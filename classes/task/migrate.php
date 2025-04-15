<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace tool_encoded\task;

use core\task\adhoc_task;
use core\task\manager;
use tool_encoded\helper;
use stdClass;

/**
 * Given our found records, this task will attempt to migrate the data.
 *
 * @package   tool_encoded
 * @copyright 2023 Mathew May <mathew.solutions>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class migrate extends adhoc_task {
    /**
     * Queue the task for the next run.
     *
     * @param int $recordid
     * @return void
     */
    public static function queue(int $recordid = 0): void {
        $task = new self();
        $task->set_custom_data([
            'recordid' => $recordid,
        ]);
        // Queue the task for the next run.
        manager::queue_adhoc_task($task);
    }

    /**
     * Perform the requested operation.
     *
     * @return void
     */
    public function execute(): void {
        global $DB;

        // Large base64 files may take time and memory.
        \core_php_time_limit::raise();
        raise_memory_limit(MEMORY_HUGE);

        $conditions = [
            'migrated' => 0,
        ];
        $recordid = $this->get_custom_data()->recordid;
        if (!empty($recordid)) {
            $conditions['id'] = $recordid;
        }

        $records = $DB->get_records('tool_encoded_base64_records', $conditions);
        // TODO: Add table only queue.
        foreach ($records as $record) {
            if (helper::can_migrate($record) && $record->migrated = $this->migrate_record($record)) {
                // Update the state of the record to indicate it has been migrated.
                $DB->update_record('tool_encoded_base64_records', $record);
                mtrace(get_string('migratesuccess', 'tool_encoded', $record));
            }
        }
    }

    /**
     * Migrate the record.
     *
     * @param stdClass $record
     * @return bool
     */
    private function migrate_record(stdClass $record): bool {
        global $DB;
        $success = false;
        // Find the associated table and columns to attempt to replace data within.
        $storedrecord = $DB->get_record($record->report_table, ['id' => $record->native_id]);
        if (empty($storedrecord)) {
            return false;
        }
        // Decode the encoded data.
        $column = $record->report_column;
        $data = $storedrecord->{$column} ?? '';
        if (!$data || !$updatedtext = $this->decode_data($record, $data)) {
            return false;
        }
        // Set the column to the link to the file.
        $storedrecord->{$column} = $updatedtext;
        if ($DB->update_record($record->report_table, $storedrecord)) {
            $success = true;
        } else {
            $success = false;
        }

        if ($success) {
            // File was written successfully and the record updated.
            return true;
        }

        // File was not written successfully and there was an issue.
        return false;
    }

    /**
     * Decodes base64 data stored in the database and replaces it with a pluginfile.
     *
     * @param stdClass $record
     * @param string $data
     * @return string
     */
    private function decode_data(stdClass $record, string $data): string {
        preg_match_all('/src="([^"]+)"/', $data, $matches);
        if (empty($srcs = $matches[1])) {
            return '';
        }

        // TODO: Improve efficiency by not storing the base64 string multiple times.
        $changes = false;
        $check = "base64,";
        foreach ($srcs as $src) {
            $start = strrpos($src, $check);
            if ($start === false) {
                continue;
            }
            $base64string = substr($src, $start + strlen($check));
            $decodeddata = base64_decode($base64string);
            if (!$pluginfile = $this->convert_to_pluginfile($record, $decodeddata)) {
                continue;
            }
            $data = str_replace($src, $pluginfile, $data);
            $changes = true;
        }
        return ($changes) ? $data : '';
    }

    /**
     * Converts decoded base64 data to a pluginfile and returns a pluginfile reference.
     *
     * @param stdClass $record
     * @param string $filecontent
     * @return string
     */
    private function convert_to_pluginfile(stdClass $record, string $filecontent): string {
        $fs = get_file_storage();

        // Use mapped data to help create a filerecord.
        if (empty($mapping = helper::get_mapping($record))) {
            return '';
        }

        switch($mapping['context']) {
            case CONTEXT_MODULE:
                $context = \context_module::instance($record->instance_id);
                break;
            case CONTEXT_COURSE:
                $context = \context_course::instance($record->instance_id);
                break;
            // TODO: Implement remaining contexts.
            case CONTEXT_USER:
            case CONTEXT_BLOCK:
            default:
                return '';
        }

        // Generate parts of filename.
        $extensioninfo = $this->get_extension_info($record->mimetype);
        $basename = $extensioninfo->group ?? 'file';
        $extension = isset($extensioninfo->extension) ? '.' . $extensioninfo->extension : '';

        $filerecord = [
            'contextid' => $context->id,
            'component' => $mapping['component'],
            'filearea' => $mapping['filearea'],
            'itemid' => str_replace('{$id}', $record->native_id, $mapping['itemid']),
            'filepath' => '/',
            'filename' => $basename . '_' . uniqid() . $extension,
            'source' => 'tool_encoded',
        ];

        // Create plugin file.
        $attempts = 0;
        while ($attempts < 3) {
            try {
                $newfile = $fs->create_file_from_string($filerecord, $filecontent);
                break;
            } catch (\stored_file_creation_exception $e) {
                // Allow a couple of additional attempts to ensure filename is unique.
                $filerecord['filename'] = $basename . '_' . uniqid() . $extension;
                $attempts++;
                continue;
            }
        }

        return isset($newfile) ? "@@PLUGINFILE@@/" . $newfile->get_filename() : '';
    }

    /**
     * Returns information about the mimetype.
     *
     * @param string $mimetype the file mimetype.
     * @return stdClass|null stdClass containing extension, type and grouping.
     */
    public static function get_extension_info($mimetype) {
        $mimetype = strtolower($mimetype);
        $mimetypesinfo = get_mimetypes_array();
        foreach ($mimetypesinfo as $extension => $info) {
            if (!isset($info['type'])) {
                continue;
            }
            if (strrpos($mimetype, $info['type']) !== false) {
                $data = new stdClass();
                $data->extension = $extension;
                $data->type = $info['type'];
                $data->group = $info['groups'][0] ?? null;
                return $data;
            }
        }
        return null;
    }
}

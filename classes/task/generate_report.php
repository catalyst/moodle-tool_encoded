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

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/lib.php');

use core\task\adhoc_task;
use core\task\manager;
use tool_encoded\helper;

/**
 * Given some columns to migrate, this task will generate a report of potential bad data.
 *
 * @package   tool_encoded
 * @copyright 2023 Mathew May <mathew.solutions>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class generate_report extends adhoc_task {
    /**
     * Queue the task for the next run.
     *
     * @param string $table
     * @param string $columns
     * @return void
     */
    public static function queue(string $table, string $columns): void {
        $task = new self();
        $task->set_custom_data([
            'table' => $table,
            'columns' => $columns,
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
        $stime = time();

        // Large tables may take time and memory.
        \core_php_time_limit::raise();
        raise_memory_limit(MEMORY_HUGE);

        $records = $this->search_columns();
        // Make a deep clone of the records just in case other functions need the raw data.
        $preppedrecords = $this->extend_records(unserialize(serialize($records)));
        $transaction = $DB->start_delegated_transaction();
        // Delete old report data. Restricting by column isn't neccesary as all relevant columns should be checked.
        $sql = "report_table = :table";
        $params = [
            'table' => $this->get_custom_data()->table,
        ];
        $DB->delete_records_select('tool_encoded_base64_records', $sql, $params);
        $DB->delete_records_select('tool_encoded_base64_tables', $sql, $params);
        $DB->insert_records('tool_encoded_base64_records', $preppedrecords);
        $DB->insert_record('tool_encoded_base64_tables', $this->get_table_record($stime));
        $transaction->allow_commit();
    }

    /**
     * Search the columns of a table for base64 encoded data.
     * @return array
     */
    private function search_columns(): array {
        global $DB;
        $table = $this->get_custom_data()->table;
        $tablecols = array_keys($DB->get_columns($table));
        $module = $DB->get_record('modules', ['name' => $table]);
        $columns = 't.' . implode(',t.', array_intersect($tablecols, ['id', 'course']));

        $sql = "SELECT $columns";
        $params = [];

        // Grabs size and mimetype from base64 columns without the column to avoid memory issues.
        $searchcols = explode(',', $this->get_custom_data()->columns);
        foreach ($searchcols as $col) {
            $paramname = $col . '_pos';
            // Use length as size approximation to avoid loading the full base64 file.
            $sql .= ",LENGTH(t.$col) AS {$col}_size,";
            // Use a simplified query to get the start of a base64 string, process later.
            $sql .= $DB->sql_substr("t.$col", $DB->sql_position(":$paramname", "t.$col"), 80) . " AS {$col}_mimetype";
            $params += [$paramname => 'data:'];
        }

        // Attempt to get course module id if we have enough data to perform a join.
        $getcmid = isset($module->id) && in_array('course', $tablecols);
        if ($getcmid) {
            $sql .= ",cm.id AS cmid";
        }
        $sql .= " FROM {{$table}} t";
        if ($getcmid) {
            $sql .= " JOIN {course_modules} cm ON cm.course = t.course AND cm.instance = t.id AND cm.module = :moduleid";
            $params += ['moduleid' => $module->id];
        }

        // Add like conditions to find base64 data.
        $firstcond = true;
        foreach ($searchcols as $col) {
            $sql .= ($firstcond) ? " WHERE " : " OR ";
            $paramname = $col . '_like';
            $sql .= $DB->sql_like("t.$col", ":$paramname");
            $params += [$paramname => '%data:%'];
            $firstcond = false;
        }
        return $DB->get_records_sql($sql, $params);
    }

    /**
     * Extend the records with additional data.
     *
     * @param array $records The records to extend.
     * @return array
     */
    private function extend_records(array $records): array {
        $cleanrecords = [];
        $columns = explode(',', $this->get_custom_data()->columns);
        foreach ($records as $record) {
            foreach ($columns as $column) {
                $cleanrecord = new \stdClass();
                // Attempt to get mimetype.
                preg_match('/data:(.*?);base64/', $record->{$column . '_mimetype'}, $matches);
                if (isset($matches[1])) {
                    $cleanrecord->mimetype = $matches[1];
                } else {
                    // No match indicates a false positive or no issue with this column.
                    continue;
                }
                // Apply size setting filter.
                $cleanrecord->encoded_size = $record->{$column . '_size'} ?? 0;
                if ($cleanrecord->encoded_size < (get_config('tool_encoded', 'size') * 1024)) {
                    continue;
                }
                $cleanrecord->native_id = (int) $record->id;
                $cleanrecord->pid = $this->get_pid() ?? 0;
                $cleanrecord->report_table = $this->get_custom_data()->table;
                $cleanrecord->report_column = $column;
                $cleanrecord->migrated = 0;
                $cleanrecord->instance_id = $record->cmid ?? helper::get_instance_id($cleanrecord);
                $cleanrecords[] = $cleanrecord;
            }
        }
        return $cleanrecords;
    }

    /**
     * Creates a table record object.
     *
     * @param int $stime start time of task
     * @return \stdClass table record
     */
    private function get_table_record(int $stime): \stdClass {
        $tablerecord = new \stdClass();
        $tablerecord->report_table = $this->get_custom_data()->table;
        $tablerecord->report_columns = $this->get_custom_data()->columns;
        $tablerecord->last_checked = $stime;
        $tablerecord->duration = (time() - $stime);
        return $tablerecord;
    }
}

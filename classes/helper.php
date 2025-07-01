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

/**
 * Helper functions for the encoded tool.
 *
 * @package   tool_encoded
 * @author    Benjamin Walker (benjaminwalker@catalyst-au.net)
 * @copyright 2024 Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_encoded;

/**
 * Tool encoded helper class.
 */
class helper {
    /** @var string Context used for questions in mapping because it is variable */
    public const CONTEXT_QUESTION = 'question';

    /** @var array Preferred extensions to use for mimetypes */
    public const PREFERRED_EXTENSIONS = [
        'image/jpeg'        => 'jpg',
        'image/png'         => 'png',
        'image/gif'         => 'gif',
        'text/plain'        => 'txt',
        'text/xml'          => 'xml',
        'application/pdf'   => 'pdf',
        'application/zip'   => 'zip',
        'application/gzip'  => 'gz',
        'audio/mpeg'        => 'mp3',
        'video/mp4'         => 'mp4',
        'video/quicktime'   => 'mov',
    ];

    /**
     * Helper method to get button type across multiple versions
     *
     * @param bool $primary Whether this is a primary button, used for styling
     * @return mixed
     */
    public static function get_button_type($primary = true) {
        global $CFG;

        // Button param was changed in Moodle 4.2 MDL-75337.
        if ($CFG->version < 2023042400) {
            return $primary;
        }

        return $primary ? \single_button::BUTTON_PRIMARY : \single_button::BUTTON_SECONDARY;
    }

    /**
     * Mapping that helps handle report generation and migrations.
     *
     * @param \stdClass $record
     * @return array
     */
    public static function get_mapping(\stdClass $record): array {
        global $DB;

        $table = $record->report_table;
        $column = $record->report_column;
        $module = $DB->get_record('modules', ['name' => $table]);
        if (isset($module->id) && $column === 'intro') {
            return [
                'component' => 'mod_' . $table,
                'filearea' => 'intro',
                'context' => CONTEXT_MODULE,
                'itemid' => 0,
                'view' => '/course/modedit.php?update={$cmid}',
            ];
        }

        $mapping = self::get_all_mapping();
        return $mapping[$table][$column] ?? [];
    }

    /**
     * Attempts to get an instance id for a base64 record.
     *
     * @param \stdClass $record
     * @return int
     */
    public static function get_instance_id(\stdClass $record) {
        if (empty($mapping = self::get_mapping($record))) {
            return 0;
        }

        if (isset($mapping['context']) && $mapping['context'] === self::CONTEXT_QUESTION) {
            return self::get_question_context($record)->instanceid ?? 0;
        }

        switch(self::get_contextlevel($record, $mapping)) {
            case CONTEXT_MODULE:
                return self::get_module_id($record, $mapping);
            case CONTEXT_COURSE:
                return self::get_course_id($record, $mapping);
            // TODO: Implement remaining contexts.
            case CONTEXT_USER:
            case CONTEXT_BLOCK:
            default:
                return 0;
        }
    }

    /**
     * Gets the context level of a record
     *
     * @param \stdClass $record
     * @param array $mapping
     * @return mixed
     */
    public static function get_contextlevel(\stdClass $record, array $mapping) {
        if (!isset($mapping['context'])) {
            return null;
        }

        if ($mapping['context'] === self::CONTEXT_QUESTION) {
            return self::get_question_context($record, true)->contextlevel ?? null;
        }

        return $mapping['context'];
    }


    /**
     * Gets the context of a question
     * This is variable and based upon the question category
     *
     * @param \stdClass $record
     * @param bool $addtorecord store the context in the record
     * @return mixed
     */
    public static function get_question_context(\stdClass $record, bool $addtorecord = false) {
        global $DB;

        if (isset($record->context)) {
            return $record->context;
        }

        $joins = "JOIN {question_versions} qv ON q.id = qv.questionid
            JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
            JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
            JOIN {context} c ON c.id = qc.contextid";

        if ($record->report_table === 'question') {
            $where = "q.id = :questionid";
            $params = ['questionid' => $record->native_id];
        } else if ($record->report_table === 'qtype_match_subquestions') {
            $joins .= " JOIN {qtype_match_subquestions} subq ON subq.questionid = q.id";
            $where = "subq.id = :subqid";
            $params = ['subqid' => $record->native_id];
        }

        $sql = "SELECT c.* FROM {question} q $joins WHERE $where";
        $context = $DB->get_record_sql($sql, $params);
        if ($addtorecord && !empty($context)) {
            $record->context = $context;
        }

        return $context;
    }

    /**
     * Attempts to get the course id for some known tables.
     *
     * @param \stdClass $record
     * @param array $mapping
     * @return int
     */
    private static function get_course_id(\stdClass $record, array $mapping): int {
        global $DB;

        $table = $record->report_table;
        $columns = array_keys($DB->get_columns($table));
        if (in_array('course', $columns)) {
            $sql = "SELECT course
                      FROM {{$table}} t
                     WHERE t.id = :nativeid";
            $params = ['nativeid' => $record->native_id];
            return $DB->get_record_sql($sql, $params)->course ?? 0;
        }

        return 0;
    }

    /**
     * Attempts to get the module id for some module subtables.
     *
     * @param \stdClass $record
     * @param array $mapping
     * @return int
     */
    private static function get_module_id(\stdClass $record, array $mapping): int {
        global $DB;

        $modulename = str_replace('mod_', '', $mapping['component']);
        $module = $DB->get_record('modules', ['name' => $modulename]);
        if (!isset($module)) {
            return 0;
        }

        $table = $record->report_table;
        $modulecols = array_keys($DB->get_columns($modulename));
        if (!empty($simplejoin = $mapping['simplejoin']) && in_array('course', $modulecols)) {
            $sql = "SELECT
                        cm.id
                    FROM
                        {{$table}} t
                    JOIN {{$modulename}} m ON m.id = t.{$simplejoin}
                    JOIN {course_modules} cm ON cm.course = m.course AND cm.instance = m.id AND cm.module = :moduleid
                    WHERE t.id = :nativeid";
            $params = [
                'moduleid' => $module->id,
                'nativeid' => $record->native_id,
            ];
            return $DB->get_record_sql($sql, $params)->id ?? 0;
        }
        return 0;
    }

    /**
     * Formats a link using part of a record.
     *
     * @param \stdClass $record
     * @return string
     */
    public static function format_view_link(\stdClass $record): string {
        $mapping = self::get_mapping($record);
        $link = $mapping['view'] ?? '';
        if (!$link) {
            return '';
        }

        $contextlevel = self::get_contextlevel($record, $mapping);
        if (empty($record->instance_id) && $contextlevel != CONTEXT_SYSTEM) {
            return '';
        }

        // Add in proper ids.
        $link = str_replace('{$id}', $record->native_id, $link);
        $link = str_replace('{$cmid}', $record->instance_id, $link);

        $courseid = $contextlevel == CONTEXT_COURSE ? $record->instance_id : 1;
        $link = str_replace('{$courseid}', $courseid, $link);
        return $link;
    }

    /**
     * Checks if a migration attempt can be performed.
     * This requires valid mapping and an instance id.
     *
     * @param \stdClass $record
     * @return bool
     */
    public static function can_migrate(\stdClass $record): bool {
        if (!empty($record->migrated)) {
            return false;
        }

        $mapping = self::get_mapping($record);
        if (empty($mapping)) {
            return false;
        }

        $contextlevel = self::get_contextlevel($record, $mapping);
        if (empty($record->instance_id) && $contextlevel != CONTEXT_SYSTEM) {
            return false;
        }

        return true;
    }

    /**
     * Mapping that helps handle report generation and migrations.
     *
     * @return array
     */
    public static function get_all_mapping(): array {
        return [
            'question' => [
                'questiontext' => [
                    'component' => 'question',
                    'filearea' => 'questiontext',
                    'context' => self::CONTEXT_QUESTION,
                    'itemid' => '{$id}',
                    'view' => '/question/bank/editquestion/question.php?courseid={$courseid}&id={$id}',
                ],
                'generalfeedback' => [
                    'component' => 'question',
                    'filearea' => 'generalfeedback',
                    'context' => self::CONTEXT_QUESTION,
                    'itemid' => '{$id}',
                    'view' => '/question/bank/editquestion/question.php?courseid={$courseid}&id={$id}',
                ],
            ],
            'qtype_match_subquestions' => [
                'questiontext' => [
                    'component' => 'qtype_match',
                    'filearea' => 'subquestion',
                    'context' => self::CONTEXT_QUESTION,
                    'itemid' => '{$id}',
                    'view' => '',
                ],
            ],
            'course_sections' => [
                'summary' => [
                    'component' => 'course',
                    'filearea' => 'section',
                    'context' => CONTEXT_COURSE,
                    'itemid' => '{$id}',
                    'view' => '/course/editsection.php?id={$id}',
                ],
            ],
            'book_chapters' => [
                'content' => [
                    'component' => 'mod_book',
                    'filearea' => 'chapter',
                    'context' => CONTEXT_MODULE,
                    'itemid' => '{$id}',
                    'view' => '/mod/book/edit.php?cmid={$cmid}&id={$id}',
                    'simplejoin' => 'bookid',
                ],
            ],
            'lesson_pages' => [
                'contents' => [
                    'component' => 'mod_lesson',
                    'filearea' => 'page_contents',
                    'context' => CONTEXT_MODULE,
                    'itemid' => '{$id}',
                    'view' => '/mod/lesson/editpage.php?id={$cmid}&pageid={$id}&edit=1',
                    'simplejoin' => 'lessonid',
                ],
            ],
        ];
    }
}

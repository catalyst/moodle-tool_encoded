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
    /**
     * Mapping that helps handle report generation and migrations.
     *
     * @param \stdClass $record
     * @return array
     */
    public static function get_mapping(\stdClass $record): array {
        GLOBAL $DB;

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

        switch($mapping['context']) {
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
     * Attempts to get the course id for some known tables.
     *
     * @param \stdClass $record
     * @param array $mapping
     * @return int
     */
    private static function get_course_id(\stdClass $record, array $mapping): int {
        GLOBAL $DB;

        if ($record->report_table === 'question') {
            $sql = "SELECT
                        quiz.course
                    FROM
                        {question} question
                    JOIN {question_versions} qv ON question.id = qv.questionid
                    JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
                    JOIN {question_references} qr ON qr.questionbankentryid = qbe.id
                        AND qr.component = 'mod_quiz'
                        AND qr.questionarea = 'slot'
                    JOIN {quiz_slots} quiz_slots ON qr.itemid = quiz_slots.id
                    JOIN {quiz} quiz ON quiz_slots.quizid = quiz.id
                    WHERE question.id = :questionid";
            $params = ['questionid' => $record->native_id];
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
        GLOBAL $DB;

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
        $link = self::get_mapping($record)['view'] ?? '';
        if (!$link || empty($record->instance_id)) {
            return '';
        }

        // Add in proper ids.
        $link = str_replace('{$id}', $record->native_id, $link);
        $link = str_replace('{$cmid}', $record->instance_id, $link);
        $link = str_replace('{$courseid}', $record->instance_id, $link);
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
        return empty($record->migrated) && !empty($record->instance_id) && !empty(self::get_mapping($record));
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
                    'context' => CONTEXT_COURSE,
                    'itemid' => '{$id}',
                    'view' => '/question/bank/editquestion/question.php?courseid={$courseid}&id={$id}',
                ],
                'generalfeedback' => [
                    'component' => 'question',
                    'filearea' => 'generalfeedback',
                    'context' => CONTEXT_COURSE,
                    'itemid' => '{$id}',
                    'view' => '/question/bank/editquestion/question.php?courseid={$courseid}&id={$id}',
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

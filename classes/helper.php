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

    /** @var string Context used for grades in mapping because it is variable */
    public const CONTEXT_GRADE = 'grade';

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

        $variablecontext = self::get_variable_context($record, $mapping);
        if (isset($variablecontext)) {
            return $variablecontext->instanceid ?? 0;
        }

        switch(self::get_contextlevel($record, $mapping)) {
            case CONTEXT_MODULE:
                return self::get_coursemodule_id($record, $mapping);
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

        $variablecontext = self::get_variable_context($record, $mapping, true);
        if (isset($variablecontext)) {
            return $variablecontext->contextlevel ?? null;
        }

        return $mapping['context'];
    }


    /**
     * Gets context for a record that can be variable
     *
     * @param \stdClass $record
     * @param array $mapping
     * @param bool $addtorecord store the context in the record
     * @return mixed
     */
    public static function get_variable_context(\stdClass $record, array $mapping, bool $addtorecord = false) {
        if (isset($record->context)) {
            return $record->context;
        }

        switch($mapping['context'] ?? null) {
            case self::CONTEXT_QUESTION:
                $context = self::get_question_context($record);
                break;
            case self::CONTEXT_GRADE:
                $context = self::get_grade_context($record);
                break;
            default:
                return null;
        }

        if (!empty($context) && $addtorecord) {
            $record->context = $context;
        }

        return $context;
    }


    /**
     * Gets the context of a question
     * This is variable and based upon the question category
     *
     * @param \stdClass $record
     * @return mixed
     */
    public static function get_question_context(\stdClass $record): mixed {
        global $DB;

        // Manually get context to avoid loading the question.
        $joins = "JOIN {question_versions} qv ON q.id = qv.questionid
            JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
            JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
            JOIN {context} c ON c.id = qc.contextid";

        $table = $record->report_table;
        if ($table === 'question') {
            $where = "q.id = :questionid";
            $params = ['questionid' => $record->native_id];
        } else if (strpos($table, 'qtype_') === 0) {
            // All tables starting with qtype should contain questionid.
            $joins .= " JOIN {{$table}} subq ON subq.questionid = q.id";
            $where = "subq.id = :subqid";
            $params = ['subqid' => $record->native_id];
        } else {
            return false;
        }

        $sql = "SELECT c.* FROM {question} q $joins WHERE $where";
        return $DB->get_record_sql($sql, $params);
    }

    /**
     * Gets the context of a grade
     * This is variable and based upon the grade item
     *
     * @param \stdClass $record
     * @return mixed
     */
    public static function get_grade_context(\stdClass $record) {
        global $CFG, $DB;
        require_once($CFG->libdir.'/gradelib.php');

        $itemid = $DB->get_field($record->report_table, 'itemid', ['id' => $record->native_id]);
        if (empty($itemid)) {
            return false;
        }

        $grade = new \grade_grade(['itemid' => $itemid], false);
        return $grade->get_context();
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
     * Attempts to get the coursemodule id.
     *
     * @param \stdClass $record
     * @param array $mapping
     * @return int
     */
    private static function get_coursemodule_id(\stdClass $record, array $mapping): int {
        global $DB;

        $modulename = str_replace('mod_', '', $mapping['component']);
        $module = $DB->get_record('modules', ['name' => $modulename]);
        if (!isset($module)) {
            return 0;
        }

        // To get the coursemodule id, we need the module instance id.
        $table = $record->report_table;
        if (isset($mapping['simplelookup'])) {
            // If the instance id is in the same table we can get this with a simple lookup.
            $moduleinstance = $DB->get_field($table, $mapping['simplelookup'], ['id' => $record->native_id]);
        } else if ($table === 'forum_posts') {
            $sql = "SELECT d.forum
                      FROM {forum_posts} p
                      JOIN {forum_discussions} d ON d.id = p.discussion
                     WHERE p.id = :postid";
            $params = ['postid' => $record->native_id];
            $moduleinstance = $DB->get_field_sql($sql, $params);
        }

        if (!empty($moduleinstance)) {
            return get_coursemodule_from_instance($modulename, $moduleinstance)->id ?? 0;
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
            'qtype_ddmatch_subquestions' => [
                'answertext' => [
                    'component' => 'qtype_ddmatch',
                    'filearea' => 'subanswer',
                    'context' => self::CONTEXT_QUESTION,
                    'itemid' => '{$id}',
                    'view' => '',
                ],
                'questiontext' => [
                    'component' => 'qtype_ddmatch',
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
                    'simplelookup' => 'bookid',
                ],
            ],
            'lesson_pages' => [
                'contents' => [
                    'component' => 'mod_lesson',
                    'filearea' => 'page_contents',
                    'context' => CONTEXT_MODULE,
                    'itemid' => '{$id}',
                    'view' => '/mod/lesson/editpage.php?id={$cmid}&pageid={$id}&edit=1',
                    'simplelookup' => 'lessonid',
                ],
            ],
            'forum_posts' => [
                'message' => [
                    'component' => 'mod_forum',
                    'filearea' => 'post',
                    'context' => CONTEXT_MODULE,
                    'itemid' => '{$id}',
                    'view' => '/mod/forum/post.php?edit={$id}',
                ],
            ],
            'page' => [
                'content' => [
                    'component' => 'mod_page',
                    'filearea' => 'content',
                    'context' => CONTEXT_MODULE,
                    'itemid' => 0,
                    'view' => '/course/modedit.php?update={$cmid}',
                ],
            ],
            'grade_grades' => [
                'feedback' => [
                    'component' => 'grade',
                    'filearea' => 'feedback',
                    'context' => self::CONTEXT_GRADE,
                    'itemid' => '{$id}',
                    'view' => '',
                ],
            ],
            'grade_grades_history' => [
                'feedback' => [
                    'component' => 'grade',
                    'filearea' => 'historyfeedback',
                    'context' => self::CONTEXT_GRADE,
                    'itemid' => '{$id}',
                    'view' => '',
                ],
            ],
        ];
    }
}

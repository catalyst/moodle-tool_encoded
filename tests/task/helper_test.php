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

namespace tool_encoded;

/**
 * Helper unit tests.
 *
 * @package   tool_encoded
 * @author    Benjamin Walker (benjaminwalker@catalyst-au.net)
 * @copyright 2024 Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \tool_encoded\helper
 */
final class helper_test extends \advanced_testcase {
    /**
     * Set up before each test.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
    }

    /**
     * Tests getting instance id from a base64 record
     *
     * @covers ::get_instance_id
     */
    public function test_get_instance_id(): void {
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        // Test course mapping.
        $section = course_create_section($course);
        $record = (object) [
            'report_table' => 'course_sections',
            'report_column' => 'summary',
            'native_id' => $section->id,
        ];
        $instanceid = helper::get_instance_id($record);
        $this->assertEquals($course->id, $instanceid);

        // Logic for direct module tables like 'page' load the cmid directly and should not use the helper.

        // Test course module mapping with simple lookups.
        $book = $this->getDataGenerator()->create_module('book', ['course' => $course->id]);
        $bookgenerator = $this->getDataGenerator()->get_plugin_generator('mod_book');
        $chapter = $bookgenerator->create_chapter(['bookid' => $book->id]);

        $record = (object) [
            'report_table' => 'book_chapters',
            'report_column' => 'content',
            'native_id' => $chapter->id,
        ];
        $instanceid = helper::get_instance_id($record);
        $this->assertEquals($book->cmid, $instanceid);

        // Test course module mapping for forum posts.
        $forum = $this->getDataGenerator()->create_module('forum', ['course' => $course->id]);
        $forumgenerator = $this->getDataGenerator()->get_plugin_generator('mod_forum');
        $properties = (object) [
            'course' => $course->id,
            'forum' => $forum->id,
            'userid' => $user->id,
        ];
        $discussion = $forumgenerator->create_discussion($properties);
        $properties->discussion = $discussion->id;
        $post = $forumgenerator->create_post($properties);

        $record = (object) [
            'report_table' => 'forum_posts',
            'report_column' => 'message',
            'native_id' => $post->id,
        ];
        $instanceid = helper::get_instance_id($record);
        $this->assertEquals($forum->cmid, $instanceid);
    }

    /**
     * Tests getting variable context from a base64 record
     *
     * @covers ::get_variable_context
     */
    public function test_get_variable_context(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        // Test mapping of question table to module context (qbank in site course).
        // Since Moodle 5.1 question categories always live inside a qbank module context.
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $category = $questiongenerator->create_question_category();
        $question = $questiongenerator->create_question('multichoice', null, ['category' => $category->id]);

        $record = (object) [
            'report_table' => 'question',
            'report_column' => 'questiontext',
            'native_id' => $question->id,
        ];

        $mapping = helper::get_mapping($record);
        $context = helper::get_variable_context($record, $mapping);
        $expectedcontext = \context::instance_by_id($category->contextid);
        $this->assertEquals($expectedcontext->contextlevel, $context->contextlevel);
        $this->assertEquals($expectedcontext->instanceid, $context->instanceid);

        // Test mapping of question table to module context (qbank in course).
        $coursecontext = \context_course::instance($course->id);
        $category = $questiongenerator->create_question_category(['contextid' => $coursecontext->id]);
        $question = $questiongenerator->create_question('multichoice', null, ['category' => $category->id]);

        $record = (object) [
            'report_table' => 'question',
            'report_column' => 'questiontext',
            'native_id' => $question->id,
        ];

        $mapping = helper::get_mapping($record);
        $context = helper::get_variable_context($record, $mapping);
        $expectedcontext = \context::instance_by_id($category->contextid);
        $this->assertEquals($expectedcontext->contextlevel, $context->contextlevel);
        $this->assertEquals($expectedcontext->instanceid, $context->instanceid);

        // Test mapping of question type tables to module context (qbank in course).
        $category = $questiongenerator->create_question_category(['contextid' => $coursecontext->id]);
        $question = $questiongenerator->create_question('match', null, ['category' => $category->id]);

        $subquestionid = $DB->get_field('qtype_match_subquestions', 'id', ['questionid' => $question->id], IGNORE_MULTIPLE);
        $record = (object) [
            'report_table' => 'qtype_match_subquestions',
            'report_column' => 'questiontext',
            'native_id' => $subquestionid,
        ];

        $mapping = helper::get_mapping($record);
        $context = helper::get_variable_context($record, $mapping);
        $expectedcontext = \context::instance_by_id($category->contextid);
        $this->assertEquals($expectedcontext->contextlevel, $context->contextlevel);
        $this->assertEquals($expectedcontext->instanceid, $context->instanceid);

        // Test mapping of grade grades to module context.
        $assign = $this->getDataGenerator()->create_module('assign', [
            'course' => $course->id,
            'assignfeedback_comments_enabled' => 1,
        ]);

        $gradeitem = $this->getDataGenerator()->create_grade_item([
            'courseid' => $course->id,
            'itemtype' => 'mod',
            'itemmodule' => 'assign',
            'iteminstance' => $assign->id,
        ]);

        $gradegrade = $this->getDataGenerator()->create_grade_grade([
            'itemid' => $gradeitem->id,
            'userid' => $user->id,
            'assignfeedbackcomments_editor' => ['text' => 'Comment', 'format' => FORMAT_MOODLE],
        ]);

        $record = (object) [
            'report_table' => 'grade_grades',
            'report_column' => 'feedback',
            'native_id' => $gradegrade->id,
        ];

        $mapping = helper::get_mapping($record);
        $context = helper::get_variable_context($record, $mapping);
        $modulecontext = \context_module::instance($assign->cmid);
        $this->assertEquals($modulecontext->contextlevel, $context->contextlevel);
        $this->assertEquals($modulecontext->instanceid, $context->instanceid);
    }
}

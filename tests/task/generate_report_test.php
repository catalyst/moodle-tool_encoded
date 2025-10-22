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

use ReflectionMethod;

defined('MOODLE_INTERNAL') || die();

/**
 * Unit tests.
 *
 * @package   tool_encoded
 * @copyright 2023 Mathew May <mathew.solutions>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \tool_encoded\task\generate_report
 */
final class generate_report_test extends \advanced_testcase {
    /**
     * Confirm the task is created and executed.
     *
     * @dataProvider task_provider
     * @param string $table The desired table.
     * @param string $columns The desired columns.
     * @param array $records The expected records.
     * @return void
     */
    public function test_task($table, $columns, $records): void {
        global $DB;
        $this->resetAfterTest();
        // Disable size filter for tests as examples are under the default size.
        set_config('size', 0, 'tool_encoded');

        $recordid = $DB->insert_record($table, [
            'submissionid' => '2',
            'reviewerid' => '2',
            'weight' => '1',
            'feedbackauthor' => '<p>Bad data &lt;img alt="" src="data:image/gif;base64,R0lGODdhAQABAPAAAP8AAAAAACwAAAAAAQABAAACAkQBADs=" /&gt;</p>',
        ]);

        $task = new generate_report();
        $task->set_custom_data([
            'table' => $table,
            'columns' => $columns,
        ]);
        $task->queue($table, $columns);
        $task->execute();
        $this->runAdhocTasks('\tool_encoded\task\generate_report');
        $this->assertInstanceOf(generate_report::class, $task);

        // Set method accessibility.
        $method = new ReflectionMethod(generate_report::class, 'search_columns');
        $method->setAccessible(true);

        $erreport = new generate_report();
        $erreport->set_custom_data([
            'table' => $table,
            'columns' => $columns,
        ]);
        $searchedtables = $method->invoke($erreport);

        $ermethod = new ReflectionMethod(generate_report::class, 'extend_records');
        $ermethod->setAccessible(true);
        $extendedrecords = $ermethod->invoke($erreport, $searchedtables);
        foreach ($extendedrecords as $errecord) {
            $records['native_id'] = $recordid;
            $records['timecreated'] = $errecord->timecreated;
            $records['timemodified'] = $errecord->timemodified;
            $this->assertEquals($records, (array) $errecord);
        }
    }

    /**
     * Data provider for the test_task.
     *
     * @return array
     */
    public static function task_provider(): array {
        return [
            'workshop assessments.' => [
                'workshop_assessments',
                'feedbackauthor',
                [
                    'encoded_size' => 113,
                    'mimetype' => 'image/gif',
                    'pid' => 0,
                    'report_table' => 'workshop_assessments',
                    'report_column' => 'feedbackauthor',
                    'migrated' => 0,
                    'instance_id' => 0,
                ],
            ],
        ];
    }
}

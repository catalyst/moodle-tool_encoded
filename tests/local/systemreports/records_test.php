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

namespace tool_encoded\local\systemreports;

use context_system;
use core_reportbuilder\system_report_factory;
use tool_encoded\task\generate_report;

defined('MOODLE_INTERNAL') || die();

/**
 * Unit tests.
 *
 * @package   tool_encoded
 * @copyright 2023 Mathew May <mathew.solutions>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \tool_encoded\local\systemreports\records
 */
final class records_test extends \advanced_testcase {
    public function test_records(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        // Disable size filter for tests as tests are under the default size.
        set_config('size', 0, 'tool_encoded');

        $DB->insert_record('workshop_assessments', [
            'submissionid' => '2',
            'reviewerid' => '2',
            'weight' => '1',
            'feedbackauthor' => '<p>Bad data &lt;img alt="" src="data:image/gif;base64,R0lGODdhAQABAPAAAP8AAAAAACwAAAAAAQABAAACAkQBADs=" /&gt;</p>',
        ]);

        $task = new generate_report();
        $task->set_custom_data([
            'table' => 'workshop_assessments',
            'columns' => 'feedbackauthor',
        ]);
        $task->execute();
        $task = new generate_report();
        $task->set_custom_data([
            'table' => 'workshop_assessments',
            'columns' => 'feedbackauthor,feedbackreviewer',
        ]);
        $task->execute();

        $records = system_report_factory::create(records::class, context_system::instance());
        $this->assertInstanceOf(records::class, $records);
        $this->assertEquals('Encoder log', $records->get_name());

        // TODO: Implement assertEquals for $records->get_columns().

        // TODO: Implement assertEquals for $records->get_actions().
    }
}

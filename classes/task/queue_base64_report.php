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

/**
 * Scheduled task to queue generation of base64 report data.
 *
 * @package    tool_encoded
 * @author     Benjamin Walker <benjaminwalker@catalyst-au.net>
 * @copyright  2025, Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class queue_base64_report extends \core\task\scheduled_task {
    /**
     * Get task name
     */
    public function get_name() {
        return get_string('queuereport', 'tool_encoded');
    }

    /**
     * Execute task
     */
    public function execute() {
        generate_report::spawnreporttasks();
    }
}

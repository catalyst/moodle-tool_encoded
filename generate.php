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
 * Admin tool base64encode generate page.
 *
 * @package    tool_encoded
 * @author     Benjamin Walker <benjaminwalker@catalyst-au.net>
 * @copyright  2025, Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core\notification;
use tool_encoded\output\generate;
use tool_encoded\task\generate_report;

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

$PAGE->set_url(new moodle_url('/admin/tool/encoded/generate.php'));
admin_externalpage_setup('tool_encoded_generate');

if ($form = data_submitted()) {
    require_sesskey();
    if (isset($form->table)) {
        if ($form->table === 'all') {
            generate_report::spawnreporttasks();
        } else {
            generate_report::queue($form->table, $form->columns);
        }
        notification::success(get_string('generatenotification', 'tool_encoded'));
    }

    // Redirect to prevent multiple submits.
    redirect($PAGE->url);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('generatereport', 'tool_encoded'));

$table = new generate();
echo $table->get_page_buttons();
$table->display();

echo $OUTPUT->footer();

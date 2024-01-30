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
 * Admin tool base64encode landing page.
 *
 * @package   tool_encoded
 * @copyright 2023 Mathew May <mathew.solutions>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core\notification;
use core_reportbuilder\system_report_factory;
use tool_encoded\local\systemreports\records;
use tool_encoded\output\generate;
use tool_encoded\task\generate_report;
use tool_encoded\local\helper;

require_once(__DIR__ . '/../../../config.php');

$action = optional_param('action', 'report', PARAM_ALPHA);

require_login(0, false);

if (!$context = context_system::instance()) {
    throw new moodle_exception('wrongcontext', 'error');
}

require_capability('moodle/site:configview', $context);

$url = new moodle_url('/admin/tool/encoded/index.php');

// Display the page.
$PAGE->set_context(context_system::instance());
$PAGE->set_url($url);
$PAGE->set_title('Encoded tool');
$PAGE->set_pagelayout('admin');

if (data_submitted() && confirm_sesskey()) {
    $form = data_submitted();
    // Override the action since a form was submitted just in case.
    $action = $form->action;
    if ($action === 'generate') {
        if (isset($form->all) && (bool) $form->all === true) {
            helper::spawnReportTasks();
        } else {
            generate_report::queue($form->table, $form->columns);
        }
        // TODO: String.
        echo notification::success('Report generation queued.');
    }
}

if ($action === 'report') {
    // TODO: String.
    $PAGE->set_heading('Found records');
    echo $OUTPUT->header();
    $report = system_report_factory::create(records::class, context_system::instance());
    echo $report->output();
} else {
    // TODO: String.
    $PAGE->set_heading('Generate report');
    echo $OUTPUT->header();
    $instance = new generate();
    // Example of way to load different functionality based on the desired action.
    echo $OUTPUT->render_from_template('tool_encoded/generate', $instance->export_for_template($OUTPUT));
}

echo $OUTPUT->footer();

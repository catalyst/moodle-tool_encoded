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
use tool_encoded\task\migrate;
use tool_encoded\local\helper;

require_once(__DIR__ . '/../../../config.php');

$action = optional_param('action', 'report', PARAM_ALPHA);
$clear = optional_param('clearrecords', null, PARAM_BOOL);
$confirm = optional_param('confirm', null, PARAM_BOOL);

require_login(0, false);

if (!$context = context_system::instance()) {
    throw new moodle_exception('wrongcontext', 'error');
}

require_capability('moodle/site:configview', $context);

$url = new moodle_url('/admin/tool/encoded/index.php', ['action' => $action]);

// Display the page.
$PAGE->set_context(context_system::instance());
$PAGE->set_url($url);
$PAGE->set_title('Encoded tool');
$PAGE->set_pagelayout('admin');

if ($clear && !$confirm) {
    echo $OUTPUT->header();
    $confirm = new moodle_url($url, ['clearrecords' => 1, 'confirm' => 1, 'sesskey' => sesskey()]);
    echo $OUTPUT->confirm(get_string('clearconfirm', 'tool_encoded'), $confirm, $url);
    echo $OUTPUT->footer();
    exit();
} else if ($clear && $confirm) {
    require_sesskey();
    $DB->delete_records('tool_encoded_base64_records');
    notification::success(get_string('clearnotification', 'tool_encoded'));
    redirect($url);
}

if (data_submitted() && confirm_sesskey()) {
    $form = data_submitted();
    // Override the action since a form was submitted just in case.
    $action = $form->action;
    if ($action === 'generate') {
        if (isset($form->all) && (bool) $form->all === true) {
            helper::spawnreporttasks();
        } else {
            generate_report::queue($form->table, $form->columns);
        }
        notification::success(get_string('generatenotification', 'tool_encoded'));
    } else if ($action === 'migrate') {
        if (isset($form->recordid)) {
            migrate::queue($form->recordid);
            if ($form->recordid == 0) {
                notification::success(get_string('migratenotificationall', 'tool_encoded'));
            } else {
                notification::success(get_string('migratenotification', 'tool_encoded', $form->recordid));
            }
        }
    }
    // Redirect to prevent multiple submits.
    redirect($url);
}

if ($action === 'report' || $action === 'migrate') {
    $PAGE->set_heading(get_string('recordsfound', 'tool_encoded'));
    echo $OUTPUT->header();
    $report = system_report_factory::create(records::class, context_system::instance());
    echo $report->output();
    echo $OUTPUT->render_from_template('tool_encoded/reportlinks', [
        'migrateall' => true,
        'recordid' => 0,
        'count' => helper::countrecords(),
        'sesskey' => sesskey(),
    ]);
} else {
    $PAGE->set_heading(get_string('generatereport', 'tool_encoded'));
    echo $OUTPUT->header();
    $instance = new generate();
    // Example of way to load different functionality based on the desired action.
    echo $OUTPUT->render_from_template('tool_encoded/generate', $instance->export_for_template($OUTPUT));
}

echo $OUTPUT->footer();

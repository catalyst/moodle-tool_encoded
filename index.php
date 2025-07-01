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
use tool_encoded\task\migrate;

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

$PAGE->set_url(new moodle_url('/admin/tool/encoded/index.php'));
admin_externalpage_setup('tool_encoded_report');

if ($form = data_submitted()) {
    require_sesskey();
    $action = $form->action;
    if ($action === 'migrate') {
        $recordid = empty($form->recordid) ? 0 : $form->recordid;
        migrate::queue($recordid);
        $messageidentifier = $recordid ? 'migratenotification' : 'migratenotificationall';
        notification::success(get_string($messageidentifier, 'tool_encoded', $recordid));
    } else if ($action === 'clearrecords') {
        $DB->delete_records('tool_encoded_base64_records');
        notification::success(get_string('clearnotification', 'tool_encoded'));
    }

    // Redirect to prevent multiple submits.
    redirect($PAGE->url);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('displayreport', 'tool_encoded'));

$report = system_report_factory::create(records::class, context_system::instance());

echo $report->get_page_buttons();
echo $report->output();

echo $OUTPUT->footer();

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
 * Admin tool base64encode strings.
 *
 * @package    tool_encoded
 * @copyright  2023 Mathew May <mathew.solutions>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
// Plugin strings.
$string['pluginname'] = 'Base64 Encoder';
$string['privacy:metadata'] = 'The Site admin presets tool does not store any personal data.';
$string['generatereport'] = 'Generate report';
$string['displayreport'] = 'Display report';
$string['sizesetting'] = 'Size setting';
$string['sizesettingdesc'] = 'The size in kilobytes to flag in the generated report.';

// Generate page strings.
$string['duration'] = 'Scan time';
$string['lastchecked'] = 'Last checked';
$string['recordsfound'] = 'Records found';
$string['recordsfoundnotfound'] = 'No records found';
$string['queuetable'] = 'Queue task for all columns';
$string['queuealltables'] = 'Queue generation tasks for all ({$a}) tables';
$string['generatenotification'] = 'Generation task queued';

// RB strings.
$string['reportid'] = 'ID';
$string['instanceid'] = 'Instance ID';
$string['encoderlog'] = 'Encoder log';
$string['encoderentity'] = 'Encoder entity';
$string['size'] = '{$a} kb';
$string['recordid'] = 'Native ID';
$string['table'] = 'Table';
$string['column'] = 'Column';
$string['mime'] = 'MIME Type';
$string['migrate'] = 'Migrate';
$string['migrated'] = 'Migrated';
$string['queuerecord'] = 'Queue migrate task for this record';
$string['queueallrecords'] = 'Queue migrate tasks for all records';
$string['migratenotification'] = 'Migrate task queued for record {$a}';
$string['migratenotificationall'] = 'Migrate task queued for all records';

// Task status.
$string['migratesuccess'] = 'Record {$a->id} successfully migrated to pluginfile: {$a->report_table} {$a->report_column} {$a->native_id}';

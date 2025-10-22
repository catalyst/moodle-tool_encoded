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
 * Plugin upgrade code
 *
 * @package    tool_encoded
 * @author     Benjamin Walker <benjaminwalker@catalyst-au.net>
 * @copyright  2025, Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Function to upgrade tool_encoded.
 *
 * @param int $oldversion the version we are upgrading from
 * @return bool result
 */
function xmldb_tool_encoded_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2025062600) {
        // Changing type of field report_table on table tool_encoded_base64_tables to char.
        $table = new xmldb_table('tool_encoded_base64_tables');
        $field = new xmldb_field('report_table', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null, 'id');

        // Launch change of type for field report_table.
        $dbman->change_field_type($table, $field);

        // Changing type of field report_table on table tool_encoded_base64_records to char.
        $table = new xmldb_table('tool_encoded_base64_records');
        $field = new xmldb_field('report_table', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null, 'pid');

        // Launch change of type for field report_table.
        $dbman->change_field_type($table, $field);

        // Changing type of field report_column on table tool_encoded_base64_records to char.
        $field = new xmldb_field('report_column', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null, 'report_table');

        // Launch change of type for field report_column.
        $dbman->change_field_type($table, $field);

        // Define field timecreated to be added to tool_encoded_base64_records.
        $field = new xmldb_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'migrated');

        // Conditionally launch add field timecreated.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field timemodified to be added to tool_encoded_base64_records.
        $field = new xmldb_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'timecreated');

        // Conditionally launch add field timemodified.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define index nativeid (not unique) to be added to tool_encoded_base64_records.
        $index = new xmldb_index('nativeid', XMLDB_INDEX_NOTUNIQUE, ['native_id']);

        // Conditionally launch add index nativeid.
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        // Queue report generation.
        \tool_encoded\task\generate_report::spawnreporttasks();

        // Encoded savepoint reached.
        upgrade_plugin_savepoint(true, 2025062600, 'tool', 'encoded');
    }

    return true;
}

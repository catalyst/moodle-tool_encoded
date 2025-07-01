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
use core_reportbuilder\system_report;
use tool_encoded\helper;
use tool_encoded\local\entities\records as record_entity;

/**
 * System report class implementation
 *
 * @package   tool_encoded
 * @copyright 2023 Mathew May <mathew.solutions>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class records extends system_report {
    /**
     * Initialise report, we need to set the main table, load our entities and set columns/filters
     */
    protected function initialise(): void {
        // Our main entity, it contains all the column definitions that we need.
        $entitymain = new record_entity();
        $entitymainalias = $entitymain->get_table_alias('tool_encoded_base64_records');

        $this->set_main_table('tool_encoded_base64_records', $entitymainalias);
        $this->add_entity($entitymain);

        // Now we can call our helper methods to add the content we want to include in the report.
        $this->add_columns();
        $this->add_filters();
    }

    /**
     * Validates access to view this report
     *
     * @return bool
     */
    protected function can_view(): bool {
        return has_capability('moodle/site:configview', context_system::instance());
    }

    /**
     * Get the visible name of the report
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('encoderlog', 'tool_encoded');
    }

    /**
     * Adds the columns we want to display in the report
     *
     * They are all provided by the entities we previously added in the {@see initialise} method, referencing each by their
     * unique identifier
     */
    protected function add_columns(): void {
        $this->add_columns_from_entities([
            'records:id',
            'records:report_table',
            'records:report_column',
            'records:native_id',
            'records:instance_id',
            'records:encoded_size',
            'records:mimetype',
            'records:migrated',
            'records:view_link',
        ]);
        $this->set_initial_sort_column('records:report_table', SORT_ASC);
    }

    /**
     * Adds the filters we want to display in the report
     *
     * They are all provided by the entities we previously added in the {@see initialise} method, referencing each by their
     * unique identifier
     */
    protected function add_filters(): void {
        $this->add_filters_from_entities([
            'records:report_table',
            'records:report_column',
            'records:encoded_size',
            'records:mimetype',
            'records:migrated',
            'records:id',
            'records:instance_id',
        ]);
    }

    /**
     * Gets page buttons for the report table
     *
     * @return string
     */
    public function get_page_buttons(): string {
        global $DB, $OUTPUT;

        $buttons = '';
        $unmigrated = $DB->record_exists('tool_encoded_base64_records', ['migrated' => 0]);
        $anyrecords = $unmigrated || $DB->record_exists('tool_encoded_base64_records', []);

        // Add button to migrate all records.
        if ($unmigrated) {
            $migratebutton = new \single_button(
                new \moodle_url('/admin/tool/encoded/index.php', ['action' => 'migrate', 'sesskey' => sesskey()]),
                get_string('queueallrecords', 'tool_encoded'), 'post', helper::get_button_type());
            $migratebutton->add_confirm_action(get_string('confirmmigrate', 'tool_encoded'));
            $buttons .= $OUTPUT->render($migratebutton);
        }

        // Add link to generate page.
        $buttons .= \html_writer::link(new \moodle_url('/admin/tool/encoded/generate.php'),
            get_string('generatereport', 'tool_encoded'), ['class' => 'btn btn-secondary m-1']);

        // Add button to clear all records.
        if ($anyrecords) {
            $clearbutton = new \single_button(
                new \moodle_url('/admin/tool/encoded/index.php', ['action' => 'clearrecords', 'sesskey' => sesskey()]),
                get_string('clearrecords', 'tool_encoded'), 'post');
            $clearbutton->add_confirm_action(get_string('confirmclear', 'tool_encoded'));
            $buttons .= $OUTPUT->render($clearbutton);
        }

        return $buttons;
    }

}

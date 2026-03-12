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

namespace tool_encoded\output;

/**
 * Show a summary of records.
 *
 * @package    tool_encoded
 * @author     Benjamin Walker <benjaminwalker@catalyst-au.net>
 * @copyright  2025, Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class summary extends \table_sql {
    /**
     * Constructor.
     */
    public function __construct() {
        global $PAGE;

        parent::__construct('tool_encoded_summary');

        if (!$PAGE->has_set_url()) {
            $PAGE->set_url(new moodle_url('/admin/tool/encoded/index.php'));
        }

        $this->set_attribute('class', 'generaltable admintable w-auto');
        $this->define_columns(['report_table', 'report_column', 'count', 'max_size', 'total_size', 'mapped']);
        $this->define_headers([
            get_string('table', 'tool_encoded'),
            get_string('column', 'tool_encoded'),
            get_string('records', 'tool_encoded'),
            get_string('maxsize', 'tool_encoded'),
            get_string('totalsize', 'tool_encoded'),
            get_string('mapped', 'tool_encoded'),
        ]);
        $this->pageable(false);
        $this->sortable(false, 'total_size', SORT_DESC);
        $this->collapsible(false);
        $this->is_downloadable(false);
        $this->define_baseurl($PAGE->url);

        $selectsql = "MAX(id) AS id, report_table, report_column, COUNT(*) AS count, SUM(encoded_size) AS total_size, "
            . "MAX(encoded_size) AS max_size";
        $wheresql = "migrated <> 1 GROUP BY report_table, report_column";
        $countsql = "SELECT COUNT(*) FROM (SELECT 1 FROM {tool_encoded_base64_records} WHERE $wheresql) g";

        $this->set_sql($selectsql, '{tool_encoded_base64_records}', $wheresql);
        $this->set_count_sql($countsql);

        $this->column_class('count', 'text-right');
    }

    /**
     * Display value for 'max size' column.
     *
     * @param \stdClass $record
     * @return string
     */
    public function col_max_size(\stdClass $record): string {
        return display_size($record->max_size);
    }

    /**
     * Display value for 'total size' column.
     *
     * @param \stdClass $record
     * @return string
     */
    public function col_total_size(\stdClass $record): string {
        return display_size($record->total_size);
    }

    /**
     * Display value for 'mapped' column
     *
     * @param \stdClass $record
     * @return string
     */
    public function col_mapped(\stdClass $record): string {
        $mapping = \tool_encoded\helper::get_mapping($record);
        return !empty($mapping) ? get_string('yes') : get_string('no');
    }

    /**
     * Gets the total count of records with base64 data in the table
     *
     * @return int total count of records with base64 data
     */
    public function get_total_count(): int {
        $sum = 0;
        foreach ($this->rawdata as $row) {
            if (isset($row->count)) {
                $sum += $row->count;
            }
        }
        return $sum;
    }

    /**
     * Gets the total size of base64 data in the table
     *
     * @return int total size of base64 data
     */
    public function get_total_size(): int {
        $sum = 0;
        foreach ($this->rawdata as $row) {
            if (isset($row->total_size)) {
                $sum += $row->total_size;
            }
        }
        return $sum;
    }
}

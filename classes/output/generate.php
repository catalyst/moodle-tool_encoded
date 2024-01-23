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
 * Show the options to generate a report.
 *
 * @package   tool_encoded
 * @copyright 2023 Mathew May <mathew.solutions>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_encoded\output;

use renderable;
use renderer_base;
use templatable;

class generate implements templatable, renderable {

    protected $potentialtables = [];

    public function __construct() {
        $this->potentialtables = $this->fetch_tables();
    }

    public function fetch_tables(): array {
        global $DB;

        $potentialtables = [];
        $link = new \moodle_url('/admin/tool/encoded/index.php');

        // Cached fetch;
        $tables = $DB->get_tables();
        foreach ($tables as $table) {
            $potentialcolumns = [];
            $tablecols = $DB->get_columns($table);
            $allcols = [];
            foreach ($tablecols as $column) {
                // Only convert columns that are either text or long varchar.
                if ($column->meta_type == 'X' || ($column->meta_type == 'C' && $column->max_length > 255)) {
                    // We only want fields that have an associated format col as they are editable by the user.
                    if (array_key_exists($column->name.'format', $tablecols)) {
                        $allcols[] = $column->name;
                        $potentialcolumns[] = [
                            'name' => $column->name,
                        ];
                    }
                }
            }
            if (!empty($potentialcolumns)) {
                $all = implode(',', $allcols);
                // Check if we have any records for this table.
                $reportrun = $DB->record_exists_select(
                    'tool_encoded_potential_records',
                    'report_table = ? and report_columns = ?',
                    [$table, $all]
                );
                $potentialtables[$table] = [
                    'name' => $table,
                    'columns' => $potentialcolumns,
                    'reportstatus' => $reportrun,
                    'all' => $all,
                    'link' => $link->out(false),
                ];
            }
        }
        return $potentialtables;
    }

    public function export_for_template(renderer_base $output) {
        return [
            'tables' => array_values($this->potentialtables),
            'count' => count($this->potentialtables),
            'sesskey' => sesskey(),
        ];
    }
}

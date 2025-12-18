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

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/tablelib.php');

use tool_encoded\helper;

/**
 * Show the options to generate a report and a summary.
 *
 * @package    tool_encoded
 * @author     Benjamin Walker <benjaminwalker@catalyst-au.net>
 * @copyright  2025, Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class generate extends \flexible_table {
    /**
     * @var array table info and summary
     */
    private array $tabledata = [];

    /**
     * Columns to be displayed in the table.
     *
     * @var array
     */
    const COLUMNS = [
        'table',
        'column',
        'records',
        'maxsize',
        'totalsize',
        'lastchecked',
        'duration',
        'actions',
    ];

    /**
     * Constructor.
     */
    public function __construct() {
        global $PAGE;

        parent::__construct('tool_encoded_summary');

        $this->set_attribute('class', 'generaltable boxaligncenter mt-2');

        $this->make_columns();

        $this->pageable(false);
        $this->sortable(true, 'totalsize', SORT_DESC);
        $this->collapsible(false);
        $this->is_downloadable(false);
        $this->define_baseurl($PAGE->url);

        $this->column_class('records', 'text-right');

        $this->setup();
        $this->load_data();
    }

    /**
     * Defines the columns for this table.
     *
     * @throws \coding_exception
     */
    public function make_columns(): void {
        $headers = [];
        $columns = $this->get_columns();
        foreach ($columns as $column) {
            $headers[] = get_string($column, 'tool_encoded');
        }

        $this->define_columns($columns);
        $this->define_headers($headers);
    }

    /**
     * returns the columns defined for the table.
     *
     * @return string[]
     */
    protected function get_columns(): array {
        $columns = self::COLUMNS;
        return $columns;
    }

    /**
     * Iterate over tables and columns looking for columns that have an associated format field.
     *
     * @throws \dml_exception
     */
    public function load_data(): void {
        global $DB;

        // Load previous results.
        $previousresults = $DB->get_records('tool_encoded_base64_tables', null, '', 'report_table, last_checked, duration');

        // Load summary of records.
        $sql = "SELECT report_table, COUNT(*) as count, SUM(encoded_size) as total_size, MAX(encoded_size) as max_size
                  FROM {tool_encoded_base64_records}
                 WHERE migrated <> 1
              GROUP BY report_table";
        $summary = $DB->get_records_sql($sql);

        // Load potential table column pairs. We can't rely on the previous results when the database has changed.
        // Cached fetch.
        $tables = $DB->get_tables();
        foreach ($tables as $table) {
            $potentialcols = self::get_editor_columns($table);

            // Add tables with potential columns to the report.
            if (!empty($potentialcols)) {
                $insummary = isset($summary[$table]);
                $previousresult = isset($previousresults[$table]);
                $summarydefault = $previousresult ? 0 : null;
                $this->tabledata[$table] = (object) [
                    'table' => $table,
                    'column' => implode(',', $potentialcols),
                    'records' => $insummary ? $summary[$table]->count : $summarydefault,
                    'maxsize' => $insummary ? $summary[$table]->max_size : $summarydefault,
                    'totalsize' => $insummary ? $summary[$table]->total_size : $summarydefault,
                    'lastchecked' => $previousresult ? $previousresults[$table]->last_checked : null,
                    'duration' => $previousresult ? $previousresults[$table]->duration : null,
                    'actions' => '',
                ];
            }
        }
        ksort($this->tabledata);
    }

    /**
     * Gets columns that contain user editable text.
     *
     * @param string $table
     * @return array column names
     */
    public static function get_editor_columns(string $table): array {
        global $DB;

        // Some editor columns don't have an exact match for the 'format' column and need to be hardcoded.
        $includecolumns = [
            'assignfeedback_comments' => [
                'commenttext' => 'commentformat',
            ],
            'assignsubmission_onlinetext' => [
                'onlinetext' => 'onlineformat',
            ],
        ];

        $editorcolumns = [];
        $tablecols = $DB->get_columns($table);
        foreach ($tablecols as $column) {
            // Only convert columns that are either text or long varchar.
            if ($column->meta_type == 'X' || ($column->meta_type == 'C' && $column->max_length > 255)) {
                // We only want fields that have an associated format col as they are editable by the user.
                if (array_key_exists($column->name . 'format', $tablecols) || isset($includecolumns[$table][$column->name])) {
                    $editorcolumns[] = $column->name;
                }
            }
        }
        return $editorcolumns;
    }

    /**
     * Display value for 'lastchecked' column.
     *
     * @param \stdClass $record
     * @return string
     */
    public function col_lastchecked(\stdClass $record): string {
        return isset($record->lastchecked) ? userdate($record->lastchecked) : '';
    }

    /**
     * Display value for 'duration' column.
     *
     * @param \stdClass $record
     * @return string
     */
    public function col_duration(\stdClass $record): string {
        return isset($record->duration) ? format_time(max($record->duration, 1)) : '';
    }

    /**
     * Display value for 'records' column.
     *
     * @param \stdClass $record
     * @return string
     */
    public function col_records(\stdClass $record): string {
        return $record->records ?? '';
    }

    /**
     * Display value for 'maxsize' column.
     *
     * @param \stdClass $record
     * @return string
     */
    public function col_maxsize(\stdClass $record): string {
        return isset($record->maxsize) ? display_size($record->maxsize) : '';
    }

    /**
     * Display value for 'totalsize' column.
     *
     * @param \stdClass $record
     * @return string
     */
    public function col_totalsize(\stdClass $record): string {
        return isset($record->totalsize) ? display_size($record->totalsize) : '';
    }

    /**
     * Display value for 'actions' column.
     *
     * @param \stdClass $record
     * @return string
     */
    public function col_actions(\stdClass $record): string {
        global $OUTPUT;

        $url = new \moodle_url('/admin/tool/encoded/generate.php', [
            'table' => $record->table,
            'columns' => $record->column,
            'sesskey' => sesskey(),
        ]);

        $generatebutton = new \single_button($url, get_string('generate', 'tool_encoded'), 'post', helper::get_button_type());
        $generatebutton->add_confirm_action(get_string('confirmgeneratetable', 'tool_encoded', $record->table));
        return $OUTPUT->render($generatebutton);
    }

    /**
     * Sets the data of the table.
     *
     * @return void
     */
    public function display(): void {
        $this->sort_data();
        $this->start_output();
        foreach ($this->tabledata as $record) {
            $classname = $record->records > 0 ? 'table-warning' : '';
            $this->add_data_keyed($this->format_row($record), $classname);
        }
        $this->finish_output();
    }

    /**
     * Sorts data based on the sorting params
     *
     * @return void
     */
    public function sort_data() {
        $sortcols = $this->get_sort_columns();
        usort($this->tabledata, function ($a, $b) use ($sortcols) {
            foreach ($sortcols as $col => $tdir) {
                $cmp = $a->$col <=> $b->$col;
                if ($cmp !== 0) {
                    return ($tdir === SORT_DESC) ? -$cmp : $cmp;
                }
            }
            return 0;
        });
    }

    /**
     * Gets page buttons for the generate table
     *
     * @return string
     */
    public function get_page_buttons(): string {
        global $OUTPUT;

        $buttons = '';

        // Add generate all button.
        $generateallbutton = new \single_button(
            new \moodle_url('/admin/tool/encoded/generate.php', ['table' => 'all', 'sesskey' => sesskey()]),
            get_string('queuealltables', 'tool_encoded', count($this->tabledata)),
            'post',
            helper::get_button_type()
        );
        $generateallbutton->add_confirm_action(get_string('confirmgenerate', 'tool_encoded'));
        $buttons .= $OUTPUT->render($generateallbutton);

        // Add link to report page.
        $buttons .= \html_writer::link(
            new \moodle_url('/admin/tool/encoded/index.php'),
            get_string('viewreport', 'tool_encoded'),
            ['class' => 'btn btn-secondary m-1']
        );

        return $buttons;
    }
}

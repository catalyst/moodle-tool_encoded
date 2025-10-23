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

namespace tool_encoded\check;

use core\check\check;
use core\check\result;
use tool_encoded\output\summary;

/**
 * Base64 data check
 *
 * @package    tool_encoded
 * @author     Benjamin Walker <benjaminwalker@catalyst-au.net>
 * @copyright  2025, Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class base64check extends check {
    /** @var int Threshold total size in bytes after which should warn about base64 data **/
    public const WARNTHRESHOLD = 10 * 1024 * 1024;

    /** @var int Threshold total size in bytes after which should error about base64 data **/
    public const ERRORTHRESHOLD = 100 * 1024 * 1024;

    /** @var \stdClass base64 record summary for a single table column pair */
    private $base64col;

    /**
     * Constructor
     *
     * @param \stdClass|null $base64col
     */
    public function __construct(\stdClass $base64col = null) {
        $this->base64col = $base64col;
    }

    /**
     * A link to check base64 data
     *
     * @return \action_link|null
     */
    public function get_action_link(): ?\action_link {
        $url = new \moodle_url('/admin/tool/encoded/index.php');
        return new \action_link($url, get_string('viewreport', 'tool_encoded'));
    }

    /**
     * Return result
     *
     * @return result
     */
    public function get_result(): result {
        global $DB;

        // Check logic for sub checks.
        if (isset($this->base64col)) {
            // Start at info and raise when hitting thresholds.
            $status = result::INFO;

            if ($this->base64col->total_size > self::WARNTHRESHOLD) {
                $status = result::WARNING;
            }

            // If it meets the higher threshold raise this to an error.
            if ($this->base64col->total_size > self::ERRORTHRESHOLD) {
                $status = result::ERROR;
            }

            return new result($status, $this->base64col->message, '');
        }

        // Check logic for main check.
        $count = count(self::get_base64_summary());
        if (empty($count)) {
            // No base64 data detected.
            $minsize = display_size(get_config('tool_encoded', 'size') * 1024);
            return new result(result::OK, get_string('checkbase64ok', 'tool_encoded', $minsize), '');
        }

        // If we have any base64 data load the summary table for details.
        $table = new summary();
        ob_start();
        $table->out(10000, false);
        $details = ob_get_clean();

        $data = [
            'columns' => $count,
            'records' => $table->get_total_count(),
            'size' => display_size($table->get_total_size()),
        ];
        return new result(result::INFO, get_string('checkbase64info', 'tool_encoded', $data), $details);
    }

    /**
     * Get the short check name
     *
     * @return string
     */
    public function get_name(): string {
        $name = parent::get_name();
        if (!isset($this->base64col)) {
            return $name;
        }
        return "$name {$this->base64col->report_table} {$this->base64col->report_column}";
    }

    /**
     * Get the check reference.
     * If this check is on a specific table, use the table and column name.
     *
     * @return string must be globally unique
     */
    public function get_ref(): string {
        $ref = parent::get_ref();
        if (!isset($this->base64col)) {
            return $ref;
        }
        // Format nicely to use as a query param.
        return "{$ref}_{$this->base64col->report_table}_{$this->base64col->report_column}";
    }

    /**
     * Gets a summary of all base64 data, grouped by table column pairs
     *
     * @return array base64 summary records
     */
    public static function get_base64_summary(): array {
        global $DB;

        static $base64summary = null;
        if (isset($base64summary)) {
            return $base64summary;
        }

        $sql = "SELECT MAX(id) AS id, report_table, report_column, COUNT(*) as count, SUM(encoded_size) as total_size
                  FROM {tool_encoded_base64_records}
                 WHERE migrated <> 1
              GROUP BY report_table, report_column";
        $base64summary = $DB->get_records_sql($sql);
        return $base64summary;
    }

    /**
     * Gets an array of all base64 warnings as checks.
     *
     * @return array of base64 warnings
     */
    public static function get_base64_warnings(): array {
        global $DB;

        $checks = [];
        $base64summary = self::get_base64_summary();

        // Create a new check for each column with base64-encoded data.
        foreach ($base64summary as $base64col) {
            $base64col->message = get_string('checkbase64column', 'tool_encoded', [
                'column' => $base64col->report_column,
                'table' => $base64col->report_table,
                'records' => $base64col->count,
                'size' => display_size($base64col->total_size),
            ]);
            $checks[] = new \tool_encoded\check\base64check($base64col);
        }
        return $checks;
    }
}

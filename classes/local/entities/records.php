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

namespace tool_encoded\local\entities;

use core_collator;
use lang_string;
use core_reportbuilder\local\helpers\format;
use core_reportbuilder\local\entities\base;
use core_reportbuilder\local\filters\autocomplete;
use core_reportbuilder\local\filters\boolean_select;
use core_reportbuilder\local\filters\number;
use core_reportbuilder\local\report\column;
use core_reportbuilder\local\report\filter;

/**
 * Entity class implementation
 *
 * @package   tool_encoded
 * @copyright 2023 Mathew May <mathew.solutions>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class records extends base {

    /**
     * Database tables that this entity uses and their default aliases
     *
     * @return array
     */
    protected function get_default_tables(): array {
        return [
            'tool_encoded_base64_records',
        ];
    }

    /**
     * Database tables that this entity uses and their default aliases
     *
     * Required for 4.1 compatibility
     *
     * @return array
     */
    protected function get_default_table_aliases(): array {
        return [
            'tool_encoded_base64_records' => 'tebr',
        ];
    }

    /**
     * The default title for this entity in the list of columns/conditions/filters in the report builder
     *
     * @return lang_string
     */
    protected function get_default_entity_title(): lang_string {
        return new lang_string('encoderentity', 'tool_encoded');
    }

    /**
     * Initialise the entity
     *
     * @return base
     */
    public function initialise(): base {
        $columns = $this->get_all_columns();
        foreach ($columns as $column) {
            $this->add_column($column);
        }

        // All the filters defined by the entity can also be used as conditions.
        $filters = $this->get_all_filters();
        foreach ($filters as $filter) {
            $this
                ->add_filter($filter)
                ->add_condition($filter);
        }

        return $this;
    }

    /**
     * Returns list of all available columns
     *
     * @return column[]
     */
    protected function get_all_columns(): array {
        $tablealias = $this->get_table_alias('tool_encoded_base64_records');

        // Table name column.
        $columns[] = (new column(
            'report_table',
            new lang_string('table', 'tool_encoded'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TEXT)
            ->add_field("{$tablealias}.report_table")
            ->set_is_sortable(true);

        // Table columns column.
        $columns[] = (new column(
            'report_columns',
            new lang_string('column', 'tool_encoded'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TEXT)
            ->add_field("{$tablealias}.report_columns")
            ->set_is_sortable(true);

        // Mimetype column.
        $columns[] = (new column(
            'mimetype',
            new lang_string('mime', 'tool_encoded'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TEXT)
            ->add_field("{$tablealias}.mimetype")
            ->set_is_sortable(true);

        // Encoded size column.
        $columns[] = (new column(
            'encoded_size',
            new lang_string('size'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_INTEGER)
            ->add_field("{$tablealias}.encoded_size")
            ->set_is_sortable(true)
            ->set_disabled_aggregation(['avg', 'count', 'countdistinct', 'max', 'min', 'sum'])
            ->add_callback(static function(int $value): string {
                // Return the bytes as kilobytes.
                return new lang_string('size', 'tool_encoded', number_format($value / 1024, 2));
            });

        // Migration status column.
        $columns[] = (new column(
            'migrated',
            new lang_string('migrated', 'tool_encoded'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_BOOLEAN)
            ->add_field("{$tablealias}.migrated")
            ->set_is_sortable(true)
            ->add_callback(static function(bool $value): string {
                return format::boolean_as_text($value);
            });

        // PID column.
        $columns[] = (new column(
            'pid',
            new lang_string('pid', 'admin'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_INTEGER)
            ->add_field("{$tablealias}.pid")
            ->set_is_sortable(true)
            ->set_disabled_aggregation(['avg', 'count', 'countdistinct', 'max', 'min', 'sum']);

        // The record ID in the original table.
        $columns[] = (new column(
            'native_id',
            new lang_string('recordid', 'tool_encoded'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_INTEGER)
            ->add_field("{$tablealias}.native_id")
            ->set_is_sortable(true)
            ->set_disabled_aggregation(['avg', 'count', 'countdistinct', 'max', 'min', 'sum']);

        $columns[] = (new column(
            'cmid',
            new lang_string('cmid', 'tool_encoded'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_INTEGER)
            ->add_field("{$tablealias}.cmid")
            ->set_is_sortable(true)
            ->set_disabled_aggregation(['avg', 'count', 'countdistinct', 'max', 'min', 'sum']);
        return $columns;
    }

    /**
     * Return list of all available filters
     *
     * @return filter[]
     */
    protected function get_all_filters(): array {
        $tablealias = $this->get_table_alias('tool_encoded_base64_records');

        $filters[] = (new filter(
            autocomplete::class,
            'report_table',
            new lang_string('table', 'tool_encoded'),
            $this->get_entity_name(),
            "{$tablealias}.report_table"
        ))
            ->add_joins($this->get_joins())
            ->set_options_callback(static function(): array {
                global $DB;
                $tables = $DB->get_fieldset_sql(
                    'SELECT DISTINCT report_table FROM {tool_encoded_base64_records} ORDER BY report_table ASC'
                );
                $options = [];
                foreach ($tables as $table) {
                    $options[$table] = $table;
                }
                core_collator::asort($options);
                return $options;
            });

        $filters[] = (new filter(
            autocomplete::class,
            'report_columns',
            new lang_string('column', 'tool_encoded'),
            $this->get_entity_name(),
            "{$tablealias}.report_columns"
        ))
            ->add_joins($this->get_joins())
            ->set_options_callback(static function(): array {
                global $DB;
                $cols = $DB->get_fieldset_sql(
                    'SELECT DISTINCT report_columns FROM {tool_encoded_base64_records} ORDER BY report_columns ASC'
                );
                $options = [];
                foreach ($cols as $col) {
                    $options[$col] = $col;
                }
                core_collator::asort($options);
                return $options;
            });

        $filters[] = (new filter(
            autocomplete::class,
            'mimetype',
            new lang_string('mime', 'tool_encoded'),
            $this->get_entity_name(),
            "{$tablealias}.mimetype"
        ))
            ->add_joins($this->get_joins())
            ->set_options_callback(static function(): array {
                global $DB;
                $mimes = $DB->get_fieldset_sql(
                    'SELECT DISTINCT mimetype FROM {tool_encoded_base64_records} ORDER BY mimetype ASC'
                );
                $options = [];
                foreach ($mimes as $mime) {
                    $options[$mime] = $mime;
                }
                core_collator::asort($options);
                return $options;
            });

        $filters[] = (new filter(
            number::class,
            'encoded_size',
            new lang_string('size'),
            $this->get_entity_name(),
            "{$tablealias}.encoded_size"
        ))
            ->add_joins($this->get_joins());

        $filters[] = (new filter(
            boolean_select::class,
            'migrated',
            new lang_string('migrated', 'tool_encoded'),
            $this->get_entity_name(),
            "{$tablealias}.migrated"
        ))
            ->add_joins($this->get_joins());

        $filters[] = (new filter(
            number::class,
            'pid',
            new lang_string('pid', 'admin'),
            $this->get_entity_name(),
            "{$tablealias}.pid"
        ))
            ->add_joins($this->get_joins());

        $filters[] = (new filter(
            number::class,
            'cmid',
            new lang_string('cmid', 'tool_encoded'),
            $this->get_entity_name(),
            "{$tablealias}.cmid"
        ))
            ->add_joins($this->get_joins());
        return $filters;
    }
}

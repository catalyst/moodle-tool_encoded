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

namespace tool_encoded\task;

use advanced_testcase;
use context_module;
use core_plugin_manager;
use dml_exception;
use stdClass;
use stored_file;

defined('MOODLE_INTERNAL') || die();

/**
 * Unit tests.
 *
 * @package   tool_encoded
 * @copyright 2024 Moxis
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \tool_encoded\task\migrate
 */
class migrate_test extends advanced_testcase {
    /**
     * @inheritDoc
     */
    protected function setUp(): void {
        $this->resetAfterTest();
        set_config(
            'size',
            0,
            'tool_encoded'
        );
    }

    /**
     * Test the migration of a data URL to a plugin file.
     *
     * @dataProvider migration_provider
     * @param string $table
     * @param string $component
     * @param array $columns
     * @return void
     * @throws \coding_exception
     * @throws dml_exception
     */
    public function test_migrate(
        $table,
        $component,
        array $columns
    ): void {
        global $DB;

        $this->setOutputCallback(function ($output) {
            // Ignore output
        });

        $generator = self::getDataGenerator();
        $creator = $generator->get_plugin_generator($component);

        $course = $generator->create_course();

        $properties = $columns;
        $properties['course'] = $properties['course'] ?? $course->id;

        $instance = $creator->create_instance($properties);
        $context = context_module::instance($instance->cmid);

        $this->generate_report_by_table($table, $columns);

        $data_urls = [];
        foreach ($columns as $column => $value) {
            $data_urls[$column] = $this->extract_data_url($value);
        }

        $record_id = $DB->get_field(
            'tool_encoded_base64_records',
            'id',
            [
                'native_id' => $instance->id
            ]
        );

        self::assertNotFalse(
            $record_id,
            "$table record with id {$instance->id} not found."
        );

        $task = new migrate();
        $task->set_custom_data([
            'recordid' => $record_id,
        ]);
        $task->execute();

        $actual = $this->get_instance_by_id($table, $instance->id);

        $plugin_manager = core_plugin_manager::instance();

        foreach ($columns as $column => $content) {
            self::assertStringContainsString(
                '@@PLUGINFILE@@',
                $actual->$column
            );

            if (!isset($data_urls[$column])) {
                continue;
            }

            foreach ($data_urls[$column] as $data) {
                $base64 = $data['base64'];
                self::assertStringNotContainsString(
                    $base64,
                    $actual->$column
                );

                $content_hash = $this->get_content_hash_from_base64($base64);
                $file = $this->get_file_by_content_hash($content_hash);

                $file_component = $file->get_component();

                $plugin = $plugin_manager->get_plugin_info($file_component);

                self::assertInstanceOf(
                    \core\plugininfo\base::class,
                    $plugin,
                    "{$file_component} not found."
                );

                self::assertEquals(
                    $file->get_contextid(),
                    $context->id,
                    'Context ID mismatch.'
                );
            }
        }
    }

    /**
     * Data provider for test_migrate.
     *
     * @return array[]
     */
    public function migration_provider(): array {
        $provider = [];

        $base64 = 'R0lGODdhAQABAPAAAP8AAAAAACwAAAAAAQABAAACAkQBADs=';
        $source = $this->get_data_url('image/gif', $base64);

        $provider['label intro.'] = [
            'label',
            'mod_label',
            'columns' => [
                'intro' => '<img alt="Test image" src="'. $source .'" />'
            ],
        ];

        return $provider;
    }

    /**
     * Generate a report by table.
     *
     * @param string $table
     * @param array<string, string> $columns
     * @return void
     */
    private function generate_report_by_table($table, $columns): void {
        $task = new generate_report();
        $task->set_custom_data([
            'table' => $table,
            'columns' => $this->get_columns_as_string($columns),
        ]);
        $task->execute();
    }

    /**
     * Extract data URLs from a string.
     *
     * @param string $content
     * @return array<string, array>
     */
    private function extract_data_url($content): array {
        $pattern = 'data\:(?<mimetype>.+);base64,(?<base64>[a-zA-Z0-9\+\/]+\={0,2})';
        $hits = preg_match_all("#$pattern#", $content, $matches);

        if (!$hits) {
            return [];
        }

        return array_map(function ($mimetype, $base64) {
            return [
                'mimetype' => $mimetype,
                'base64' => $base64
            ];
        }, $matches['mimetype'], $matches['base64']);
    }

    /**
     * Get an instance by its ID.
     *
     * @param string $table
     * @param int $id
     * @return stdClass
     * @throws dml_exception
     */
    private function get_instance_by_id($table, $id): stdClass {
        global $DB;
        return $DB->get_record($table, ['id' => $id], '*', MUST_EXIST);
    }

    /**
     * Get a data URL with base64.
     *
     * @param string $mime_type
     * @param string $base64
     * @return string
     */
    private function get_data_url($mime_type, $base64): string {
        return 'data:' . $mime_type . ';base64,' . $base64;
    }

    /**
     * Get the columns as string concatenation.
     *
     * @param array<string, string> $columns
     * @return string
     */
    private function get_columns_as_string(array $columns): string {
        return implode(',', array_keys($columns));
    }

    /**
     * Get the content hash from a base64 string.
     *
     * @param string $base64
     * @return string
     */
    private function get_content_hash_from_base64($base64): string {
        return sha1(base64_decode($base64));
    }

    /**
     * Get a file by its content hash.
     *
     * @param string $content_hash
     * @return stored_file
     * @throws dml_exception
     */
    private function get_file_by_content_hash($content_hash): stored_file {
        global $DB;
        $record = $DB->get_record(
            'files',
            ['contenthash' => $content_hash],
            '*',
            MUST_EXIST
        );
        return get_file_storage()->get_file_instance($record);
    }
}

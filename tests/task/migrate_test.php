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

/**
 * Unit tests.
 *
 * @package   tool_encoded
 * @copyright 2024 Moxis
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \tool_encoded\task\migrate
 */
final class migrate_test extends advanced_testcase {
    /**
     * Set up before each test.
     */
    protected function setUp(): void {
        parent::setUp();
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

        // Ignore output.
        ob_start();

        $generator = self::getDataGenerator();
        $creator = $generator->get_plugin_generator($component);

        $course = $generator->create_course();

        $properties = $columns;
        $properties['course'] = $properties['course'] ?? $course->id;

        $instance = $creator->create_instance($properties);
        $context = context_module::instance($instance->cmid);

        $this->generate_report_by_table($table, $columns);

        $dataurls = [];
        foreach ($columns as $column => $value) {
            $dataurls[$column] = $this->extract_data_url($value);
        }

        $recordid = $DB->get_field(
            'tool_encoded_base64_records',
            'id',
            [
                'native_id' => $instance->id,
            ]
        );

        self::assertNotFalse(
            $recordid,
            "$table record with id {$instance->id} not found."
        );

        $task = new migrate();
        $task->set_custom_data([
            'recordid' => $recordid,
        ]);
        $task->execute();

        $actual = $this->get_instance_by_id($table, $instance->id);

        $pluginmanager = core_plugin_manager::instance();

        foreach ($columns as $column => $content) {
            self::assertStringContainsString(
                '@@PLUGINFILE@@',
                $actual->$column
            );

            if (!isset($dataurls[$column])) {
                continue;
            }

            foreach ($dataurls[$column] as $data) {
                $base64 = $data['base64'];
                self::assertStringNotContainsString(
                    $base64,
                    $actual->$column
                );

                $contenthash = $this->get_content_hash_from_base64($base64);
                $file = $this->get_file_by_content_hash($contenthash);

                $filecomponent = $file->get_component();

                $plugin = $pluginmanager->get_plugin_info($filecomponent);

                self::assertInstanceOf(
                    \core\plugininfo\base::class,
                    $plugin,
                    "{$filecomponent} not found."
                );

                self::assertEquals(
                    $file->get_contextid(),
                    $context->id,
                    'Context ID mismatch.'
                );
            }
        }
        // Ignore output.
        ob_end_clean();
    }

    /**
     * Data provider for test_migrate.
     *
     * @return array[]
     */
    public static function migration_provider(): array {
        $provider = [];

        $base64 = 'R0lGODdhAQABAPAAAP8AAAAAACwAAAAAAQABAAACAkQBADs=';
        $source = self::get_data_url('image/gif', $base64);

        $provider['label intro.'] = [
            'label',
            'mod_label',
            'columns' => [
                'intro' => '<img alt="Test image" src="' . $source . '" />',
            ],
        ];

        return $provider;
    }

    /**
     * Test the find_base64_uris method.
     *
     * @dataProvider find_base64_uris_provider
     * @param string $input
     * @param array $expected
     */
    public function test_find_base64_uris(string $input, array $expected): void {
        $actual = migrate::find_base64_uris($input);
        self::assertEquals($expected, $actual);
    }

    /**
     * Data provider for test_find_base64_uris.
     *
     * @return array
     */
    public static function find_base64_uris_provider(): array {
        $png = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABAQAAAAA3bvkkAAAACklEQVR4AWNgAAAAAgABc3UBGAAA
                AABJRU5ErkJggg==';
        $pnguri = "data:image/png;base64,{$png}";
        $pngdecoded = base64_decode($png);

        $gif = 'R0lGODlhAQABAIABAP///wAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==';
        $gifuri = "data:image/gif;base64,{$gif}";
        $gifdecoded = base64_decode($gif);

        return [
            'empty string' => [
                'input' => '',
                'expected' => [],
            ],
            'no base64' => [
                'input' => '<img src="/path/to/image.jpg">',
                'expected' => [],
            ],
            'png single quotes' => [
                'input' => "<img src='{$pnguri}'>",
                'expected' => [
                    (object) ['uri' => $pnguri, 'decoded' => $pngdecoded],
                ],
            ],
            'png double quotes' => [
                'input' => "<img src=\"{$pnguri}\">",
                'expected' => [
                    (object) ['uri' => $pnguri, 'decoded' => $pngdecoded],
                ],
            ],
            'png and gif single quotes' => [
                'input' => "<img src='{$pnguri}'><img src='{$gifuri}'>",
                'expected' => [
                    (object) ['uri' => $pnguri, 'decoded' => $pngdecoded],
                    (object) ['uri' => $gifuri, 'decoded' => $gifdecoded],
                ],
            ],
            'png and gif double quotes' => [
                'input' => "<img src=\"{$pnguri}\"><img src=\"{$gifuri}\">",
                'expected' => [
                    (object) ['uri' => $pnguri, 'decoded' => $pngdecoded],
                    (object) ['uri' => $gifuri, 'decoded' => $gifdecoded],
                ],
            ],
            'png and gif mixed quotes' => [
                'input' => "<img src='{$pnguri}'><img src=\"{$gifuri}\">",
                'expected' => [
                    (object) ['uri' => $pnguri, 'decoded' => $pngdecoded],
                    (object) ['uri' => $gifuri, 'decoded' => $gifdecoded],
                ],
            ],
            'png with caps' => [
                'input' => "<IMG SRC=\"{$pnguri}\">",
                'expected' => [
                    (object) ['uri' => $pnguri, 'decoded' => $pngdecoded],
                ],
            ],
            'complex mixed case and whitespace' => [
                'input' => "<IMG SRC= '{$pnguri}'><img src =\" {$gifuri} \"><img src = '/path/to/image.jpg'>",
                'expected' => [
                    (object) ['uri' => $pnguri, 'decoded' => $pngdecoded],
                    (object) ['uri' => $gifuri, 'decoded' => $gifdecoded],
                ],
            ],
            'png missing closing single quote' => [
                'input' => "<img src='{$pnguri}",
                'expected' => [],
            ],
            'png missing closing double quote' => [
                'input' => "<img src=\"{$pnguri}",
                'expected' => [],
            ],
            'png mismatched quotes' => [
                'input' => "<img src='{$pnguri}\">",
                'expected' => [],
            ],
            'gif mismatched quotes' => [
                'input' => "<img src=\"{$gifuri}'>",
                'expected' => [],
            ],
            'png url no quotes' => [
                'input' => "<style>body { background-image: url({$pnguri}); }</style>",
                'expected' => [
                    (object) ['uri' => $pnguri, 'decoded' => $pngdecoded],
                ],
            ],
            'png url single quotes' => [
                'input' => "<style>body { background-image: url('{$pnguri}'); }</style>",
                'expected' => [
                    (object) ['uri' => $pnguri, 'decoded' => $pngdecoded],
                ],
            ],
            'png url double quotes' => [
                'input' => "<style>body { background-image: url(\"{$pnguri}\"); }</style>",
                'expected' => [
                    (object) ['uri' => $pnguri, 'decoded' => $pngdecoded],
                ],
            ],
            'png url whitespace' => [
                'input' => "<style>body { background-image: url( {$pnguri} ); }</style>",
                'expected' => [
                    (object) ['uri' => $pnguri, 'decoded' => $pngdecoded],
                ],
            ],
            'gif url whitespace' => [
                'input' => "<style>body { background-image: url( ' {$gifuri} ' ); }</style>",
                'expected' => [
                    (object) ['uri' => $gifuri, 'decoded' => $gifdecoded],
                ],
            ],
            'png src and gif url' => [
                'input' => "<img src='{$pnguri}'><style>body { background-image: url('{$gifuri}'); }</style>",
                'expected' => [
                    (object) ['uri' => $pnguri, 'decoded' => $pngdecoded],
                    (object) ['uri' => $gifuri, 'decoded' => $gifdecoded],
                ],
            ],
        ];
    }

    /**
     * Generate a report by table.
     *
     * @param string $table
     * @param array $columns
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
                'base64' => $base64,
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
     * @param string $mimetype
     * @param string $base64
     * @return string
     */
    private static function get_data_url($mimetype, $base64): string {
        return 'data:' . $mimetype . ';base64,' . $base64;
    }

    /**
     * Get the columns as string concatenation.
     *
     * @param array $columns
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
     * @param string $contenthash
     * @return stored_file
     * @throws dml_exception
     */
    private function get_file_by_content_hash($contenthash): stored_file {
        global $DB;
        $record = $DB->get_record(
            'files',
            ['contenthash' => $contenthash],
            '*',
            MUST_EXIST
        );
        return get_file_storage()->get_file_instance($record);
    }
}

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
 * Admin tool base64encode settings.
 *
 * @package   tool_encoded
 * @copyright 2023 Mathew May <mathew.solutions>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $ADMIN->add(
        'tools',
        new admin_category('encodedfolder', get_string('pluginname', 'tool_encoded'))
    );

    $ADMIN->add(
        'encodedfolder',
        new admin_externalpage(
            'tool_encoded_generate',
            get_string('generatereport', 'tool_encoded'),
            new moodle_url('/admin/tool/encoded/generate.php'),
        )
    );

    $ADMIN->add(
        'encodedfolder',
        new admin_externalpage(
            'tool_encoded_report',
            get_string('displayreport', 'tool_encoded'),
            new moodle_url('/admin/tool/encoded/index.php'),
        )
    );

    $settings = new admin_settingpage('tool_encoded', get_string('settings'), 'moodle/site:configview');

    $settings->add(new admin_setting_configtext(
        'tool_encoded/size',
        new lang_string('sizesetting', 'tool_encoded'),
        new lang_string('sizesettingdesc', 'tool_encoded'),
        10,
        PARAM_INT,
    ));

    $ADMIN->add('encodedfolder', $settings);
}

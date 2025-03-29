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
 * @author  Vasileios Sotiras <v.sotiras@qmul.ac.uk>
 * @license GNU GPL v3 / 2022
 */
defined('MOODLE_INTERNAL') || die();
/** @var bool $hassiteconfig */
if ($hassiteconfig) {
    $settings = new admin_settingpage('local_broadcaster', new lang_string('pluginname', 'local_broadcaster'));

    /** @var admin_root $ADMIN */
    try {
        $ADMIN->add('localplugins', $settings);
    } catch (coding_exception $e) {
        debugging($e->getMessage(), DEBUG_DEVELOPER);
    }

    $settings->add(new admin_setting_configtext('local_broadcaster/daystokeepexpired',
        new lang_string('settingdaystokeepexpired', 'local_broadcaster'),
        new lang_string('settingdaystokeepexpireddesc', 'local_broadcaster'), 180, PARAM_INT));

}

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

require_once(__DIR__ . '/../../config.php');
global $OUTPUT, $PAGE, $USER, $CFG;
/** @noinspection PhpIncludeInspection */
require_once($CFG->libdir . '/accesslib.php');
require_once('./locallib.php');
try {
    require_login();
    // Load all user roles in the $USER->ra array.
    load_all_capabilities();
    $pluginname = get_string('pluginname', 'local_broadcaster');
    $settings = get_string('settings', 'local_broadcaster');
    $PAGE->set_url('/local/broadcaster/index.php');
    $PAGE->set_context(context_system::instance());
    $pagetitle = "$pluginname $settings";
    $PAGE->set_title("$pagetitle");

    // Check for site admin capability.
    $sitepermission = has_capability('moodle/site:config', context_system::instance());
    // Check if the user has any category level management.
    if (!$sitepermission) {
        $categorypermission = local_broadcaster_has_category_manage_capability();
    } else {
        $categorypermission = $sitepermission;
    }
    $nopermission = !($sitepermission || $categorypermission);
    $content = (object) [
            'sitepermission' => $sitepermission,
            'categorypermission' => $categorypermission,
            'nopermission' => $nopermission,
            'wwwroot' => $CFG->wwwroot . '/' . $CFG->admin,
    ];

    echo $OUTPUT->header();
    echo $OUTPUT->render_from_template('local_broadcaster/settings', $content);

    echo $OUTPUT->footer();
} catch (coding_exception | require_login_exception | moodle_exception $e) {
    debugging($e->getMessage() . ' ' . $e->getTraceAsString());
}

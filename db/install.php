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
 * Install utility.
 *
 * @package    local
 * @subpackage broadcaster
 * @copyright  2022 Vasileios Sotiras <v.sotiras@qmul.ac.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_broadcaster\event\broadcasterpage_created;
use local_broadcaster\event\broadcasterpagetype_created;


/**
 * Set basic plugin settings.
 *
 * @throws dml_exception
 * @throws coding_exception
 * @noinspection PhpUnused
 */
function xmldb_local_broadcaster_install()
{
    $main = 'local_broadcaster';
    set_config('enabled', 1, $main);
    global $DB;
    $types = 'local_broadcaster_pagetype';
    $records = $DB->get_records($types);

    // Check that we have at least one page-type defined.
    if (empty($records)) {
        $records = [
            [
                'userid' => 0,
                'type' => 'My Page',
                'urlpattern' => '/my/',
                "active" => 1
            ],
            [
                'userid' => 0,
                'type' => 'Site Home',
                'urlpattern' => '/?redirect=0',
                'active' => 1
            ],
            [
                'userid' => 0,
                'type' => 'Course View',
                'urlpattern' => '/course/view',
                'active' => 1
            ],
        ];
        $modules = $DB->get_records('modules');
        foreach ($modules as $module) {
            $records[] = [
                'userid' => 0,
                'type' => ucfirst($module->name) . ' Page View',
                'urlpattern' => '/mod/' . str_replace(' ', '_', $module->name) . '/view',
                'active' => $module->visible
            ];
        }
        foreach ($records as $record) {
            $objid = $DB->insert_record($types, $record);
            $event = broadcasterpagetype_created::create([
                'objectid' => $objid,
            ]);
            $event->trigger();
        }

        // Now create some sample content records.
        $contents = [];
        $counter = 1;
        foreach ($records as $record) {
            $contents[] =
                [
                    'userid' => 0,
                    'pagetypeid' => $counter++,
                    'timebegin' => time(),
                    'timeend' => time() + 86400,
                    'active' => 0,
                    'categoryid' => 0,
                    'cohortid' => 0,
                    'header' => 1,
                    'timecreated' => time(),
                    'timemodified' => 0,
                    'loggedin' => 2,
                    'identifier' => $record['type'],
                    'title' => $record['type'],
                    'content' => '<p class="alert alert-info">This is a ' . $record['type'] .
                        ' additional contents Sample</p>',
                ];

        }

        foreach ($contents as $record) {
            $objid = $DB->insert_record($main, $record);
            $event = broadcasterpage_created::create([
                'objectid' => $objid,
            ]);
            $event->trigger();
        }
    }
}

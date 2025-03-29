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

/*
 * Created by PhpStorm.
 * User: vasileios
 * Date: 14/4/2022
 * Time: 15:40
 *
 *
 * File         local/broadcaster/db/tasks.php
 *
 * Purpose      Define local related cron/scheduler subsystem task to run once a day
 *
 * Input        N/A
 *
 * Output       N/A
 *
 * Notes        This file is used to remove old Broadcaster Pages not in use for some time
 *              as defined in the plugin settings.
 *
 *
 */

// Use this namespace also in the ./classes/task/classname.php .
namespace local_broadcaster\task;
defined('MOODLE_INTERNAL') || die;

$tasks = [
    [
        'classname' => 'local_broadcaster\task\delete_pages',
        'blocking' => 0,
        'minute' => '0',
        'hour' => '1',
        'day' => '*',
        'dayofweek' => '*',
        'month' => '*'
    ],
];

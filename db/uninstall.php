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
 * uninstall script for local_broadcaster
 * This will be executed when the plugin is uninstalled, before dropping its DB schema
 *
 * @package      local
 * @subpackage   broadcaster
 * @author       Vasileios Sotiras <v.sotiras@qmul.ac.uk>
 * @license      http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__) . '/../../../config.php');
defined('MOODLE_INTERNAL') || die();
require_login();

/** @noinspection PhpUnused */
function xmldb_local_broadcaster_uninstall(): bool
{
    global $DB;

    $dbman = $DB->get_manager();

    $xmlds = $dbman->get_install_xml_schema();
    $xmlds->deleteTable('local_broadcaster');
    $xmlds->deleteTable('local_broadcaster_main_log');
    $xmlds->deleteTable('local_broadcaster_pagetype');
    $xmlds->deleteTable('local_broadcaster_types_log');

    return true;
}

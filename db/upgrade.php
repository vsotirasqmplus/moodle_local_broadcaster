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

defined('MOODLE_INTERNAL') || die();

/**
 * Local Broadcaster upgrade.
 *
 * @package    local_broadcaster
 * @copyright  2022 Vasileios Sotiras
 * @author     Vasileios Sotiras <ptolemy.sotir@googlemail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
function xmldb_local_broadcaster_upgrade($oldversion)
{
    global $DB;
    $dbman = $DB->get_manager();


    if ($oldversion < 2022082600) {
        $tablename = 'local_broadcaster';
        // Define field buttontype to be added to local_broadcaster.
        $table = new xmldb_table($tablename);
        $field = new xmldb_field('buttontype', XMLDB_TYPE_CHAR, '30', null, XMLDB_NOTNULL, null, 'btn-primary', 'identifier');

        // Conditionally launch add field buttontype.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field buttonsize to be added to local_broadcaster.
        $field = new xmldb_field('buttonsize', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, 'btn', 'buttontype');

        // Conditionally launch add field buttonsize.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Update the old records with the default values for these two fields.
        $sql = 'SELECT * FROM {' . $tablename . '} WHERE buttontype IS NULL ';
        $records = $DB->get_recordset_sql($sql);
        foreach ($records as $record) {
            $record->buttontype = 'btn-primary';
            $record->buttonsize = 'btn';
            $DB->update_record($tablename, $record);
        }
        $records->close();

        // Broadcaster savepoint reached.
        upgrade_plugin_savepoint(true, 2022082600, 'local', 'broadcaster');
    }
    return true;
}
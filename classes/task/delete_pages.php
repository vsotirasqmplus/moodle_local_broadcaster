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

namespace local_broadcaster\task;

use coding_exception;
use core\task\scheduled_task;
use dml_exception;
use lang_string;
use local_broadcaster\event\broadcasterpage_deleted;

class delete_pages extends scheduled_task
{

    public function get_name(): string
    {
        return (new lang_string('delete_pages', 'local_broadcaster'))->__toString();
    }

    public function execute()
    {
        global $DB;
        try {
            $keepdays = get_config('local_broadcaster', 'daystokeepexpired');
        } catch (dml_exception $e) {
            $keepdays = 180;
        }
        // Now minus the time to wait for.
        $cuttime = (time() - (86400 * $keepdays));
        try {

            $records = $DB->get_records_sql('SELECT id FROM {local_broadcaster} WHERE timeend < ' . $cuttime);
        } catch (dml_exception $e) {
            debugging($e->getMessage() . '\n<br/>' . $e->getTraceAsString(), DEBUG_DEVELOPER);
            $records = null;
        }
        if ($records) {
            foreach ($records as $id => $record) {
                try {
                    // Delete the record.
                    $DB->delete_records('local_broadcaster', ['id' => $id]);
                    // Register the action.
                    $event = broadcasterpage_deleted::create([
                        'objectid' => $record->id,
                    ]);
                    $event->trigger();
                } catch (coding_exception|dml_exception $e) {
                    debugging($e->getMessage() . '\n<br/>' . $e->getTraceAsString(), DEBUG_DEVELOPER);
                }
            }
        }
    }
}

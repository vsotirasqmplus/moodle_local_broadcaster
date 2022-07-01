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

namespace local_broadcaster\event;
defined('MOODLE_INTERNAL') || die();

use context_system;
use core\event\base;
use dml_exception;

require_once(dirname(__FILE__) . '/../../locallib.php');

class broadcasterpagetype_updated extends base {

    /**
     * @inheritDoc
     * @throws dml_exception
     */
    protected function init() {
        $this->context = context_system::instance();
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->data['objecttable'] = 'local_broadcaster_pagetype';
    }

    public static function get_name(): string {
        return local_broadcaster_get_string('eventbroadcasterpagetypeupdated', 'local_broadcaster');
    }

    public function get_description(): string {
        return "The user with id '$this->userid' updated the Broadcaster Page Type with id '$this->objectid'.";
    }

}

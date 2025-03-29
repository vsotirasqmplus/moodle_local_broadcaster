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
namespace local_broadcaster;

require_once(__DIR__ . '/../../../config.php');
require_login();
global $CFG;
require_once($CFG->libdir . '/formslib.php');

use cache;
use coding_exception;
use dml_exception;
use local_broadcaster\event\broadcasterpagetype_created;
use local_broadcaster\event\broadcasterpagetype_deleted;
use local_broadcaster\event\broadcasterpagetype_updated;
use moodleform;

class EditTypes extends moodleform
{

    public $form;
    protected $table = 'local_broadcaster_pagetype';
    protected $log = 'local_broadcaster_types_log';
    protected $main = 'local_broadcaster';

    /**
     * @throws dml_exception
     * @throws coding_exception
     */
    public function save_data(object $data)
    {
        // Save data here.
        global $DB, $USER;
        if (!$data->id) {
            $recordid = $DB->insert_record($this->table, $data);
            $data->id = $recordid;
            $data->action = 'create';
            $event = broadcasterpagetype_created::create([
                'objectid' => $recordid,
            ]);
            $event->trigger();
        } else if ($data->action === 'edit') {
            $DB->update_record($this->table, $data);
            $recordid = $data->id;
            $event = broadcasterpagetype_updated::create([
                'objectid' => $recordid,
            ]);
            $event->trigger();
        } else if ($data->action === 'delete') {
            $DB->delete_records($this->table, ['id' => $data->id]);
            $recordid = $data->id;
            $event = broadcasterpagetype_deleted::create([
                'objectid' => $recordid,
            ]);
            $event->trigger();
        }
        // Update the broadcaster types cache.
        $cachetypes = cache::make($this->table, 'broadcastertypes');
        $cachetypes->set('broadcastertypes', $DB->get_records($this->table, ['active' => 1], 'id', 'id, urlpattern'));

        // Save local log entry.
        $logger = [
            'userid' => $USER->id,
            'pagetypeid' => $data->id,
            'timecreated' => time(),
            'oldurlpattern' => "$USER->username,$data->action,$data->id,$data->urlpattern,$data->type",
        ];
        $DB->insert_record($this->log, $logger);
    }

    /**
     * @return void
     * @throws dml_exception
     */
    protected function definition()
    {
        global $USER;
        $myform = &$this->_form;
        $myform->addElement('header', 'typessettingsheader', $this->get_string('edittypestitle', $this->main));
        $myform->addElement('text', 'id', $this->get_string('recordid', $this->main),
            ['readonly' => 'readonly', 'size' => 10, 'maxlength' => 10]);
        $myform->setType('id', PARAM_INT);
        $myform->addElement('hidden', 'userid', 'userid');
        $myform->setDefault('userid', $USER->id);
        $myform->setType('userid', PARAM_INT);
        $myform->addElement('text', 'type', $this->get_string('typename', $this->main), '', ['size' => 20, 'maxlength' => 60]);
        $myform->addRule('type', $this->get_string('required'), 'required');
        $myform->setType('type', PARAM_NOTAGS);
        $urls = $this->getTypes();
        $myform->addElement('select', 'urlpattern', $this->get_string('pagetypeid', $this->main), $urls);
        $myform->setType('urlpattern', PARAM_TEXT);
        $myform->addElement('selectyesno', 'active', $this->get_string('active', $this->main));
        $myform->setType('active', PARAM_INT);
        $myform->setDefault('active', 1);
        $this->add_action_buttons();
        $this->form = $myform;
    }

    /**
     * @throws dml_exception
     */
    private function gettypes(): array
    {
        global $DB;
        $mods = $DB->get_records('modules');
        $pagepatterns = [
            '/?redirect=0' => $this->get_string('sitehome', $this->main),
            '/my/' => $this->get_string('mypage', $this->main),
            '/course/view' => $this->get_string('course') . ' ' . $this->get_string('view', $this->main)
        ];
        foreach ($mods as $mod) {
            $pagepatterns['/mod/' . $mod->name . '/view'] =
                $mod->name . ' ' . $this->get_string('module', $this->main) . ' ' . $this->get_string('view', $this->main);
        }
        return $pagepatterns;
    }

    /**
     * @param $data
     * @param $files
     * @return array
     * @throws dml_exception
     */
    public function validation($data, $files): array
    {
        global $DB;
        $errors = parent::validation($data, $files);
        if ($data) {
            $type = $data['type'];
            $pattern = $data['urlpattern'];
            $exists = $DB->get_record($this->table, ['type' => $type, 'urlpattern' => $pattern], '*',
                IGNORE_MULTIPLE);
            if ($exists && ((int)$exists->id !== (int)$data['id'])) {
                $err = (object)['type' => $type, 'pattern' => $pattern];
                $errors['type'] = $this->get_string('typeerror', $this->main, $err);
            }
        }
        return $errors;
    }

    private function get_string($identifier, $plugin = null, $params = null): string
    {
        try {
            $identifier = get_string($identifier, $plugin, $params);
        } catch (coding_exception $e) {
            debugging($e->getMessage() . ' ' . $e->getTraceAsString(), DEBUG_DEVELOPER);
        }
        return $identifier;
    }
}

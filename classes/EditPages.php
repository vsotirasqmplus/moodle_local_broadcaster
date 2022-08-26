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
defined('MOODLE_INTERNAL') || die();
require_once(__DIR__ . '/../../../config.php');
require_login();
global $CFG;
require_once($CFG->libdir . '/formslib.php');

use cache;
use coding_exception;
use dml_exception;
use local_broadcaster\event\broadcasterpage_created;
use local_broadcaster\event\broadcasterpage_deleted;
use local_broadcaster\event\broadcasterpage_updated;
use moodleform;

/**
 * Edit Pages class
 */
class EditPages extends moodleform
{

    /**
     * @var
     */
    public $form;
    /**
     * @var string
     */
    protected $table = 'local_broadcaster';
    /**
     * @var string
     */
    protected $log = 'local_broadcaster_main_log';
    /**
     * @var string
     */
    protected $typestable = 'local_broadcaster_pagetype';

    /**
     * @throws dml_exception
     * @throws coding_exception
     */
    public function save_data(object $data)
    {
        // Save data here.
        global $DB, $USER;
        if (!$data->id) {
            $data->content = $data->content['text'];
            $recordid = $DB->insert_record($this->table, $data);
            $data->id = $recordid;
            $data->action = 'create';
            $event = broadcasterpage_created::create([
                'objectid' => $recordid,
            ]);
            $event->trigger();
        } else if ($data->action === 'edit') {
            $data->timemodified = time();
            $data->content = $data->content['text'];
            $DB->update_record($this->table, $data);
            $recordid = $data->id;
            $event = broadcasterpage_updated::create([
                'objectid' => $recordid,
            ]);
            $event->trigger();
        } else if ($data->action === 'delete') {
            $data->timemodified = time();
            $DB->delete_records($this->table, ['id' => $data->id]);
            $recordid = $data->id;
            $event = broadcasterpage_deleted::create([
                'objectid' => $recordid,
            ]);
            $event->trigger();

        }
        // Update the broadcaster pages cache.
        $cachepages = cache::make($this->table, 'broadcasterpages');
        $cachepages->set('broadcasterpages', $DB->get_records($this->table));

        // Save local log entry.
        $content = $data->content['text'] ?? $data->content;
        $cohortid = $data->cohortid ?? 0;
        $categoryid = $data->categoryid ?? 0;
        $roleid = $data->roleid ?? 0;
        $mylog = [
            'userid' => $USER->id,
            'broadcastid' => $data->id,
            'timecreated' => time(),
            'oldcontents' => "$USER->username,$data->action,$data->id,$data->active,$data->header," .
                "$data->timebegin,$data->timeend,$categoryid,$roleid,$cohortid,$content",
        ];
        $DB->insert_record($this->log, $mylog);
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
            $identifier = $data['identifier'];
            $pagetypeid = $data['pagetypeid'];
            $exists = $DB->get_record($this->table, ['identifier' => $identifier], '*', IGNORE_MULTIPLE);
            if ($exists && ((int)$exists->id !== (int)$data['id'])) {
                $err = (object)['identifier' => $identifier, 'pagetypeid' => $pagetypeid];
                $errors['identifier'] = $this->get_string('pageerror', $this->table, $err);
            }
        }
        return $errors;
    }

    /**
     * @return void
     * @throws dml_exception
     */
    protected function definition()
    {
        global $USER;

        $myform = &$this->_form;
        $myform->addElement('header', 'pagessettingsheader',
            $this->get_string('pluginname', $this->table) . $this->get_string('pages', $this->table));
        $myform->setExpanded('pagessettingsheader', false);
        $myform->addElement('text', 'id', $this->get_string('recordid', $this->table),
            ['readonly' => 'readonly', 'size' => 10, 'maxlength' => 10]);
        $myform->setType('id', PARAM_INT);
        $myform->addElement('hidden', 'userid', 'userid');
        $myform->setDefault('userid', $USER->id);
        $myform->setType('userid', PARAM_INT);
        $pagetypes = $this->getActiveTypes();
        $myform->addElement('select', 'pagetypeid', $this->get_string('pagetypeid', $this->table), $pagetypes);
        $myform->setType('pagetypeid', PARAM_INT);
        $myform->addElement('text', 'identifier', $this->get_string('pageidentifier', $this->table),
            array('size' => 60, 'maxlength' => 60));
        $myform->setType('identifier', PARAM_NOTAGS);
        $myform->addRule('identifier', $this->get_string('required'), 'required');
        $myform->addElement('hidden', 'timecreated', 'timecreated');
        $myform->setDefault('timecreated', time());
        $myform->setType('timecreated', PARAM_INT);
        $myform->addElement('hidden', 'timemodified', 'timemodified');
        $myform->setDefault('timemodified', time());
        $myform->setType('timemodified', PARAM_INT);
        $myform->addElement('date_time_selector', 'timebegin', $this->get_string('timebegin', $this->table), []);
        $myform->setDefault('timebegin', time());
        $myform->addElement('date_time_selector', 'timeend', $this->get_string('timeend', $this->table), []);
        $myform->setDefault('timeend', time() + 3600 * 24);
        $myform->addElement('selectyesno', 'active', $this->get_string('active', $this->table));
        $myform->setType('active', PARAM_INT);
        $myform->setDefault('active', 1);
        $myform->addElement('select', 'header', $this->get_string('position', $this->table),
            [0 => $this->get_string('footer', $this->table), 1 => $this->get_string('header', $this->table)]);
        $myform->setDefault('header', 0);
        $myform->addElement('select', 'loggedin', $this->get_string('loggedin', $this->table) . $this->get_string('qm', $this->table),
            [
                0 => $this->get_string('notloggedin', $this->table),
                1 => $this->get_string('guest'),
                2 => $this->get_string('loggedin', $this->table)
            ]);
        $myform->setDefault('loggedin', 2);
        $roles = $this->getRoles();
        $myform->addElement('select', 'roleid', $this->get_string('roleid', $this->table), $roles, ['size' => 1, 'width' => '80%']);
        $records = 0;
        $offset = 0;
        $categories = $this->getCategories($records, $offset);
        $myform->addElement('select', 'categoryid',
            $this->get_string('categoryid', $this->table) . $this->get_string('qm', $this->table),
            $categories, ['size' => 1, 'width' => '80%']);
        $myform->setType('categoryid', PARAM_INT);
        // Do not present this option for site home and my page.
        $myform->hideIf('categoryid', 'pagetypeid', 'eq', 1);
        $myform->hideIf('categoryid', 'pagetypeid', 'eq', 2);
        $cohorts = $this->getCohorts();
        $myform->addElement('select', 'cohortid', $this->get_string('cohortid', $this->table) . $this->get_string('qm', $this->table),
            $cohorts);
        $myform->setType('cohortid', PARAM_INT);
        // Do not present this option for site home and my page.
        $myform->hideIf('cohortid', 'pagetypeid', 'eq', 1);
        $myform->hideIf('cohortid', 'pagetypeid', 'eq', 2);
        $buttontypes = $this->getbuttontypes();
        $myform->addElement('select', 'buttontype', $this->get_string('buttontype', $this->table), $buttontypes);
        $buttonsizes = $this->getbuttonsizes();
        $myform->addElement('select', 'buttonsize', $this->get_string('buttonsize', $this->table), $buttonsizes);
        $myform->addElement('text', 'title', $this->get_string('title', $this->table), array('size' => 60, 'maxlength' => 60));
        $myform->setType('title', PARAM_NOTAGS);
        $myform->addRule('title', $this->get_string('required'), 'required');
        $myform->addElement('editor', 'content', $this->get_string('content', $this->table), null, []);
        $myform->addRule('content', $this->get_string('required'), 'required');
        $myform->setType('content', PARAM_RAW);
        $this->add_action_buttons();
        // Keep a reference to the form, so the enclosing context could add/edit some elements.
        $this->form = $myform;
    }

    /**
     * @return array
     */
    private function getbuttontypes(): array
    {
        $elements = [];
        $prefix = 'btn';
        $sep = '-';
        $types = ['primary', 'secondary', 'success', 'danger', 'warning', 'info', 'light', 'dark'];
        $forms = ['', 'outline'];
        foreach ($forms as $form) {
            foreach ($types as $type) {
                if ($form <> '') {
                    $elements[$prefix . $sep . $form . $sep . $type] = "$form $type";
                } else {
                    $elements[$prefix . $sep . $type] = $type;
                }
            }
        }
        return $elements;
    }

    /**
     * @param $identifier
     * @param $plugin
     * @param $params
     * @return string
     */
    private function get_string($identifier, $plugin = null, $params = null): string
    {
        try {
            $identifier = get_string($identifier, $plugin, $params);
        } catch (coding_exception $e) {
            debugging($e->getMessage() . ' ' . $e->getTraceAsString(), DEBUG_DEVELOPER);
        }
        return $identifier;
    }

    /**
     * @return array
     * @throws dml_exception
     */
    private function getactivetypes(): array
    {
        global $DB;
        return $DB->get_records_menu($this->typestable, ['active' => 1], 'type, id', 'id, type');
    }

    /**
     * @return string[]
     */
    private function getRoles(): array
    {
        $roles = [0 => 'All'];
        global $DB;
        try {
            $records = $DB->get_records('role', null, 'sortorder', 'id, shortname, name, archetype');
        } catch (dml_exception $e) {
            debugging($e->getMessage() . '\n' . $e->getTraceAsString(), DEBUG_DEVELOPER);
            $records = null;
        }
        if ($records) {
            foreach ($records as $key => $record) {
                $roles[$key] = "$record->name ($record->shortname:$record->archetype)";
            }
        }

        return $roles;
    }

    private function getcategories(int $records = 0, int $offset = 0): array
    {
        $cats = local_broadcaster_get_user_categories();
        $admin = (is_siteadmin() ? [0 => $this->get_string('none', $this->table)] : []);
        $entries = $admin + $cats;
        if ($records > 0 && $offset > 0) {
            $entries = array_slice($entries, $offset, $records, true);
        }
        return $entries;
    }

    /**
     * @return array
     * @throws dml_exception
     */
    private function getcohorts(): array
    {
        return local_broadcaster_get_user_cohorts();
    }

    private function getbuttonsizes(): array
    {
        return [
            'btn' => $this->get_string('buttonsizenormal', $this->table),
            'btn-sm' => $this->get_string('buttonsizesmall', $this->table),
            'btn-lg' => $this->get_string('buttonsizelarge', $this->table),
        ];
    }
}

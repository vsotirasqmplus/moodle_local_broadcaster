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
/** @noinspection PhpIncludeInspection */
require_once($CFG->libdir . '/formslib.php');

use cache;
use coding_exception;
use dml_exception;
use local_broadcaster\event\broadcasterpage_created;
use local_broadcaster\event\broadcasterpage_deleted;
use local_broadcaster\event\broadcasterpage_updated;
use moodleform;

/**
 *
 */
class edit_pages extends moodleform {

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
    public function save_data(object $data) {
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
        $cachepages->set('broadcasterpages', $DB->get_records($this->table, null, '', '*'));

        // Save local log entry.
        $content = $data->content['text'] ?? $data->content;
        $cohortid = $data->cohortid ?? 0;
        $categoryid = $data->categoryid ?? 0;
        $roleid = $data->roleid ?? 0;
        $log = [
                'userid' => $USER->id,
                'broadcastid' => $data->id,
                'timecreated' => time(),
                'oldcontents' => "$USER->username,$data->action,$data->id,$data->active,$data->header," .
                        "$data->timebegin,$data->timeend,$categoryid,$roleid,$cohortid,$content",
        ];
        $DB->insert_record($this->log, $log);
    }

    /**
     * @param $data
     * @param $files
     * @return array
     * @throws dml_exception
     */
    public function validation($data, $files): array {
        global $DB;
        $errors = parent::validation($data, $files);
        if ($data) {
            $identifier = $data['identifier'];
            $pagetypeid = $data['pagetypeid'];
            $exists = $DB->get_record($this->table, ['identifier' => $identifier], '*', IGNORE_MULTIPLE);
            if ($exists && ((int) $exists->id !== (int) $data['id'])) {
                $err = (object) ['identifier' => $identifier, 'pagetypeid' => $pagetypeid];
                $errors['identifier'] = $this->get_string('pageerror', $this->table, $err);
            }
        }
        return $errors;
    }

    /**
     * @return void
     * @throws dml_exception
     */
    protected function definition() {
        global $USER;

        $form = &$this->_form;
        $form->addElement('header', 'pagessettingsheader',
                $this->get_string('pluginname', $this->table) . $this->get_string('pages', $this->table));
        $form->setExpanded('pagessettingsheader', false);
        $form->addElement('text', 'id', $this->get_string('recordid', $this->table),
                ['readonly' => 'readonly', 'size' => 10, 'maxlength' => 10]);
        $form->setType('id', PARAM_INT);
        $form->addElement('hidden', 'userid', 'userid');
        $form->setDefault('userid', $USER->id);
        $form->setType('userid', PARAM_INT);
        $pagetypes = $this->getActiveTypes();
        $form->addElement('select', 'pagetypeid', $this->get_string('pagetypeid', $this->table), $pagetypes);
        $form->setType('pagetypeid', PARAM_INT);
        $form->addElement('text', 'identifier', $this->get_string('pageidentifier', $this->table),
                array('size' => 60, 'maxlength' => 60));
        $form->setType('identifier', PARAM_NOTAGS);
        $form->addRule('identifier', $this->get_string('required'), 'required');
        $form->addElement('hidden', 'timecreated', 'timecreated');
        $form->setDefault('timecreated', time());
        $form->setType('timecreated', PARAM_INT);
        $form->addElement('hidden', 'timemodified', 'timemodified');
        $form->setDefault('timemodified', time());
        $form->setType('timemodified', PARAM_INT);
        $form->addElement('date_time_selector', 'timebegin', $this->get_string('timebegin', $this->table), []);
        $form->setDefault('timebegin', time());
        $form->addElement('date_time_selector', 'timeend', $this->get_string('timeend', $this->table), []);
        $form->setDefault('timeend', time() + 3600 * 24);
        $form->addElement('selectyesno', 'active', $this->get_string('active', $this->table));
        $form->setType('active', PARAM_INT);
        $form->setDefault('active', 1);
        $form->addElement('select', 'header', $this->get_string('position', $this->table),
                [0 => $this->get_string('footer', $this->table), 1 => $this->get_string('header', $this->table)]);
        $form->setDefault('header', 0);
        $form->addElement('select', 'loggedin', $this->get_string('loggedin', $this->table) . $this->get_string('qm', $this->table),
                [
                        0 => $this->get_string('notloggedin', $this->table),
                        1 => $this->get_string('guest'),
                        2 => $this->get_string('loggedin', $this->table)
                ]);
        $form->setDefault('loggedin', 2);
        $roles = $this->getRoles();
        $form->addElement('select', 'roleid', $this->get_string('roleid', $this->table), $roles, ['size' => 1, 'width' => '80%']);
        $categories = $this->getCategories();
        $form->addElement('select', 'categoryid',
                $this->get_string('categoryid', $this->table) . $this->get_string('qm', $this->table),
                $categories, ['size' => 1, 'width' => '80%']);
        $form->setType('categoryid', PARAM_INT);
        // Do not present this option for site home and my page.
        $form->hideIf('categoryid', 'pagetypeid', 'eq', 1);
        $form->hideIf('categoryid', 'pagetypeid', 'eq', 2);
        $cohorts = $this->getCohorts();
        $form->addElement('select', 'cohortid', $this->get_string('cohortid', $this->table) . $this->get_string('qm', $this->table),
                $cohorts);
        $form->setType('cohortid', PARAM_INT);
        // Do not present this option for site home and my page.
        $form->hideIf('cohortid', 'pagetypeid', 'eq', 1);
        $form->hideIf('cohortid', 'pagetypeid', 'eq', 2);
        $form->addElement('text', 'title', $this->get_string('title', $this->table), array('size' => 60, 'maxlength' => 60));
        $form->setType('title', PARAM_NOTAGS);
        $form->addRule('title', $this->get_string('required'), 'required');
        $form->addElement('editor', 'content', $this->get_string('content', $this->table), null, []);
        $form->addRule('content', $this->get_string('required'), 'required');
        $form->setType('content', PARAM_RAW);
        $this->add_action_buttons();
        // Keep a reference to the form, so the enclosing context could add/edit some elements.
        $this->form = $form;
    }

    /**
     * @param $identifier
     * @param $plugin
     * @param $params
     * @return string
     */
    private function get_string($identifier, $plugin = null, $params = null): string {
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
    private function getactivetypes(): array {
        global $DB;
        return $DB->get_records_menu($this->typestable, ['active' => 1], 'type, id', 'id, type');
    }

    /** @noinspection PhpSameParameterValueInspection */

    /**
     * @return string[]
     */
    private function getRoles(): array {
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

    private function getcategories(int $records = 0, int $offset = 0): array {
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
    private function getcohorts(): array {
        return local_broadcaster_get_user_cohorts();
    }

}

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

use cache;
use coding_exception;
use context_system;
use dml_exception;
use moodle_exception;

defined('MOODLE_INTERNAL') || die();
$dir = __DIR__;
require_once($dir . '/../locallib.php');

/**
 *
 */
class broadcaster {

    /**
     * @var string
     */
    protected static $maintable = 'local_broadcaster';
    /**
     * @var string
     */
    protected static $typestable = 'local_broadcaster_pagetype';
    /**
     * @var int
     */
    protected static $checktypes = -1;
    /**
     * @var array
     */
    protected static $types = [];

    /**
     * @var array
     */
    protected static $pages = [];

    /**
     * @throws dml_exception
     */
    public function __construct() {
        self::update_cache();
    }

    /**
     * @throws dml_exception
     */
    public static function get_user_cohorts($user): array {
        global $DB;
        $cohorts = [];
        $cohortids = self::get_user_cohort_ids($user);
        if ($cohortids) {
            $cohorts = $DB->get_records_list('cohort', 'id', $cohortids, '', 'id, name, idnumber, visible');
            foreach ($cohorts as $key => $cohort) {
                if ($cohort->visible == 0) {
                    unset($cohorts[$key]);
                }
            }
        }
        return $cohorts;
    }

    /**
     * @throws dml_exception
     */
    private static function get_user_cohort_ids($user): array {
        global $DB;
        $ids = [];
        $records = $DB->get_records('cohort_members', ['userid' => $user->id], '', 'cohortid') ?? [];
        foreach ($records as $record) {
            $ids[] = (int) $record->cohortid;
        }
        return $ids;
    }

    /**
     * @throws dml_exception
     * @throws moodle_exception
     */
    public static function envelope_contents(string $function): string {
        global $OUTPUT;
        $output = '';
        $pages = self::get_additional_contents($function);
        foreach ($pages as $page) {
            $page->js = (self::moodle_version() <= 30900);
            $page->script = self::prepare_js($page);
            $output .= $OUTPUT->render_from_template('local_broadcaster/content', $page);
        }
        return $output;
    }

    /**
     * @throws dml_exception
     * @throws coding_exception
     * @noinspection PhpUndefinedFieldInspection
     */
    public static function get_additional_contents(string $function): array {
        global $PAGE, $COURSE, $USER, $SITE;
        $result = [];
        list($file, $dir, $moodlefile, $moodledir) = self::moodle_file_dir();
        $pages = self::get_pages_to_render($PAGE->url, $USER, $COURSE);
        if ($pages) {
            $from = local_broadcaster_get_string('from', self::$maintable);
            $to = local_broadcaster_get_string('to', self::$maintable);
            $valid = local_broadcaster_get_string('valid', self::$maintable);
            foreach ($pages as $recid => $page) {
                $result[] =
                        (object) ['key' => sha1($recid . $page->identifier),
                                'title' => $page->title,
                                'pagecontent' => $page->content,
                                'target' => ['append', 'prepend'][$page->header],
                                'function' => $function,
                                'file' => $file,
                                'dir' => $dir,
                                'moodlefile' => $moodlefile,
                                'moodledir' => $moodledir,
                                'useridnumber' => $USER->idnumber ?? 0,
                                'userid' => $USER->id ?? 0,
                                'courseidnumber' => $COURSE->idnumber ?? 0,
                                'courseid' => $COURSE->id ?? 0,
                                'sitename' => $SITE->fullname ?? '',
                                'url' => $PAGE->url,
                                'recordid' => $page->id,
                                'roleid' => $page->roleid,
                                'timebegin' => $page->timebegin,
                                'timeend' => $page->timeend,
                                'timebegindt' => gmdate("d M Y H:i:s", $page->timebegin),
                                'timeenddt' => gmdate("d M Y H:i:s", $page->timeend),
                                'from' => $from,
                                'to' => $to,
                                'valid' => $valid,
                                'admin' => has_capability('moodle/site:config', context_system::instance()),
                                'loggedin' => $page->loggedin,
                        ];
            }
        }
        return $result;
    }

    /**
     * @return array
     */
    private static function moodle_file_dir(): array {
        global $CFG;
        $file = __FILE__;
        $dir = __DIR__;
        $moodlefile = explode('_', str_replace('/', '_', str_replace($CFG->dirroot, '', $file)), 2)[1];
        $moodledir = explode('_', str_replace('/', '_', str_replace($CFG->dirroot, '', $dir)), 2)[1];
        return [$file, $dir, $moodlefile, $moodledir];
    }

    /**
     * This function should read database records and filter them by course category, date window, visibility, cohort membership.
     * Multiple records matching the criteria could be presented.
     *
     * @return array[]
     * @throws dml_exception
     * @throws coding_exception
     */
    private static function get_pages_to_render(string $url, object $user, object $course): array {
        global $DB, $PAGE;
        $records = [];
        // Cater for upgrading state empty cache.
        self::update_cache() ?? [];
        $typerecords = self::$types;
        // Get type IDs.
        // Cater for the query when there is no match.
        $typeids[0] = '0';
        $url = strtolower($url);
        foreach ($typerecords as $record) {
            if (strpos($url, strtolower($record->urlpattern)) !== false) {
                $typeids[] = $record->id;
            }
        }
        $context = $PAGE->context ?? null;
        $contextroles = $context ? get_user_roles($context) : [0];
        $roles = [0];
        if ($contextroles) {
            foreach ($contextroles as $contextrole) {
                $roles[] = (int) $contextrole->roleid;
            }
        }

        if (count($typeids) > 1) {
            // Get user cohorts.
            $cohorts = self::get_user_cohort_ids($user);
            // Add pages where the cohort ID is not set.
            $cohorts[0] = '0';
            list($incohortssql, $cohortsqlparams) = $DB->get_in_or_equal($cohorts, SQL_PARAMS_NAMED, 'cohortid');
            // Get course categories.
            $categories = self::get_course_category_ids($course);
            // Add pages where the category ID is not set.
            $categories[0] = '0';
            list($incatsssql, $catssqlparams) = $DB->get_in_or_equal($categories, SQL_PARAMS_NAMED, 'categoryid');
            // Check if there are any changes to the page types.

            $loggedin = local_broadcaster_login_state();
            $table = self::$maintable;
            list($intypeidssql, $sqlparams) = $DB->get_in_or_equal($typeids, SQL_PARAMS_NAMED, 'typeid');
            $sqlparams['active'] = 1;
            $sqlparams['loggedin'] = $loggedin;
            $sqlparams['time'] = time();
            // Get active records with the time frame including now and contains user cohorts set or none.
            // Course categories for this course or none.
            $records = $DB->get_records_sql(
                    "SELECT * FROM {{$table}}
WHERE active = :active
  AND :time BETWEEN timebegin AND timeend
  AND loggedin = :loggedin
  AND pagetypeid " . $intypeidssql . '
  AND cohortid ' . $incohortssql . '
  AND categoryid ' . $incatsssql
                    , $sqlparams + $cohortsqlparams + $catssqlparams);
        }
        if ($records && $roles) {
            foreach ($records as $key => $record) {
                if (!in_array($record->roleid, $roles)) {
                    // Check if the user has this role in this context
                    unset($records[$key]);
                }
            }
        }
        return $records;
    }

    /**
     * @throws dml_exception
     * @throws coding_exception
     */
    private static function update_cache(): void {
        global $DB;
        $sql = "SELECT (sum(id) * avg(id)) / count(id) hash FROM {local_broadcaster_pagetype} WHERE active = 1 GROUP BY active";
        $hash = $DB->get_record_sql($sql);
        if ($hash->hash <> self::$checktypes) {
            // Changes to the stored vs caches are identified.
            $cachetypes = cache::make(self::$maintable, 'broadcastertypes');
            $cachepages = cache::make(self::$maintable, 'broadcasterpages');

            $types = $DB->get_records(self::$typestable, ['active' => 1], 'id', 'id, urlpattern');
            $pages = $DB->get_records(self::$maintable, null, '', '*');

            $cachetypes->set('broadcastertypes', $types);
            $cachepages->set('broadcasterpages', $pages);
            // Store the cache.
            self::$types = $cachetypes->get('broadcastertypes');
            self::$pages = $cachepages->get('broadcasterpages');
            // Update the hash.
            self::$checktypes = $hash->hash;
        }

    }

    /**
     * @throws dml_exception
     */
    private static function get_types_log_count(): int {
        global $DB;
        $records = $DB->get_records('local_broadcaster_types_log', null, 'id desc', 'id', 0, 1);
        return $records->id ?? 0;
    }

    /**
     * @throws dml_exception
     */
    private static function get_course_category_ids($course): array {
        global $DB;
        $ids = [];
        $path = $DB->get_field('context', 'path', ['contextlevel' => CONTEXT_COURSE, 'instanceid' => $course->id]);
        $path = substr(str_replace('/', ',', $path), 1);
        $categories = $DB->get_records_sql("SELECT instanceid catid FROM {context} WHERE id IN ($path) AND contextlevel = " .
                CONTEXT_COURSECAT);
        if ($categories) {
            foreach ($categories as $category) {
                $ids[] = (int) $category->catid;
            }
        }
        return $ids;
    }

    /**
     * @return int
     */
    public static function moodle_version(): int {
        global $CFG;
        $release = explode('.', $CFG->release);
        return (int) ($release[0]) * 10000 + (int) ($release[1]) * 100;
    }

    /**
     * @param $page
     * @return string
     */
    private static function prepare_js($page): string {
        $template = <<<TEMPLATE
    <script class="broadcaster" id="{{function}}_{{recordid}}_script_id">
        f_{{function}}_{{recordid}}_wait_For_Element_To_Display("#region-main",
                function () {
                    const div = document.getElementById('{{function}}_{{recordid}}_section');
                    const target = document.getElementById('region-main');
                    div.parentElement.removeChild(div);
                    target.{{target}}(div);
                    div.style.visibility = 'visible';
                },
                1000,
                180000
        );

        function f_{{function}}_{{recordid}}_script() {
        let s = document.getElementById("{{function}}_{{recordid}}_script_id")
            let p = s.parentElement;
            p.removeChild(s);
        }

        function f_{{function}}_{{recordid}}_wait_For_Element_To_Display(selector, callback, checkFrequencyInMs, timeoutInMs) {
            const startTimeInMs = Date.now();
            (function loopSearch() {
                if (document.querySelector(selector) != null) {
                    callback();
                    f_{{function}}_{{recordid}}_script();
                } else {
                    setTimeout(function () {
                        if (timeoutInMs && Date.now() - startTimeInMs > timeoutInMs)
                            return;
                        loopSearch();
                    }, checkFrequencyInMs);
                }
            })();
        }
    </script>
TEMPLATE;
        return str_replace(['{{function}}', '{{recordid}}', '{{target}}'], [$page->function, $page->recordid, $page->target],
                $template);
    }
}

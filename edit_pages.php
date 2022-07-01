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
require_once(__DIR__ . '/../../config.php');
require_once('./locallib.php');
require_once('./classes/edit_pages.php');
global $OUTPUT, $PAGE, $DB, $USER;
$broadcaster = 'local_broadcaster';

use local_broadcaster\edit_pages;

try {
    $PAGE->set_context(context_system::instance());
    $PAGE->set_url('/local/broadcaster/edit_pages.php');
    $PAGE->set_title(local_broadcaster_get_string('editpagestitle', $broadcaster));

    require_login();
    load_all_capabilities();

    // Consider personalised site and category management capabilities.
    $pass = local_broadcaster_has_category_manage_capability();

    $output = '';

    if ($pass) {
        // Prepare some output for the main section.

        // These parameters are sent only from the records list to edit or delete an entry.
        $id = optional_param('id', 0, PARAM_INT);
        $action = optional_param('action', 'create', PARAM_TEXT);
        $page = optional_param('page', 0, PARAM_INT);
        $pagetypeid = optional_param('pagetypeid', 0, PARAM_INT);
        $pagesize = 10;
        $offset = $page * $pagesize;
        // Check if this the valid session key.
        $sesskey = optional_param('sesskey', '', PARAM_TEXT);
        $form = new edit_pages();
        $toform = null;
        // Check if an action is set, check if a record with id exists, then populate the form.

        $typestable = $broadcaster . '_pagetype';
        $dbtable = $broadcaster;

        if ((sesskey() <> $sesskey) && $action && $id) {
            $output .= local_broadcaster_get_string('invalidsession');
            redirect($PAGE->url->get_path(), $output);
        }

        if ($action && $id) {
            $exists = $DB->record_exists($dbtable, ['id' => $id]);
            if ($exists) {
                $toform = $DB->get_record($dbtable, ['id' => $id]);
                // Set the field values in the form elements.
                $form->set_data($toform);
                $form->form->addElement('hidden', 'action');
                $form->form->setType('action', PARAM_ALPHANUMEXT);
                $form->form->setDefault('action', $action);
                // Set the field text value in the form editor.
                $form->form->setDefault('content', ['text' => $toform->content]);
                $message = strtoupper("<strong>$action $id</strong>");
                $form->form->addElement('header', 'actionheader', $message);
            }
        } else {
            $form->form->setDefault('end', time() + 3600 * 24);
            // Set default data (if any).
            $form->set_data($toform);
        }
        $conditions = [];
        if ($pagetypeid > 0) {
            $conditions['pagetypeid'] = $pagetypeid;
        }

        if (!is_siteadmin()) {

            $wheresql = '';
            $categories = local_broadcaster_get_user_categories();
            if ($categories) {
                $wheresql .= ' categoryid in (' . implode(', ', array_keys($categories)) . ')';
            }

            $cohorts = local_broadcaster_get_user_cohorts();
            if ($cohorts) {
                if ($categories) {
                    $wheresql .= ' OR ';
                }
                $wheresql .= 'cohortid IN (' . implode(', ', array_keys($cohorts)) . ')';
            }

            $sql = "SELECT * FROM {{$dbtable}} ";
            if ($wheresql) {
                $sql .= " WHERE $wheresql";
            }
            $counter = count($DB->get_records_sql($sql, $conditions));
            $pages = $DB->get_records_sql($sql, $conditions, $offset, $pagesize);
        } else {
            $pages = $DB->get_records($dbtable, $conditions, 'id', '*', $offset, $pagesize);
            $counter = $DB->count_records($dbtable, $conditions);
        }
        $maxpages = (int) (($counter - 1) / $pagesize);

        if ($pages) {
            $url = './edit_pages.php';
            $all = local_broadcaster_get_string('all', $broadcaster);
            $selecttypes = "<option value='0'>$all</option>";
            $pagetypes = $DB->get_records_menu($broadcaster . '_pagetype', null, 'id', 'id, type');
            foreach ($pagetypes as $key => $pagetype) {
                if ($key == $pagetypeid) {
                    $selected = 'selected="selected"';
                } else {
                    $selected = '';
                }
                $selecttypes .= "<option value='$key' $selected>$pagetype</option>";
            }
            $label =
                    local_broadcaster_get_string('page', $broadcaster) . '&ensp;' .
                    local_broadcaster_get_string('types', $broadcaster);
            $typesselector = <<<TYPES
<div class="broadcaster formdiv border border-primary">
    <form action="$url">
        <span class="label label-primary">
            <label for="pagetypeid">$label</label>
        </span>
        <input type="hidden" id="page" name="page" value="$page">
        <select id="pagetypeid" name="pagetypeid" onchange="this.form.submit()">
        $selecttypes
        </select>
    </form>
</div>
TYPES;

            $table = new html_table();
            $table->head = [
                    local_broadcaster_get_string('id', $broadcaster),
                    local_broadcaster_get_string('pageidentifier', $broadcaster),
                    local_broadcaster_get_string('title', $broadcaster),
                    local_broadcaster_get_string('user', $broadcaster),
                    local_broadcaster_get_string('active', $broadcaster),
                    local_broadcaster_get_string('header', $broadcaster),
                    local_broadcaster_get_string('from', $broadcaster),
                    local_broadcaster_get_string('to', $broadcaster),
                    local_broadcaster_get_string('edit', $broadcaster),
                    local_broadcaster_get_string('delete', $broadcaster),
            ];
            foreach ($pages as $key => $broadcastpage) {
                $table->data[] =
                        [
                                $key,
                                $broadcastpage->identifier,
                                $broadcastpage->title,
                                $DB->get_field('user', 'username', ['id' => $broadcastpage->userid]),
                                [local_broadcaster_get_string('no', $broadcaster),
                                        local_broadcaster_get_string('yes', $broadcaster)][$broadcastpage->active],
                                [local_broadcaster_get_string('footer', $broadcaster),
                                        local_broadcaster_get_string('header', $broadcaster)][$broadcastpage->header],
                                date('d M Y H:i:s', $broadcastpage->timebegin),
                                date('d M Y H:i:s', $broadcastpage->timeend),
                                local_broadcaster_post_link(new moodle_url($url), 'id', '&#9998;', $key, 'edit', $page,
                                        $pagetypeid),
                                local_broadcaster_post_link(new moodle_url($url), 'id', '&#10007;', $key, 'delete', $page,
                                        $pagetypeid)
                        ];
            }
            $output .= $typesselector;
            $output .= local_broadcaster_edit_pages_nav($page, $maxpages, $PAGE->url->get_path(), $pagetypeid);
            $output .= '<div class="broadcaster pagediv border border-primary">' . html_writer::table($table) . '</div><p/>';
        }

        // Redirect before rendering if cancellation is clicked.
        if ($form->is_cancelled()) {
            // Return to the parent page.
            redirect('./index.php');
        } else if ($form->is_submitted()) {
            // If valid data are given, store and redirect before rendering.
            $data = $form->get_data();
            if ($data) {
                $form->save_data($data);
                // Reload the page with new empty form.
                redirect($PAGE->url, local_broadcaster_get_string('changessaved'));
            }
        }
        $prompt = local_broadcaster_get_prompt($id, $broadcaster, $action);
        $new = local_broadcaster_get_string('new', $broadcaster);

    } else {
        $output = local_broadcaster_get_string('nopermissiontoedit', 'local_broadcaster');
    }
    $output .= '<p/>';
    $output .= '<div class="broadcaster recordform border border-primary">';

    $back = local_broadcaster_get_string('back', $broadcaster);
    // Display action buttons.
    if ($pass) {
        $output .= <<<NEW
    <a id="newbutton" type="submit" href="{$PAGE->url->get_path()}" class="btn btn-primary">$new</a>
NEW;
    }
    $output .= <<<BACK
    <a id="backbutton" type="submit" href="./index.php" class="btn btn-primary">$back</a>
BACK;

    if ($pass) {
        if ($pagetypeid) {

            $form->form->setDefault('pagetypeid', $pagetypeid);
        }
        // Displays the form.
        $output .= $form->render();
        // Add confirmation to the submission button.
        $output .= <<<SCRIPT
<script defer>
    const button = document.getElementById("id_submitbutton")
    button.form.onsubmit = function (){
        return confirm('$prompt');
    }
</script>
SCRIPT;

    }
    $output .= '</div>';

    // Start rendering.

    echo $OUTPUT->header();
    echo $output;

    echo $OUTPUT->footer();
} catch (dml_exception | coding_exception | require_login_exception | moodle_exception $e) {
    debugging($e->getMessage() . '<br/>' . $e->getTraceAsString(), DEBUG_DEVELOPER);
}

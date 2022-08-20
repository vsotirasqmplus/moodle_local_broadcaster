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
use local_broadcaster\EditTypes;

require_once(__DIR__ . '/../../config.php');
global $OUTPUT, $PAGE, $DB;
require_once('./locallib.php');
require_once('./classes/EditTypes.php');
$broadcaster = 'local_broadcaster';
try {
    $PAGE->set_context(context_system::instance());
    $PAGE->set_url('/' . str_replace('_', '/', $broadcaster) . '/edit_types.php');
    $PAGE->set_title(local_broadcaster_get_string('edittypestitle', $broadcaster));
    require_login();

    // The Broadcaster Types should be managed only by the site admins.
    require_capability('moodle/site:config', context_system::instance());

    // Prepare some output for the main section.
    $output = '';
    // These parameters are snt only from the records list to edit or delete an entry.
    $id = optional_param('id', 0, PARAM_INT);
    $action = optional_param('action', 'create', PARAM_TEXT);
    // Check if this the valid session key.
    $sesskey = optional_param('sesskey', '', PARAM_TEXT);
    $form = new EditTypes();
    $toform = null;

    $dbtable = $broadcaster . '_pagetype';

    if ((sesskey() <> $sesskey) && $action && $id) {
        $output .= local_broadcaster_get_string('invalidsession');
        redirect($PAGE->url->get_path(), $output);
    }

    if ($action && $id) {
        $exists = $DB->record_exists($dbtable, ['id' => $id]);
        if ($exists) {
            $toform = $DB->get_record($dbtable, ['id' => $id]);
            $form->form->addElement('hidden', 'action');
            $form->form->setType('action', PARAM_ALPHANUMEXT);
            $form->form->setDefault('action', $action);

            switch ($action) {
                case 'edit':
                case 'delete':
                    $message = strtoupper("<strong>$action:$id</strong>");
                    $form->form->addElement('header', 'actionheader', $message);
                    break;
                case 'create':
                    $message = strtoupper("<strong>$action</strong>");
                    $form->form->addElement('header', 'actionheader', $message);
                    break;
                default:
                    break;
            }
        }
    }
    // Set default data (if any).
    $form->set_data($toform);
    $output .= '<h1>' . local_broadcaster_get_string('edittypestitle', $broadcaster) . '</h1>';
    $types = $DB->get_records($dbtable, null, 'id');
    if ($types) {
        $url = './edit_types.php';
        $table = new html_table();
        $table->head = [
            local_broadcaster_get_string('id', $broadcaster),
            local_broadcaster_get_string('type', $broadcaster),
            local_broadcaster_get_string('url', $broadcaster) . local_broadcaster_get_string('pagetypeid', $broadcaster),
            local_broadcaster_get_string('user', $broadcaster),
            local_broadcaster_get_string('active', $broadcaster),
            local_broadcaster_get_string('edit', $broadcaster),
            local_broadcaster_get_string('delete', $broadcaster),
        ];
        foreach ($types as $key => $type) {
            $table->data[] =
                [
                    $key,
                    $type->type,
                    $type->urlpattern,
                    $DB->get_field('user', 'username', ['id' => $type->userid]),
                    [
                        local_broadcaster_get_string('no', $broadcaster),
                        local_broadcaster_get_string('yes', $broadcaster)
                    ][$type->active],
                    local_broadcaster_post_link(new moodle_url($url), 'id', '&#9998;', $key, 'edit'),
                    local_broadcaster_post_link(new moodle_url($url), 'id', '&#10007;', $key, 'delete')
                ];
        }
        $output .= '<div class="broadcaster pagetyperecords border border-primary">' . html_writer::table($table) .
            '</div><p/>';
    }

    $prompt = local_broadcaster_get_prompt($id, $broadcaster, $action);
    $output .= ($id == 0) ? '<p/>' . local_broadcaster_get_string('addtyperecord', $broadcaster) : '';

    // Redirect before rendering if cancellation is clicked.
    if ($form->is_cancelled()) {
        // Return to the parent page.
        redirect('./index.php');
    }

    if ($form->is_submitted()) {
        // If valid data are given, store and redirect before rendering.
        $data = $form->get_data();
        if ($data) {
            $form->save_data($data);
            // Reload the page with new empty form.
            redirect($PAGE->url, get_string('changessaved'));
        }
    }

    $output .= '<div class="broadcaster recordform border border-primary">';
    // Display action buttons.
    $new = local_broadcaster_get_string('new', 'local_broadcaster');
    $back = local_broadcaster_get_string('back', 'local_broadcaster');
    $output .= <<<BUTTON
<p/>
    <a id="newbutton" type="submit" href="$PAGE->url" class="btn btn-primary">$new</a>
    <a id="backbutton" type="submit" href="./index.php"  class="btn btn-primary">$back</a>

BUTTON;
    // Displays the form.
    $output .= $form->render();
    $output .= <<<SCRIPT
<script>
    const button = document.getElementById("id_submitbutton")
    button.form.onsubmit = function (){
        return confirm('$prompt');
    }
</script>

SCRIPT;
    $output .= '</div>';
    // Start rendering.

    echo $OUTPUT->header();
    echo $output;

    echo $OUTPUT->footer();
} catch (dml_exception|coding_exception|moodle_exception|require_login_exception $e) {
    debugging($e->getMessage() . '<br/>' . $e->getTraceAsString(), DEBUG_DEVELOPER);
}

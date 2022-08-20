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

function local_broadcaster_post_link($url, $name, $text, $value, $action, $page = 0, $pagetypeid = 0): string
{
    $sesskey = sesskey();
    return <<<TEXT
<form method="POST" action="$url">
    <input type="hidden" name="$name" value="$value">
    <input type="hidden" name="action" value="$action">
    <input type="hidden" name="sesskey" value="$sesskey">
    <input type="hidden" name="page" value="$page">
    <input type="hidden" name="pagetypeid" value="$pagetypeid">
    <button type="submit">$text</button>
</form>
TEXT;

}

/**
 * @param $id
 * @param $broadcaster
 * @param $action
 * @return string
 */
function local_broadcaster_get_prompt($id, $broadcaster, $action): string
{
    if ($id == 0) {
        $prompt = local_broadcaster_get_string('insert', $broadcaster);
    } else {
        $a = ['action' => $action, 'id' => $id];
        $prompt = local_broadcaster_get_string('amend', $broadcaster, (object)$a);
    }

    return $prompt;
}

/**
 * @param int $page
 * @param int $pages
 * @param string $url
 * @param int $pagetypeid
 * @return string
 */
function local_broadcaster_edit_pages_nav(int $page, int $pages, string $url, int $pagetypeid): string
{
    $page = max(0, min($page, $pages));
    $typeid = $pagetypeid > 0 ? "&pagetypeid=$pagetypeid" : '';
    $options =
        [-$page, -5000, -2000, -1000, -500, -100, -50, -20, -10, -5, -4, -3, -2, -1, 0, 1, 2, 3, 4, 5, 10, 20, 50, 100,
            500, 1000, 2000, 5000, $pages];
    $pagenav = html_writer::tag('li',
        local_broadcaster_get_string('page', 'local_broadcaster') . '&emsp;' . '<br/>' . ($page + 1) . '&emsp;',
        ['class' => 'page-item', 'align' => 'center']);

    $links = "$pagenav<li class='page-item'><a class='page-link' href='$url?page=0$typeid'>&laquo; 1</a></li>\n";
    foreach ($options as $option) {
        $target = ($page + $option);
        if ($target > 0 && $target < $pages) {
            $link = local_broadcaster_get_string(['prev', 'next'][(int)($target > $page)], 'local_broadcaster', $target + 1);
            $links .= "<li class='page-item'><a class='page-link' href='$url?page=$target" . $typeid . "'>$link</a></li>\n";
        }
    }
    $last = $pages + 1;
    $links .= "<li class='page-item'><a class='page-link' href='$url?page=$pages" . $typeid . "'>$last &raquo;</a></li>\n";

    return <<<NAV
<nav aria-label="Page navigation">
  <ul class="pagination justify-content-center">
  $links
  </ul>
</nav>
NAV;
}

/**
 * @param $identifier
 * @param null $plugin
 * @param null $params
 * @return string
 */
function local_broadcaster_get_string($identifier, $plugin = null, $params = null): string
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
 */
function local_broadcaster_get_user_categories(): array
{
    load_all_capabilities();
    return core_course_category::make_categories_list('moodle/category:manage');
}

/**
 * @return string[]
 * @throws dml_exception
 */
function local_broadcaster_get_user_cohorts(): array
{
    global $DB;
    $categories = array_keys(local_broadcaster_get_user_categories());
    $condition = '';
    if ($categories) {
        $condition .= ' AND cc.id IN (' . implode(', ', $categories) . ') ';
    }
    $sql = 'SELECT ch.id, ' . $DB->sql_concat_join("'&emsp;|&emsp;'", ['cc.name', 'ch.name']) . ' cohort, cx.path
                FROM {cohort} ch
                JOIN {context} cx on cx.id = ch.contextid
                JOIN {course_categories} cc on cc.id = cx.instanceid
                WHERE ch.visible = 1
                ' . $condition . '
                ORDER BY cx.path;';
    $cohorts = $DB->get_records_sql($sql);
    if (is_siteadmin()) {
        $list = [0 => local_broadcaster_get_string('none', 'local_broadcaster')];
    } else {
        $list = [];
    }
    if ($cohorts) {
        foreach ($cohorts as $key => $value) {
            $list[$key] = str_repeat('&ensp;', substr_count($value->path, '/')) . $value->cohort;
        }
    }
    return $list;
}

/**
 * @throws coding_exception
 * @throws dml_exception
 */
function local_broadcaster_has_category_manage_capability(): bool
{
    global $USER, $DB;
    // Consider personalised site and category management capabilities.
    $pass = has_capability('moodle/site:config', context_system::instance());
    if (!$pass) {
        // Check for category level manage capability.
        foreach ($USER->access['ra'] as $contextpath => $roles) {
            $cx = $DB->get_record('context', ['path' => $contextpath], '*', IGNORE_MULTIPLE);
            if ($cx && $roles) {
                $context = context::instance_by_id($cx->id);
                if ($context) {
                    $pass = has_capability('moodle/category:manage', $context) || $pass;
                }
            }
        }
    }
    return $pass;
}

/**
 * @throws coding_exception
 */
function local_broadcaster_login_state(): int
{
    if (!isloggedin()) {
        return 0;
    }
    if (isguestuser()) {
        return 1;
    }
    return 2;
}

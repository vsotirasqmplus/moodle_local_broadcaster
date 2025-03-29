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

/**
 * Plugin core library file
 *
 * @copyright Vasileios Sotiras <v.sotiras@qmul.ac.uk>
 * @license GPL
 * @package broadcaster
 *
 */

use core_user\output\myprofile\tree;
use local_broadcaster\Broadcaster;

defined('MOODLE_INTERNAL') || die();
require_once(dirname(__FILE__) . '/locallib.php');

/**
 *
 * This function is utilised to add broadcaster contents
 *
 * @throws moodle_exception
 * @throws dml_exception
 * @package local+broadcaster
 *
 */
function local_broadcaster_before_footer(): string
{
    // Returning a value to be used to display is only available on 3.10 onwards.
    $content = Broadcaster::envelope_contents(__FUNCTION__);
    if ($content) {
        if (Broadcaster::moodle_version() <= 30900) {
            echo $content;
            return '';
        } else {
            return $content;
        }
    }
    return '';
}

/**
 *
 * Add navigation breadcrumbs
 *
 * @param tree $tree
 * @return bool
 */
function local_broadcaster_myprofile_navigation(core_user\output\myprofile\tree $tree): bool
{
    try {
        if (local_broadcaster_has_category_manage_capability()) {
            // A dd links to report category.
            $url = new moodle_url('/local/broadcaster/index.php');
            $linktext = get_string('pluginname', 'local_broadcaster');
            $node = new core_user\output\myprofile\node('miscellaneous', 'broadcaster', $linktext, null, $url);
            $tree->add_node($node);
            return true;
        }
    } catch (dml_exception|coding_exception $codingexception) {
        debugging($codingexception->getMessage() . ' ' . $codingexception->getTraceAsString(), DEBUG_DEVELOPER);
    }
    return false;
}

/**
 *
 * Add navigation breadcrumb
 *
 * @param global_navigation $navigation
 * @return void
 */
function local_broadcaster_extend_navigation(global_navigation $navigation)
{

    // Add a breadcrumb for each user visible page.
    try {
        $navigation->add(get_string('pluginname', 'local_broadcaster'),
            new moodle_url('/local/broadcaster/index.php', []),
            71,
            get_string('pluginname', 'local_broadcaster'),
            'broadcaster');
        $navigation->add(get_string('broadcasteredittypesnav', 'local_broadcaster'),
            new moodle_url('/local/broadcaster/edit_types.php', []),
            71,
            get_string('pluginname', 'local_broadcaster'),
            'broadcasteredittypesnav');
        $navigation->add(get_string('broadcastereditpagesnav', 'local_broadcaster'),
            new moodle_url('/local/broadcaster/edit_pages.php', []),
            71,
            get_string('pluginname', 'local_broadcaster'),
            'broadcastereditpagesnav');

    } catch (moodle_exception $e) {
        debugging($e->getMessage() . '\n<br/>' . $e->getTraceAsString(), DEBUG_DEVELOPER);
    }
}

/*
 * Alternative hooks where contents can be utilised.
 * function local_broadcaster_standard_footer_html(): string
 * function local_broadcaster_before_standard_top_of_body_html(): string
 */

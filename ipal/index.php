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
 * This script lists all the instances of ipal in a particular course
 *
 * @package    mod_ipal
 * @copyright  2010 onwards William Junkin  {@link http://moodle.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/lib.php');

$id = required_param('id', PARAM_INT);   // Course id.
$PAGE->set_url('/mod/ipal/view.php', array('id' => $id));

if (! $course = $DB->get_record('course', array('id' => $id))) {
    error('Course ID is incorrect');
}

$coursecontext = context_course::instance($id);
require_login($course);
$PAGE->set_pagelayout('incourse');

$params = array(
    'context' => $coursecontext
);
$event = \mod_ipal\event\ipal_instance_list_viewed::create($params);
$event->trigger();

// Print the header.

$PAGE->set_url('/mod/ipal/view.php', array('id' => $id));
$PAGE->set_title($course->fullname);
$PAGE->set_heading($course->shortname);

echo $OUTPUT->header();

// Get all the appropriate data.

if (! $ipals = get_all_instances_in_course('ipal', $course)) {
    echo $OUTPUT->heading(get_string('noipals', 'ipal'), 2);
    echo $OUTPUT->continue_button("view.php?id = $course->id");
    echo $OUTPUT->footer();
    die();
}
echo $OUTPUT->heading(get_string('modulenameplural', 'ipal'), 2);

echo "\n<br />Here are all the ipal instances for this course";

foreach ($ipals as $ipal) {
    if (!$ipal->visible) {
        // Show dimmed if the mod is hidden.
        echo "\n<br />".'<a class="dimmed" href="view.php?id='.$ipal->coursemodule.'">'.format_string($ipal->name).'</a>';
    } else {
        // Show normal if the mod is visible.
        echo "\n<br />".'<a href="view.php?id='.$ipal->coursemodule.'">'.format_string($ipal->name).'</a>';
    }
}


// Finish the page.

echo $OUTPUT->footer();

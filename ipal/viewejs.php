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
 * This script provides the code so that EJS activities can be added to questions in IPAL.
 *
 *
 * @package   mod_ipal
 * @copyright 2011 w. F. Junkin, Eckerd College (http://www.eckerd.edu)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once('../../mod/ejsapp/external_interface/ejsapp_external_interface.php');


$id = optional_param('id', 0, PARAM_INT); // Course_module ID.
$n  = optional_param('n', 0, PARAM_INT);  // Ejsapp instance ID.
if ($id) {
    $cm         = get_coursemodule_from_id('ejsapp', $id, 0, false, MUST_EXIST);
    $course     = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
    $ejsapp  = $DB->get_record('ejsapp', array('id' => $cm->instance), '*', MUST_EXIST);
} else {
    if ($n) {
        $ejsapp  = $DB->get_record('ejsapp', array('id' => $n), '*', MUST_EXIST);
        $course     = $DB->get_record('course', array('id' => $ejsapp->course), '*', MUST_EXIST);
        $cm         = get_coursemodule_from_instance('ejsapp', $ejsapp->id, $course->id, false, MUST_EXIST);
    } else {
        error('You must specify a course_module ID or an instance ID');
    }
}
require_login($course, true, $cm);

// Require_login($course, true, $cm);/Find out why this causes an error.
$context = context_module::instance($course->id);

// Setting some, but not all, of the PAGE values.
$PAGE->set_url('/mod/ejsapp/view.php', array('id' => $cm->id));
$PAGE->set_context($context);
$PAGE->set_button(update_module_button($cm->id, $course->id, get_string('modulename', 'ejsapp')));

// Output starts here.
echo "<html><head></head><body>";
echo "<div align='center'>\n";
$externalsize = new stdClass;
$externalsize->width = 570;
$externalsize->height = 380;
echo $OUTPUT->heading(draw_ejsapp_instance($ejsapp->id, null, $externalsize->width, $externalsize->height));
echo "\n</div></body></html>";

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
 * This script deals with starting a new attempt of a quiz for an IPAL session.
 *
 * Normally, it will be in an iframe that is refreshed to $url = $CFG->wwwroot.'/mod/quiz/attempt.php?attempt='.$attemptid.'&page=1';
 *
 * @package   mod_quiz
 * @copyright 2018 w. F. Junkin, Eckerd College (http://www.eckerd.edu)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
// The course module for this quiz is $cmid.
$cmid = required_param('cmid', PARAM_INT);
if (!$cm = get_coursemodule_from_id('quiz', $cmid)) {
    print_error('invalidcoursemodule');
}
if (!$course = $DB->get_record('course', array('id' => $cm->course))) {
    print_error("coursemisconf");
}

require_login($course, true, $cm);
echo "<html><head></head><body>";
    $form = "<form method=\"post\" action=\"".$CFG->wwwroot."/mod/quiz/startattempt.php\" id='ipalform2'>";
    $form .= "<input type=\"hidden\" name=\"cmid\" value=\"$cmid\">";
    $form .= "<input type=\"hidden\" name=\"sesskey\" value=\"".$USER->sesskey."\">";
    $form .= "<button type=\"submit\">Attempt quiz now</button>";
    $form .= "</form>";
    echo $form;
/**
 * The function to create the javascript to submit the form to <wwwroot>/mod/quiz/startattempt.php?id=$cmid
 *
 * @param $formid The string to identify the form that should be submitted. It's value usually is 'lableform1'.
 **/
function submitform($formid) {
    echo "<script language='javascript'>";
    echo "\ndocument.getElementById(\"$formid\").submit()";
    echo "\n</script>";
   
}
submitform('ipalform2');
echo "\n</body></html>";
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
 * Prints a particular instance of quiz
 *
 *
 * @package   mod_quiz
 * @copyright 2017 w. F. Junkin, Eckerd College (http://www.eckerd.edu)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


require_once(dirname(dirname(dirname(dirname(__FILE__)))).'/config.php');

require_once("locallib_liveview.php");

$id = optional_param('id', 0, PARAM_INT); // Course_module ID for this IPAL.
$n  = optional_param('n', 0, PARAM_INT);  // IPAL instance ID.

if ($id) {
    $cm         = get_coursemodule_from_id('ipal', $id, 0, false, MUST_EXIST);
    $course     = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
    $ipal = $DB->get_record('ipal', array('id' => $cm->instance), '*', MUST_EXIST);
    //$quiz  = $DB->get_record('quiz', array('id' => $cm->instance), '*', MUST_EXIST);
} else {
    if ($n) {
        $ipal  = $DB->get_record('ipal', array('id' => $n), '*', MUST_EXIST);
        $course     = $DB->get_record('course', array('id' => $ipal->course), '*', MUST_EXIST);
        $cm         = get_coursemodule_from_instance('ipal', $ipal->id, $course->id, false, MUST_EXIST);
    } else {
        error('You must specify a course_module ID or an IPAL instance ID');
    }
}

require_login($course, true, $cm);

if ($ipal->mobile != 4) {
    // The teacher came her by mistake. Send the teacher to the IPAL instance that doesn't use a quiz.
    $ipalurl = $CFG->wwwroot.'/mod/ipal/view.php?n='.$ipal->id;
    redirect($ipalurl);
    exit;
}
if (!($ipal->quizid > 0)) {
    // The teacher needs to select a quiz and use its questions with IPAL.
    $choosequizurl = $CFG->wwwroot.'/mod/ipal/liveview/choosequiz.php?ipalid='.$ipal->id;
    redirect($choosequizurl);
    exit;
}
$quiz = $DB->get_record('quiz', array('id' => $ipal->quizid));
$cmquiz = get_coursemodule_from_instance('quiz', $quiz->id, $course->id, false, MUST_EXIST);
$context = context_module::instance($cmquiz->id);

// Log this request.
$params = array(
    'objectid' => $quiz->id,
    'context' => $context
);
$event = \mod_quiz\event\course_module_viewed::create($params);
$event->add_record_snapshot('quiz', $quiz);
$event->trigger();

// Print the page header.

$PAGE->set_url('/mod/ipal/liveview/quizview.php', array('id' => $cm->id));
$PAGE->set_title($quiz->name);
$PAGE->set_heading($course->shortname);

// Output starts here.
// Make sure there is only one question per page. To do: make this better html code.
$quizslots = $DB->get_records('quiz_slots', array('quizid' => $quiz->id));
$slotpages = array();
foreach ($quizslots as $quizslot) {
    $slotpages[$quizslot->page] = 1;
}
if (count($slotpages) != count($quizslots)) {
    echo "<html><head></head><body>";
    echo "In-class polling requires one page per question.";
    echo "\n<br />You have ".count($quizslots)." questions and only ".count($slotpages)." pages.";
    echo "\n<br />You must use the back button on your broswer and correct this before you can use this quiz for in-class polling.";
    echo "</body></html>";
    exit;
}

echo $OUTPUT->header();
if (has_capability('mod/quiz:manage', $context)) {
    // The cmquiz->id is the cmid for the quiz. The cm->id is the cmid for the ipal instance. 
    quiz_display_instructor_interface($cmquiz->id, $quiz->id, $cm->id);
} else {
    echo "\n<br />You are not authorized to view the teacher interface.";
    exit;
}

// Make sure this is being used for in-class polling. Pages should not be shuffled.
$quizsections = $DB->get_record('quiz_sections', array('quizid' => $quiz->id));
if ($quizsections->shufflequestions <> 0) {
    $record = new stdClass();
    $record->id = $quizsections->id;
    $record->shufflequestions = 2;
    $DB->update_record('quiz_sections', $record);
}

// Finish the page.
echo $OUTPUT->footer();
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
 * Prints a particular instance of ipal
 *
 *
 * @package   mod_ipal
 * @copyright 2011 w. F. Junkin, Eckerd College (http://www.eckerd.edu)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


require_once(dirname(dirname(dirname(dirname(__FILE__)))).'/config.php');
require_once ('locallib.php'); // This is the locallib.php script for the choosequiz.php script only.
$id = optional_param('id', 0, PARAM_INT); // Course_module ID.
$ipalid  = optional_param('ipalid', 0, PARAM_INT);  // Ipal instance ID - it should be named as the first character of the module.
$quizid = optional_param('quizid', 0, PARAM_INT); // The ID of the quiz that will be used if a quiz has been chosen.
$timecreated = optional_param('timecreated', 0, PARAM_INT); // The timecreated for the IPAL instance, used to ensure the post came from this page.

if ($id) {
    $cm         = get_coursemodule_from_id('ipal', $id, 0, false, MUST_EXIST);
    $course     = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
    $ipal  = $DB->get_record('ipal', array('id' => $cm->instance), '*', MUST_EXIST);
} else {
    if ($ipalid) {
        $ipal  = $DB->get_record('ipal', array('id' => $ipalid), '*', MUST_EXIST);
        $course     = $DB->get_record('course', array('id' => $ipal->course), '*', MUST_EXIST);
        $cm         = get_coursemodule_from_instance('ipal', $ipal->id, $course->id, false, MUST_EXIST);
    } else {
        error('You must specify a course_module ID or an instance ID');
    }
}

require_login($course, true, $cm);

$context = context_module::instance($cm->id);

// Log this request.
$params = array(
    'objectid' => $ipal->id,
    'context' => $context
);
$event = \mod_ipal\event\course_module_viewed::create($params);
$event->add_record_snapshot('ipal', $ipal);
$event->trigger();

// Print the page header.

$PAGE->set_url('/mod/ipal/liveview/choosequiz.php', array('id' => $cm->id, 'quizid' => $quizid));
$PAGE->set_title($ipal->name);
$PAGE->set_heading($course->shortname);
echo $OUTPUT->header();
if ($quizid > 0) {
    $selectquiz = false;
} else {
    $selectquiz = true;
}
$hascapability = has_capability('mod/ipal:instructoraccess', $context);
if ($hascapability) {
    if ($selectquiz) {
        echo "\n<br />You have chosen to use an IPAL activity where the questions are provided by a quiz.
            With this choice, students can use the Mobile Moodle app or a browser to answer questions.
            However, students cannot use the IPAL App to answer questions.
            If this was not your intent, please return to the settings for this IPAL activity and change the settings.";
        echo "\n<br />Otherwise, please continue. Students will be able to use the Mobile Moodle App to answer questions.
            The questions will be provided by the quiz that you choose. You have not yet selected a quiz.";
        echo "\n<br />Please choose a quiz to provide the questions for this IPAL activity.";
        echo "\n<br />If you want to change the questions provided by a quiz, you will need to go to the quiz activity and change the questions there.";
        echo "\n<br />For your convenience, each quiz has a link that provides a view of the questions associated with that quiz.";
        quiz_table_form($course->id, $cm->id, $ipalid, $ipal->timecreated);
    } else {
        if ($timecreated == $ipal->timecreated) {
        // The id for the quiz that will be used has been submitted.
        // Create or locate the no_active_question question.
        $noactivequestionid = ipal_noactiveq_create_($course->id);
        // Put this question in slot 1 and make sure the questions are not shuffled.
        $message = ipal_add_firstquestion_to_quiz($quizid, $noactivequestionid);
        if (strlen($message) !== 0) {
            echo $message;
            exit;
        }
        $quiz = $DB->get_record('quiz', array('id' => $quizid));
        $record = new stdClass();
        $record->id = $ipal->id;
        $record->quizid = $quizid;
        $quizname = $quiz->name;
        $nonlinkedquizname = preg_replace("/\ /", '_', $quizname); 
        $record->intro = $ipal->intro."\n(using quiz: ".$nonlinkedquizname.")"; 
        $DB->update_record('ipal', $record);
        // Show description.
        $record1 = new stdClass();
        $record1->id = $cm->id;
        $record1->showdescription = '1';
        $DB->update_record('course_modules', $record1);
        // A quiz has been selected for this IPAL activity.
        // Make a label for Mobile Moodle users.
        make_label($ipal->id);
        // When the information is submitted, the redirect will go to the course page.
        submitform('labelform1');
        } else {
            echo "\n<br />You did not come from the page for choosing a quiz or something else is wrong.";
            exit;
        }
    }
} else {
    echo "\n<br />You do not have permission to view this page";
}

echo $OUTPUT->footer();

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
 * Displays the IPAL HIstogram or text resposes.
 *
 * An indicaton of # of responses to this question/# of student responding to this IPAL instance is printed.
 * After that the histogram or the text responses are printed, depending on the question type.
 * @package    mod_ipal
 * @copyright  2012 W. F. Junkin, Eckerd College, http://www.eckerd.edu
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once($CFG->dirroot.'/lib/graphlib.php');
$ipalid = optional_param('ipalid', 0, PARAM_INT);
$ipal = $DB->get_record('ipal', array('id' => $ipalid));
$course = $DB->get_record('course', array('id' => $ipal->course), '*', MUST_EXIST);
$cm = get_coursemodule_from_instance('ipal', $ipal->id, $course->id, false, MUST_EXIST);
require_login($course, true, $cm);
$contextinstance = context_module::instance($cm->id);
if (!(has_capability('mod/ipal:instructoraccess', $contextinstance))) {
    echo "\n<br />You must be authorized to access this site";
    exit;
}

/**
 * Return the id for the current question
 *
 * @param int $ipalid The ID for the IPAL instance
 * @return int The ID for the current question.
 */
function ipal_show_current_question_id_graphics($ipalid) {
    global $DB;
    if ($DB->record_exists('ipal_active_questions', array('ipal_id' => $ipalid))) {
        $question = $DB->get_record('ipal_active_questions', array('ipal_id' => $ipalid));
        return($question->question_id);
    }
    return(0);
}

/**
 * Return the id for the current question from the ipal_active_question table.
 *
 * @param int $ipalid The ID for the IPAL instance
 * @return int The ID for the current question.
 */
function ipal_current_question_code($ipalid) {
    global $DB;
    if ($DB->record_exists('ipal_active_questions', array('ipal_id' => $ipalid))) {
        $question = $DB->get_record('ipal_active_questions', array('ipal_id' => $ipalid));
        return($question->id);
    }
    return(0);
}

/**
 * Return the ids of all users who have submitted answers to the active question question (ipal_code).
 *
 * @param int $ipalid The ID for the IPAL instance
 * @return array The user ids.
 */
function ipal_who_thistime($ipalid) {
    global $DB;
    $questioncode = ipal_current_question_code($ipalid);
    $records = $DB->get_records('ipal_answered', array('ipal_code' => $questioncode));
    foreach ($records as $records) {
         $answer[] = $records->user_id;
    }
    return(count(@array_unique($answer)));
}


/**
 * Return the number of users who have submitted answers to this IPAL instance.
 *
 * @param int $ipalid The ID for the IPAL instance
 * @return int The number of students submitting answers.
 */
function ipal_who_sofar_count($ipalid) {
    global $DB;
    $records = $DB->get_records('ipal_answered', array('ipal_id' => $ipalid));
    foreach ($records as $records) {
        $answer[] = $records->user_id;
    }
    return(count(@array_unique($answer)));
}

/**
 * Return the number of users who have submitted answers to the active_question_id (ipal_code).
 *
 * @return int The number of students submitting answers to this question.
 */
function ipal_count_thistime_responses() {
    global $DB;
    $ipalid = optional_param('ipalid', 0, PARAM_INT);
    $questioncode = ipal_current_question_code($ipalid);
    $total = $DB->count_records('ipal_answered', array('ipal_code' => $questioncode));
    return((int)$total);
}


/**
 * Return the ids of all users who have submitted answers to this question.
 *
 * @return int The number of students.
 */
function ipal_count_active_responses() {
    global $DB;
    $ipalid = optional_param('ipalid', 0, PARAM_INT);
    $questionid = ipal_show_current_question_id_graphics($ipalid);
    $ipalid = optional_param('ipalid', 0, PARAM_INT);
    $total = $DB->count_records('ipal_answered', array('question_id' => $questionid, 'ipal_id' => ($ipalid)));
    return((int)$total);
}


/**
 * Return a string = number of responses to each question and labels for questions.
 *
 * @param int $questioncode The id in the active question table for the active question.
 * @return string The number of responses to each question and labels for questions.
 */
function ipal_count_question_codes($questioncode) {
    global $DB;

    $question = $DB->get_record('ipal_active_questions', array('id' => $questioncode));
    $questionid = $question->question_id;
    $answers = $DB->get_records('question_answers', array('question' => $questionid));
    $labels = '';
    $n = 0;
    foreach ($answers as $answer) {
        $labels .= "&x[$n]=".urlencode(substr(strip_tags($answer->answer), 0, 15));
        $data[] = $DB->count_records('ipal_answered', array('ipal_code' => $questioncode, 'answer_id' => $answer->id));
        $n ++;
    }

    return( "?data=".implode(",", $data).$labels."&total=10");

}

?>
<html>
<head>
</head>
<body>
<?php
echo "Total Responses --> ".ipal_count_thistime_responses()."/".ipal_who_sofar_count($ipalid);

/**
 * A function to optain the question type of a given question.
 *
 *
 * Modified by Junkin
 * @param int $questionid The id of hte question
 * @return int question type
 */
function ipal_get_question_type($questionid) {
    global $DB;
    $questiontype = $DB->get_record('question', array('id' => $questionid));
    return($questiontype->qtype);
}

/**
 *  A function to display answers to essay questions.
 *
 * @param int $ipalid The id of this IPAL instance
 * @return array The essay answers to the active question.
 */
function ipal_thistime_essay_answers($ipalid) {
    global $DB;
    $questioncode = ipal_current_question_code($ipalid);
    $answerids = $DB->get_records('ipal_answered', array('ipal_code' => $questioncode));
    foreach ($answerids as $id) {
        $answers[] = $id->a_text;
    }
    if (!(isset($answers[0]))) {
        $answers[0] = "No answers yet";
    }
    return($answers);

}


// A function to display asnwers to essay questions.
/**
 *  A function to display answers to essay questions.
 *
 * @param int $ipalid The id of this IPAL instance
 * @return array The essay answers to the current question.
 */
function ipal_display_essay_answers($ipalid) {
    global $DB;
    $questionid = ipal_show_current_question_id_graphics($ipalid);
    $answerids = $DB->get_records('ipal_answered', array('question_id' => $questionid));
    foreach ($answerids as $id) {
        $answers[] = $id->a_text;
    }
    return($answers);
}

$qtype = ipal_get_question_type(ipal_show_current_question_id_graphics($ipalid));
if ($qtype == 'essay') {
    $answers = ipal_thistime_essay_answers($ipalid);
    foreach ($answers as $answer) {
        echo "\n<br />".strip_tags($answer);
    }
} else {// Only show graph if question is not an essay question.
    echo "<img src=\"graph.php".ipal_count_question_codes(ipal_current_question_code($ipalid))."\"></img>";
}
echo "\n</body>\n</html>";

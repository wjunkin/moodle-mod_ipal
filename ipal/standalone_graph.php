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
 *
 * Use this file to display the selected question in an IPAL instance.
 *
 * @package    mod_ipal
 * @copyright  2013 Bill Junkin
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once($CFG->dirroot.'/lib/graphlib.php');
$ipalid = optional_param('ipalid', 0, PARAM_INT);// The id of this IPAL instance.
$refresh = optional_param('refresh', false, PARAM_BOOL);
?>
<html>
<head>
<?php
if ($refresh) {
    echo "<meta http-equiv=\"refresh\" content=\"3;url=?refresh=true&ipalid=".$ipalid."\">";
}
?>
</head>
<body>
<?php
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
 * Return the number of users who have submitted answers to this IPAL instance.
 *
 * @param int $ipalid The ID for the IPAL instance
 * @return int The number of students submitting answers.
 */
/**
 * Find out who has answered questions so far.
 *
 * @param int $ipalid This id for the ipal instance.
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
 * Return the id for the current question
 *
 * @param int $ipalid The ID for the IPAL instance
 * @return int The ID for the current question.
 */
function ipal_show_current_question_id($ipalid) {
    $id = optional_param('id', 0, PARAM_INT);// The id of the question selected (if one has been selected).
    if ($id == 0) {
        global $DB;
        if ($DB->record_exists('ipal_active_questions', array('ipal_id' => $ipalid))) {
            $question = $DB->get_record('ipal_active_questions', array('ipal_id' => $ipalid));
            return($question->question_id);
        }
        return(0);
    } else {
        return($id);
    }
}

/**
 * Return the ids of all users who have submitted answers to this question.
 *
 * @return int The number of students.
 */
function ipal_count_active_responses() {
    global $DB;
    $ipalid = optional_param('ipalid', 0, PARAM_INT);// The id of this IPAL instance.
    $questionid = ipal_show_current_question_id($ipalid);
    $total = $DB->count_records('ipal_answered', array('question_id' => $questionid, 'ipal_id' => $ipalid));
    return((int)$total);
}


/**
 * Return a string = number of responses to each question and labels for questions.
 *
 * @param int $questionid The question id in the active question table for the active question.
 * @return string The number of responses to each question and labels for questions.
 */
function ipal_count_questions($questionid) {
    global $DB;
    $ipalid = optional_param('ipalid', 0, PARAM_INT);// The id of this IPAL instance.
    $answers = $DB->get_records('question_answers', array('question' => $questionid));
    $labels = '';
    $n = 0;
    foreach ($answers as $answer) {
        $labels .= "&x[$n]=".urlencode(substr(strip_tags($answer->answer), 0, 15));
        $data[] = $DB->count_records('ipal_answered', array('ipal_id' => $ipalid, 'answer_id' => $answer->id));
        $n ++;
    }

    return( "?data=".implode(",", $data).$labels."&total=10");

}

/**
 *  A function to display answers to essay questions.
 *
 * @param int $questionid The id of question being answered
 * @return array The essay answers to the current question.
 */
function ipal_display_essay_by_id($questionid) {
    global $DB;
    $answerids = $DB->get_records('ipal_answered', array('question_id' => $questionid));
    foreach ($answerids as $id) {
        $answers[] = $id->a_text;
    }
    if (!(isset($answers[0]))) {
        $answers[0] = "No answers yet";
    }
    return($answers);
}

echo "Total Responses --> ".ipal_count_active_responses()."/".ipal_who_sofar_count($ipalid);

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

$qtype = ipal_get_question_type(ipal_show_current_question_id($ipalid));
if ($qtype == 'essay') {
    $answers = ipal_display_essay_by_id(ipal_show_current_question_id($ipalid));
    foreach ($answers as $answer) {
        echo "\n<br />".strip_tags($answer);
    }
} else {// Only show graph if question is not an essay question.
    echo "<br><img src=\"graph.php".ipal_count_questions(ipal_show_current_question_id($ipalid))."\"></img>";
}
?>
</body>
</html>

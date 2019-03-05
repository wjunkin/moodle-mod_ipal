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
 * This script provides the IPAL spreadsheet view for the teacher.
 *
 * @package    mod_ipal
 * @copyright  2012 W. F. Junkin, Eckerd College, http://www.eckerd.edu
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
$ipalid = optional_param('id', 0, PARAM_INT);// The id for this IPAL instance.
$ipal = $DB->get_record('ipal', array('id' => $ipalid));
$course = $DB->get_record('course', array('id' => $ipal->course), '*', MUST_EXIST);
$cm = get_coursemodule_from_instance('ipal', $ipal->id, $course->id, false, MUST_EXIST);
require_login($course, true, $cm);
$contextinstance = context_module::instance($cm->id);
if (!(has_capability('mod/ipal:instructoraccess', $contextinstance))) {
    echo "\n<br />You must be authorized to access this site";
    exit;
}
// To sort by first name, namesort =1. Defalut is 0 = sort by last name.
$namesort = optional_param('namesort', 0, PARAM_INT);
/**
 * Return the number of users who have submitted answers to this IPAL instance.
 *
 * @param int $ipalid The ID for the IPAL instance
 * @return int The number of students submitting answers.
 */
function ipal_who_sofar_gridview($ipalid) {
    global $DB;

    $records = $DB->get_records('ipal_answered', array('ipal_id' => $ipalid));

    foreach ($records as $records) {
        $answer[] = $records->user_id;
    }
    if (isset($answer)) {
        return(array_unique($answer));
    } else {
        return(null);
    }
}

/**
 * Return the first and last name of a student.
 *
 * @param int $userid The ID for the student.
 * @return string The last name, first name of the student.
 */
function ipal_find_student_gridview($userid, $namesort) {
     global $DB;
     $user = $DB->get_record('user', array('id' => $userid));
     if ($namesort == 1) {
        $name = $user->firstname."__".$user->lastname;
     } else {
        $name = $user->lastname."__".$user->firstname;
     }
     return($name);
}

$ipal = $DB->get_record('ipal', array('id' => $ipalid));
$questions = explode(",", $ipal->questions);
echo "<link rel=\"stylesheet\" type=\"text/css\" href=\"gridviewstyle.css\" />";

// Javascript and css for tooltips.
    echo "\n<script type=\"text/javascript\">";
    require_once("dw_tooltip_c.php");
    echo "\n</script>";

    echo "\n<style type=\"text/css\">";
    echo "\ndiv#tipDiv {";
        echo "\nfont-size:16px; line-height:1.2;";
        echo "\ncolor:#000; background-color:#E1E5F1;";
        echo "\nborder:1px solid #667295; padding:4px;";
        echo "\nwidth:320px;";
    echo "\n}";
    echo "\n</style>";
// The array for storing the all the texts for tootips.
$tooltiptext = array();
if (!$ipal->anonymous) {
    if ($namesort == 1) {
        echo "<a href='gridview.php?id=$ipalid&namesort=0'>Sort by last name</a>";
    } else {
        echo "<a href='gridview.php?id=$ipalid&namesort=1'>Sort by first name</a>";
    }
}
echo "<table border=\"1\" width=\"100%\">\n";
echo "<thead><tr>";

// If anonymous, exclude the column "name" from the table.
if (!$ipal->anonymous) {
    if ($namesort == 1) {
        echo "<th>".get_string('firstname', 'ipal').' </th><th>'.get_string('lastname', 'ipal')."</th>\n";
    } else {
        echo "<th>".get_string('lastname', 'ipal').' </th><th>'.get_string('firstname', 'ipal')."</th>\n";
    }        
}

foreach ($questions as $question) {
    if ($questiondata = $DB->get_record('question', array('id' => $question))) {
        echo "<th style=\"word-wrap: break-word;\">".substr(trim(strip_tags($questiondata->name)), 0, 80)."</th>\n";
    }
}
echo "</tr>\n</thead>\n";

$users = ipal_who_sofar_gridview($ipal->id);
if (isset($users)) {
    $tablerow = array();
    $nrow = 0;
    foreach ($users as $user) {
        $tablerow[$nrow] = "<tbody><tr>";

        // If anonymous, exlude the student name data from the table.
        if (!$ipal->anonymous) {
            $fullname = ipal_find_student_gridview($user, $namesort);
            list($lastname, $firstname) = explode('__', $fullname);
            $tablerow[$nrow] .= "<td>$lastname </td><td>$firstname</td>\n";
        }
        foreach ($questions as $question) {
            if (($question != "") and ($question != 0)) {
                $numrecords = $DB->count_records('ipal_answered', array('ipal_id' => $ipal->id,
                    'user_id' => $user, 'question_id' => $question));
                if ($numrecords == 0) {
                    $displaydata = '';
                } else if ($numrecords > 1) {
                    $answers = $DB->get_records('ipal_answered', array('ipal_id' => $ipal->id,
                        'user_id' => $user, 'question_id' => $question));
                    $answerdata = array();
                    $n = 0;
                    foreach ($answers as $myanswer) {
                        $ipalanswerid = $myanswer->answer_id;
                        $answer = $DB->get_record('question_answers', array('id' => $ipalanswerid));
                        $answerdata[$n] = $answer->answer;
                        $n++;
                    }
                    $displaydata = strip_tags(trim(implode('&&', $answerdata)));
                } else {
                    $answer = $DB->get_record('ipal_answered', array('ipal_id' => $ipal->id,
                        'user_id' => $user, 'question_id' => $question));
                    if ($answer->answer_id < 0) {
                        $displaydata = htmlentities(trim($answer->a_text));
                    } else {
                        $answerdata = $DB->get_record('question_answers', array('id' => $answer->answer_id));
                        $displaydata = $answerdata->answer;
                    }
                }

                if (strlen($displaydata) > 40) {
                        $safeanswer1 = preg_replace("/\n/", "<br />", $displaydata);
                        $tooltiptext[] .= "\n    link".$user."_$question: '$safeanswer1'";
                        $tablerow[$nrow] .= "<td><div class=\"showTip link".$user."_$question\">".substr($displaydata, 0, 40);
                        $tablerow[$nrow] .= "</div></td>";
                } else {
                    $tablerow[$nrow] .= "<td style=\"word-wrap: break-word;\">".$displaydata."</td>\n";
                }
            }
        }
        $tablerow[$nrow] .= "</tr></tbody>\n";
        $nrow ++;
    }
}
if (count($tablerow) > 0) {
    sort($tablerow);
    for ($n = 0; $n < count($tablerow); $n++) {
        echo $tablerow[$n];
    }
}
echo "</table>\n";
if (count($tooltiptext) > 0) {
    $tooltiptexts = implode(",", $tooltiptext);
    echo "\n<script>";
    echo "\ndw_Tooltip.content_vars = {";
    echo $tooltiptexts;
    echo "\n}";
    echo "\n</script>";
}
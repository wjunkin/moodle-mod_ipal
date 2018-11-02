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
function ipal_find_student_gridview($userid) {
     global $DB;
     $user = $DB->get_record('user', array('id' => $userid));
     $name = $user->lastname.", ".$user->firstname;
     return($name);
}

$ipal = $DB->get_record('ipal', array('id' => $ipalid));
$questions = explode(",", $ipal->questions);
echo "<link rel=\"stylesheet\" type=\"text/css\" href=\"gridviewstyle.css\" />";
?>
<style>
    .tooltip {
        position: relative;
        display: inline;
    }
</style>

<?php
echo "<table border=\"1\" width=\"100%\">\n";
echo "<thead><tr>";

// If anonymous, exclude the column "name" from the table.
if (!$ipal->anonymous) {
    echo "<th>Name</th>\n";
}

foreach ($questions as $question) {
    if ($questiondata = $DB->get_record('question', array('id' => $question))) {
        echo "<th style=\"word-wrap: break-word;\">".substr(trim(strip_tags($questiondata->name)), 0, 80)."</th>\n";
    }
}
echo "</tr>\n</thead>\n";

$users = ipal_who_sofar_gridview($ipal->id);
if (isset($users)) {
    foreach ($users as $user) {
        echo "<tbody><tr>";

        // If anonymous, exlude the student name data from the table.
        if (!$ipal->anonymous) {
            echo "<td>".ipal_find_student_gridview($user)."</td>\n";
        }
        foreach ($questions as $question) {
            if (($question != "") and ($question != 0)) {
                $numrecords = $DB->count_records('ipal_answered', array('ipal_id' => $ipal->id,
                    'user_id' => $user, 'question_id' => $question));
                if ($numrecords == 0) {
                    $displaydata = '&nbsp';
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
                    $displaydata = implode('&&', $answerdata);
                } else {
                    $answer = $DB->get_record('ipal_answered', array('ipal_id' => $ipal->id,
                        'user_id' => $user, 'question_id' => $question));
                    if ($answer->answer_id < 0) {
                        $displaydata = $answer->a_text;
                    } else {
                        $answerdata = $DB->get_record('question_answers', array('id' => $answer->answer_id));
                        $displaydata = $answerdata->answer;
                    }
                }
                $displaydata = trim(strip_tags($displaydata));
                if (strlen($displaydata) > 40) {
                    echo "<td style=\"word-wrap: break-word;\"><span title=\"".$displaydata."\" class=\"tooltip\">";
                    echo substr(trim(strip_tags($displaydata)), 0, 40)."</span></td>\n";
                } else {
                    echo "<td style=\"word-wrap: break-word;\">".$displaydata."</td>\n";
                }
            }
        }
        echo "</tr></tbody>\n";
    }
}

echo "</table>\n";
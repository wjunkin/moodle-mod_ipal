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
 * This script provides the quiz spreadsheet view for the teacher.
 *
 * @package    mod_quiz
 * @copyright  2016 W. F. Junkin, Eckerd College, http://www.eckerd.edu
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once('../../../config.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');
$id = optional_param('id', 0, PARAM_INT);
$q = optional_param('q', 0, PARAM_INT);
$evaluate = optional_param('evaluate', 0, PARAM_INT);
$showkey = optional_param('showkey', 0, PARAM_INT);
$mode = optional_param('mode', '', PARAM_ALPHA);

if ($id) {
    if (!$cm = get_coursemodule_from_id('quiz', $id)) {
        print_error('invalidcoursemodule');
    }
    if (!$course = $DB->get_record('course', array('id' => $cm->course))) {
        print_error('coursemisconf');
    }
    if (!$quiz = $DB->get_record('quiz', array('id' => $cm->instance))) {
        print_error('invalidcoursemodule');
    }

} else {
    if (!$quiz = $DB->get_record('quiz', array('id' => $q))) {
        print_error('invalidquizid', 'quiz');
    }
    if (!$course = $DB->get_record('course', array('id' => $quiz->course))) {
        print_error('invalidcourseid');
    }
    if (!$cm = get_coursemodule_from_instance("quiz", $quiz->id, $course->id)) {
        print_error('invalidcoursemodule');
    }
}

$url = new moodle_url('/mod/quiz/liveview/gridview.php', array('id' => $cm->id, 'evaluate' => $evaluate, 'showkey' => $showkey));
if ($mode !== '') {
    $url->param('mode', $mode);
}
$PAGE->set_url($url);
$quizid = $quiz->id;

require_login($course, false, $cm);

$contextinstance = context_module::instance($cm->id);
$quizcontextid = $contextinstance->id;
if (!(has_capability('mod/quiz:viewreports', $contextinstance))) {
    echo "\n<br />You must be authorized to access this site";
    exit;
}


/**
 * Return the number of users who have submitted answers to this quiz instance.
 *
 * @param int $quizid The ID for the quiz instance
 * @return array The userids for all the students submitting answers.
 */
function liveview_who_sofar_gridview($quizid) {
    global $DB;

    $records = $DB->get_records('quiz_attempts', array('quiz' => $quizid));

    foreach ($records as $records) {
        $userid[] = $records->userid;
    }
    if (isset($userid)) {
        return(array_unique($userid));
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
function liveview_find_student_gridview($userid) {
     global $DB;
     $user = $DB->get_record('user', array('id' => $userid));
     $name = $user->lastname.", ".$user->firstname;
     return($name);
}
echo "\n<html>\n<head>";
echo "\n<title>Live view spreadsheet</title>";
echo "<link rel=\"stylesheet\" type=\"text/css\" href=\"gridviewstyle.css\" />";
echo "\n</head><body>";
/**
 * This class returns the fractional grade for students answers.
 *
 * For questions that can be graded by the computer program, it returns the fraction associated with
 * the grade assigned to the answer the student has given.
 * A lot of this comes from the question/engine/ scripts.
 * @copyright  2016 W. F. Junkin, Eckerd College, http://www.eckerd.edu
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 **/
class liveview_fraction {
    /**
     * @var question_engine_data_mapper $dm
     */
    public $dm;

    /**
     * Create a new instance of this class. This can be called directly.
     *
     * @param int $qubaid The id of from the question_useages table for this student.
     */
    public function __construct($qubaid) {
        $this->dm = question_engine::load_questions_usage_by_activity($qubaid);
    }

    /**
     * Function to obtain the question from the slot value of the question.
     *
     * @param int $slot The id from the slot table of this question.
     * @return The question object from the row in the question table.
     */
    public function get_question($slot) {
        return $this->dm->get_question($slot);
    }
    /**
     * Function to return the graded responses to the question.
     *
     * @param int $slot The value of the id for this question in the slot table.
     * @param string $myresponse The response that the student gave.
     * @return real The fraction for the answer the student gave.
     */
    public function get_fraction ($slot, $myresponse) {
        $myquestion = $this->dm->get_question($slot);
        $response[0] = 'no summary available';
        if (method_exists($myquestion, 'summarise_response')) {
            $response[0] = $myquestion->summarise_response($myresponse);
        }
        $response[1] = 'NA';
        if (method_exists($myquestion, 'grade_response')
            && is_callable(array($myquestion, 'grade_response'))) {
            $grade = $myquestion->grade_response($myresponse);
            if ($grade[0] == 0) {
                $grade[0] = 0.001;// This is set so that the isset function returns a value of true.
            }
            $response[1] = $grade[0];
        }
        return $response;
    }
}
$slots = liveviewslots($quizid);
$question = liveviewquestion($slots);
$quizattempts = $DB->get_records('quiz_attempts', array('quiz' => $quizid));
// An array, quizattpt, that has all rows from the quiz_attempts table indexed by userid and value of the quizattemptid.
$quizattpt = array();
foreach ($quizattempts as $key => $quizattempt) {
    $quizattpt[$quizattempt->userid] = $quizattempt->uniqueid;// Getting the latest uniqueid for each user.
}
// These arrays are the 'answr' or 'fraction' indexed by userid and questionid.
$stanswers = array();
$stfraction = array();
foreach ($quizattpt as $usrid => $qubaid) {
    $mydm = new liveview_fraction($qubaid);
    $qattempts = $DB->get_records('question_attempts', array('questionusageid' => $qubaid));
    foreach ($qattempts as $qattempt) {
        $myresponse = array();
        $qattemptsteps = $DB->get_records('question_attempt_steps', array('questionattemptid' => $qattempt->id));
        foreach ($qattemptsteps as $qattemptstep) {
            $answers = $DB->get_records('question_attempt_step_data', array('attemptstepid' => $qattemptstep->id));
            foreach ($answers as $answer) {
                $myresponse[$answer->name] = $answer->value;
            }
            if (count($myresponse) > 0) {
                $response = $mydm->get_fraction($qattempt->slot, $myresponse);
                $stanswers[$usrid][$qattempt->questionid] = $response[0];
                $stfraction[$usrid][$qattempt->questionid] = $response[1];
            }
        }
    }
}

/**
 * Return the greatest time that a student responded to a given question in a quiz.
 *
 * This is used to determine if the teacher view of the graph should be refreshed.
 * @param int $qid The ID for the question.
 * @param int $quizcontextid The ID for the context for this quiz.
 * @return int The integer for the greatest time.
 */
function liveviewquestionmaxtime($qid, $quizcontextid) {
    global $DB;
    $time = $DB->get_records_sql("
        SELECT max(qa.timemodified)
        FROM {question_attempts} qa
        JOIN {question_usages} qu ON qu.id = qa.questionusageid
        WHERE qa.questionid = ? AND qu.contextid = ?", array($qid, $quizcontextid));
    foreach ($time as $key => $tm) {
        $maxtime = $key;
    }
    return $maxtime;
}
/**
 * Return the greatest time that a student responded to a given quiz.
 *
 * This is used to determine if the teacher view of the graph should be refreshed.
 * @param int $quizcontextid The ID for the context for this quiz.
 * @return int The integer for the greatest time.
 */
function liveviewquizmaxtime($quizcontextid) {
    global $DB;
    $quiztime = $DB->get_records_sql("
        SELECT max(qa.timemodified)
        FROM {question_attempts} qa
        JOIN {question_usages} qu ON qu.id = qa.questionusageid
        WHERE qu.contextid = ?", array($quizcontextid));
    foreach ($quiztime as $qkey => $qtm) {
        $qmaxtime = intval($qkey) + 1;
    }
    return $qmaxtime;
}
/**
 * Function to display the color key when answer grades are shown.
 */
function liveviewshowkey() {
    echo "Fraction colors\n<br />";
    echo "<table border=\"1\" width=\"100%\">\n";
    $head = "<tr>";
    for ($i = 0; $i < 11; $i++) {
        $myfraction = $i / 10;
        $head .= "<td ";
        $greenpart = intval(127 * $myfraction + 128);// Add in as much green as the answer is correct.
        $redpart = 383 - $greenpart;// This is 255 - myfraction*127.
        $head .= "style='background-color: rgb($redpart,$greenpart,126)'";
        $head .= ">$myfraction</td>";
    }
    echo $head."\n</tr></table>\n<br />";
}
if ($showkey) {
    liveviewshowkey();
}
$qmaxtime = liveviewquizmaxtime($quizcontextid);
if ($evaluate) {
    echo "<a href='./gridview.php?id=$id&q=$q&evaluate=0&showkey=$showkey'>Don't Grade answers</a>&nbsp";
} else {
    echo "<a href='./gridview.php?id=$id&q=$q&evaluate=1&showkey=$showkey'>Grade answers</a>&nbsp";
}
if ($showkey) {
    echo "<a href='./gridview.php?id=$id&q=$q&evaluate=$evaluate&showkey=0'>Don't show grading key</a><br />\n";
} else {
    echo "<a href='./gridview.php?id=$id&q=$q&evaluate=$evaluate&showkey=1'>Show grading key</a><br />\n";
}
echo "Responses\n<br />";
echo "<table border=\"1\" width=\"100%\" id='timemodified' name=$qmaxtime>\n";
echo "<thead><tr>";

// If anonymous, exclude the column "name" from the table.
$quiz->anonymous = 0;
if (!$quiz->anonymous) {
    echo "<th>Name</th>\n";
}

foreach ($slots as $key => $slotvalue) {
    echo "<th style=\"word-wrap: break-word;\">";
    if (isset($question['name'][$key])) {
        echo substr(trim(strip_tags($question['name'][$key])), 0, 80);
    }
    echo "</th>\n";
}
echo "</tr>\n</thead>\n";

$users = liveview_who_sofar_gridview($quizid);
/**
 * Function to get the questionids as the keys to the $slots array so we know all the questions in the quiz.
 * @param int $quizid The id for this quiz.
 * @return array $slots The slot values (from the quiz_slots table) indexed by questionids.
 */
function liveviewslots($quizid) {
    global $DB;
    $slots = array();
    $myslots = $DB->get_records('quiz_slots', array('quizid' => $quizid));
    foreach ($myslots as $key => $value) {
        $slots[$value->questionid] = $value->slot;
    }
    return $slots;
}
/**
 * Function to get the qtype, name, questiontext for each question.
 * @param array $slots and array of slot ids indexed by question ids.
 * @return array $question. A doubly indexed array giving qtype, qname, and qtext for the questions.
 */
function liveviewquestion($slots) {
    global $DB;
    $question = array();
    foreach ($slots as $questionid => $slotvalue) {
        $myquestion = $DB->get_record('question', array('id' => $questionid));
        $question['qtype'][$questionid] = $myquestion->qtype;
        $question['name'][$questionid] = $myquestion->name;
        $question['questiontext'][$questionid] = $myquestion->questiontext;
    }
    return $question;
}

// Create the table.
if (isset($users)) {
    foreach ($users as $user) {
        echo "<tbody><tr>";

        // If anonymous, exlude the student name data from the table.
        if (!$quiz->anonymous) {
            echo "<td>".liveview_find_student_gridview($user)."</td>\n";
        }
        foreach ($slots as $questionid => $slotvalue) {
            if (($questionid != "") and ($questionid != 0)) {
                if (isset($stanswers[$user][$questionid])) {
                    if (count($stanswers[$user][$questionid]) == 1) {
                        $answer = $stanswers[$user][$questionid];
                    } else {
                        $answer = '';
                        foreach ($stanswers[$user][$questionid] as $key => $value) {
                            $answer .= $key."=".$value."; ";
                        }
                    }
                } else {
                    $answer = ' ';
                }
            }
            echo "<td ";
            if ($evaluate) {
                if (isset($stfraction[$user][$questionid]) and (!($stfraction[$user][$questionid] == 'NA'))) {
                    $myfraction = $stfraction[$user][$questionid];
                    $greenpart = intval(127 * $myfraction + 128);// Add in as much green as the answer is correct.
                    $redpart = 383 - $greenpart;// This is 255 - myfraction*127.
                    echo "style='background-color: rgb($redpart,$greenpart,126)'";
                } else {
                    echo '';
                }
            }
            if (strlen($answer) < 40) {
                echo ">".htmlentities($answer)."</td>";
            } else {
                // Making a tooltip out of a long answer. The htmlentities function leaves single quotes unchanged.
                $safeanswer = htmlentities($answer);
                echo "><div title=\"$safeanswer\">".substr(trim(strip_tags($answer)), 0, 40)."</div></td>";
            }
        }
        echo "</tr></tbody>\n";
    }
}
echo "</table>\n";

// Javascript to refresh the page if the contents of the table change.
echo "\n\n<script type=\"text/javascript\">\nvar http = false;\nvar x=\"\";
        \n\nif(navigator.appName == \"Microsoft Internet Explorer\")
        {\nhttp = new ActiveXObject(\"Microsoft.XMLHTTP\");\n} else {\nhttp = new XMLHttpRequest();}";
    echo "\n\nfunction replace() { ";
    $t = '&t='.time();
    echo "\n x=document.getElementById('timemodified');";
    echo "\n myname = x.getAttribute('name');";
    echo "\nvar t=setTimeout(\"replace()\",10000);\nhttp.open(\"GET\", \"graphicshash.php?id=".$id.$t."\", true);";
    echo "\nhttp.onreadystatechange=function() {\nif(http.readyState == 4) {";
    echo "\n if(parseInt(http.responseText) != parseInt(myname)){";
    echo "\n    location.reload(true);";
    echo "\n}\n}\n}";
    echo "\n http.send(null);";
    echo "\n}\nreplace();";
echo "\n</script>";

/**
 * Java script for checking to see if the chart need to be updated.
 *
 * @param int $id The cmid of this quiz instance.
 */
function quiz_java_graphupdate($id) {

    echo "\n\n<script type=\"text/javascript\">\nvar http = false;\nvar x=\"\";
        \n\nif(navigator.appName == \"Microsoft Internet Explorer\")
        {\nhttp = new ActiveXObject(\"Microsoft.XMLHTTP\");\n} else {\nhttp = new XMLHttpRequest();}";
    echo "\n\nfunction replace() { ";
    $t = '&t='.time();
    echo "\nvar t=setTimeout(\"replace()\",10000);\nhttp.open(\"GET\", \"graphicshash.php?id=".$id.$t."\", true);";
    echo "\nhttp.onreadystatechange=function() {\nif(http.readyState == 4) {\nif(http.responseText != x){";
    echo "\nx=http.responseText;\n";
    echo "}\n}\n}\nhttp.send(null);\n}\nreplace();\n</script>";

}
echo "\n</body></html>";

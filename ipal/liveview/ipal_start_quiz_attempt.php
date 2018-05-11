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
 * This script deals with starting a new attempt at a quiz for an IPAL session.
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
// The layout value will be 0 until the quiz_attempt has been started.
$makelayout = optional_param('makelayout', 0, PARAM_INT);
if (!$cm = get_coursemodule_from_id('quiz', $cmid)) {
    print_error('invalidcoursemodule');
}
if (!$course = $DB->get_record('course', array('id' => $cm->course))) {
    print_error("coursemisconf");
}

require_login($course, true, $cm);
$userid = $USER->id;
$quizid = $cm->instance;
$attemptid = 0;
if ($attempts = $DB->get_records('quiz_attempts', array('quiz' => $quizid, 'userid' => $userid, 'state' => 'inprogress'))) {
    foreach ($attempts as $attempt) {
        $attemptid = $attempt->id;
    }
}

if ($attemptid > 0) {
    if ($makelayout == 1) {
        $qslot = 1;
        $activequestion = $DB->get_record('quiz_active_questions', array('quiz_id' => $quizid));
        $questionid = $activequestion->question_id;
        if ($questionid == -1) {
            // There is no active question.
            $qslot = 1;
        } else {
            $slots = $DB->get_records('quiz_slots', array('quizid' => $quizid, 'questionid' => $questionid));
            foreach ($slots as $slot) {
                $qslot = $slot->slot;
            }
        }
        $layout = ",$qslot,0";
        for ($n = 1; $n < 5; $n++) {
            $layout .= ",$qslot,0".$layout;
        }
        $layout = $qslot.",0".$layout;
        $attemptlayout = new stdClass();
        $attemptlayout->id = $attemptid;
        $attemptlayout->layout = $layout;
        $DB->update_record('quiz_attempts', $attemptlayout);
        
            
    }
    $location = $CFG->wwwroot."/mod/ipal/liveview/quiz_iframe.php?quizid=$quizid";
    redirect($location);
    exit;
} else {
    echo "\n<br /><iframe src=\"ipalstartattempt.php?cmid=$cmid\" id = 'ipal_iframe1' width = '100%' height='100%'>Starting quiz attempt.</iframe>";
    quiz_java_session_inprogress($quizid, $userid, $cmid);
}


/**
 * Java script for checking to see if there is a uiz_attempt inprogress.
 * This script is used to refresh the iframe once the session has started.
 *
 * @param int $quizid The id of this quiz instance.
 * @param int $userid The id of this user.
 */
function quiz_java_session_inprogress($quizid, $userid, $cmid) {
    echo "\n\n<script type=\"text/javascript\">\nvar http = false;\nvar x=\"\";\nvar myCount=0;
        \n\nif(navigator.appName == \"Microsoft Internet Explorer\")
        {\nhttp = new ActiveXObject(\"Microsoft.XMLHTTP\");\n} else {\nhttp = new XMLHttpRequest();}";
    echo "\n\nfunction replace() {\nvar t=setTimeout(\"replace()\",3000)";
    echo "\nhttp.open(\"GET\", \"inprogress_attempt.php?quizid=".$quizid."&userid=".$userid."\", true);";
    echo "\nhttp.onreadystatechange=function() {\nif(http.readyState == 4) {";
    echo "\nx=http.responseText;";
    echo "\nif(x > 0 && myCount > 1 && myCount < 10){\n";
    echo "window.location.href=\"ipal_start_quiz_attempt.php?cmid=$cmid&makelayout=1\";\n";
    echo "}\nx=http.responseText;}\n}\nhttp.send(null);\nmyCount++;}\n\nreplace();\n</script>";
}

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
 * Prints an iframe for students with browsers to access quizzes that are using polling.
 *
 *
 * @package   mod_quiz
 * @copyright 2018 w. F. Junkin, Eckerd College (http://www.eckerd.edu)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


require_once(dirname(dirname(dirname(dirname(__FILE__)))).'/config.php');

$quizid = optional_param('quizid', 0, PARAM_INT);

if ($quizid) {
    $quiz  = $DB->get_record('quiz', array('id' => $quizid), '*', MUST_EXIST);
    $course     = $DB->get_record('course', array('id' => $quiz->course), '*', MUST_EXIST);
    $cm         = get_coursemodule_from_instance('quiz', $quiz->id, $course->id, false, MUST_EXIST);
} else {
    error('You must specify a course_module ID or an instance ID');
    exit;
}
require_login($course, true, $cm);

$context = context_module::instance($cm->id);

// Log this request.
$params = array(
    'objectid' => $quiz->id,
    'context' => $context
);
$event = \mod_quiz\event\course_module_viewed::create($params);
$event->add_record_snapshot('quiz', $quiz);
$event->trigger();

if (has_capability('mod/quiz:attempt', $context)) {
    $userid = $USER->id;
} else {
    echo "\n<br />You are not authorized to attempt this quiz.";
    exit;
}

/**
 * Java script for checking to see if the Question has changed.
 * This scriptrefreshes the student screen when polling has stopped or a question has been sent.
 *
 * @param int $quizid The id of this quiz instance.
 */
function quiz_java_questionupdate($quizid, $url) {
    global $DB;
    global $USER;
    if ($configs = $DB->get_record('config', array('name' => 'sessiontimeout'))) {
        $timeout = intval($configs->value);
    } else {
        $timeout = 7200;
    }
    echo "\n\n<script type=\"text/javascript\">\nvar http = false;\nvar x=\"\";\nvar myCount=0;
        \n\nif(navigator.appName == \"Microsoft Internet Explorer\")
        {\nhttp = new ActiveXObject(\"Microsoft.XMLHTTP\");\n} else {\nhttp = new XMLHttpRequest();}";
    echo "\n\nfunction replace() {\nvar t=setTimeout(\"replace()\",3000)";
    echo "\nhttp.open(\"GET\", \"current_question.php?quizid=".$quizid."\", true);";
    echo "\nhttp.onreadystatechange=function() {\nif(http.readyState == 4) {";
    echo "\n\nif(http.responseText != x && myCount > 1 && myCount < $timeout/3){\n";
    echo "document.getElementById('quizQuestionIframe').src=\"".$url."\"\n";
    echo "}\nx=http.responseText;}\n}\nhttp.send(null);\nmyCount++;}\n\nreplace();\n</script>";
}
if ($quizattempts = $DB->get_records('quiz_attempts', array('quiz' => $quizid, 'userid' => $userid, 'state' => 'inprogress'))) {
    foreach ($quizattempts as $attempt) {
        $attemptid = $attempt->id;
    }
    // Everything, including the layout in the quiz_attempts table, should be good to go.
    $url = $CFG->wwwroot.'/mod/quiz/attempt.php?attempt='.$attemptid.'&page=1';
    quiz_java_questionupdate($quizid, $url);
    echo "\n<br />Please click <a href='".$CFG->wwwroot."'>here</a> to exit polling for this quiz.";
    echo "\n<br />";
    echo "\n<iframe src='$url' width='100%' height='100%' id='quizQuestionIframe'>".$url."</iframe>";
    
} else {
    // This user has not attempted this quiz before, so we must create a quiz attempt.
    // We must create the correct layout in the quiz_attempts page and then return to this page.
    $location = $CFG->wwwroot."/mod/ipal/liveview/ipal_start_quiz_attempt.php?cmid=".$cm->id;
    redirect($location);
    exit;
}

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
 * Prints out the id for the current question (the id for the quiz_active_question based on the id of the quiz instance
 *
 * This script, with this one function, may be run thousands of times a second,
 * since it is run every 3 seconds by the browser on every student involved in any quiz polling session.
 * Therefore, it is very important that it run very fast and not overload the server.
 * For this reason, the GET method is used to obtain the quizid instead of the optional_param function.
 * If someone uses this function outside of its intended use, the only information returned will be the
 * question id of the current question for that quiz. They will not have access to the question itself.
 * @package    mod_quiz
 * @copyright  2018 W. F. Junkin, Eckerd College, http://www.eckerd.edu
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(dirname(__FILE__)))).'/config.php');
// This program needs to run very quickly to let students know when the current question has changed.
// Therefore it does not take time to check on login and
// it uses the inval($_GET['quizid']) which is about 40 times faster than optional_param('quizid', 0, PARAM_INT).
// It only returns the id number for the current question for a specific quiz id.
$quizid = intval($_GET['quizid']);
$quiz = $DB->get_record('quiz', array('id' => $quizid));
$course = $DB->get_record('course', array('id' => $quiz->course), '*', MUST_EXIST);
$cm = get_coursemodule_from_instance('quiz', $quiz->id, $course->id, false, MUST_EXIST);
require_login($course, true, $cm);
if ($DB->record_exists('quiz_active_questions', array('quiz_id' => $quizid))) {
    $question = $DB->get_record('quiz_active_questions', array('quiz_id' => $quizid));
    echo $question->id;
} else {
    echo -1;
}

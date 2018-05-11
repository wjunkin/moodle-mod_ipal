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
 * Prints out the id for the quiz_attempt for this user and this quiz where the state is inprogress.
 *
 * This script will be polled a few times as a student starts and in-class polling session that uses a quiz.
 * If there is a quiz attempt the attemptid will be returned. Otherwise, only a 0 will be returned.
 * Once the quiz attempt has started this script will no longer be polled.
 * @package    mod_quiz
 * @copyright  2018 W. F. Junkin, Eckerd College, http://www.eckerd.edu
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(dirname(__FILE__)))).'/config.php');
$quizid = optional_param('quizid', 0, PARAM_INT);
$userid = optional_param('userid', 0, PARAM_INT);
$quiz = $DB->get_record('quiz', array('id' => $quizid));
$course = $DB->get_record('course', array('id' => $quiz->course), '*', MUST_EXIST);
$cm = get_coursemodule_from_instance('quiz', $quiz->id, $course->id, false, MUST_EXIST);
require_login($course, true, $cm);
if ($DB->record_exists('quiz_attempts', array('quiz' => $quizid, 'userid' => $userid, 'preview' => 0, 'state' => 'inprogress'))) {
    $attempts = $DB->get_records('quiz_attempts', array('quiz' => $quizid, 'userid' => $userid, 'preview' => 0, 'state' => 'inprogress'));
    foreach ($attempts as $attempt) {
        $attemptid = $attempt->id;
    }
    echo $attemptid;
} else {
    echo 0;
}

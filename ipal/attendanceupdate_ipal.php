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
 * Provides utilities and interface to add resources from the Moodle EJS module to questions.
 *
 * @package    mod_ipal
 * @copyright  2012 W. F. Junkin, Eckerd College, http://www.eckerd.edu
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
$cmid = optional_param('cmid', 0, PARAM_INT); // Course_module ID.
$undo = optional_param('UNDO', '', PARAM_TEXT);// If the question should be removed from the ipal list.
if ($cmid) {
    $module = $DB->get_record('modules', array('name' => 'ipal'));
    $coursemodules = $DB->get_record('course_modules', array('id' => $cmid, 'module' => $module->id));
} else {
    echo "You must supply the cmid value for the IPAL activity.";
    exit;
}
$ipalid = $coursemodules->instance;
$ipal = $DB->get_record('ipal', array('id' => $ipalid));
$course = $DB->get_record('course', array('id' => $ipal->course), '*', MUST_EXIST);
$cm = get_coursemodule_from_instance('ipal', $ipal->id, $course->id, false, MUST_EXIST);
require_login($course, true, $cm);
$contextinstance = context_module::instance($cm->id);
$PAGE->set_url('/mod/ipal/ejs_attendance.php', array('id' => $cm->id));
$PAGE->set_title('Taking attendance through the '.$ipal->name.' activity');
$PAGE->set_heading($course->shortname);

// Output starts here.
echo $OUTPUT->header();
// Only authorized people can access this site.
if (!(has_capability('mod/ipal:instructoraccess', $contextinstance))) {
    echo "\n<br />You must be authorized to access this site";
    echo $OUTPUT->footer();
    exit;
}

echo "Click <a href='".$CFG->wwwroot."/mod/ipal/edit.php?cmid=$cmid'>here</a> to return to IPAL activity.";
$courseid = $coursemodules->course;
// There must be at least one attendance module in the course.
if (!$attendanceinstance = $DB->get_records('attendance', array('course' => $courseid))) {
    echo "\n<br />You must create at least one EJS App activity in this course before you can add it to questions.";
    exit;
}

// The ipal instance must allow mobile responses so that the teacher can see the access code.
if (!($ipal->mobile > 0)) {
    echo "\n<br />You must allow mobile responses in this ipal instance so that the access code is displayed to the teacher.";
    exit;
}

$qid = optional_param('qid', '0', PARAM_INT);
// Update attendance if requested.
$update_attendance = optional_param('update_attendance', 'No', PARAM_TEXT);
if ($update_attendance == 'Yes') {
    $questionid = optional_param('questionid', '0', PARAM_INT);
    $sessid = optional_param('sessid', '0', PARAM_INT);
    $responsecount = $DB->count_records('ipal_answered', array('question_id' => "$questionid", 'ipal_id' => "$ipalid"));
    if ($responsecount > 0) {
        $attendance_session = $DB->get_record('attendance_sessions', array('id' => $sessid));
        $attendsessionid = $attendance_session -> attendanceid;
        if (!($attendance_status = $DB->get_record('attendance_statuses', array('attendanceid' => $attendsessionid, 'acronym' => 'P')))) {
            echo "\n<br />No attendance grade has a 'P' acronum. Ipal can't take attendance.";
            exit;
        }
        $attendance_statusid = $attendance_status->id;
        //$sql = "'SELECT * FROM {attendance_statuses} WHERE attendanceid = ? ORDER BY grade DESC'";, array( $attendsessionid )";
        $attendance_statuses = $DB->get_records_sql('SELECT * FROM {attendance_statuses} 
            WHERE attendanceid = ? ORDER BY grade DESC', array( $attendsessionid ));
        $statusset = array();
        foreach ($attendance_statuses as $value) {
            $statusset[] = $value->id;
        }
        $statsst = implode(",", $statusset);
        $accesscode = $ipalid.fmod($ipal->timecreated, 100);
        $responses = $DB->get_records('ipal_answered', array('question_id' => "$questionid", 'ipal_id' => "$ipalid"));
        $correctcode = 0;
        echo "\n<br />The following students have been marked present:\n<br />";
        foreach ($responses as $response) {
            if (preg_match("/$accesscode/", $response->a_text, $matches)) {
                $studentname = $DB->get_record('user', array('id' => $response->user_id));
                echo $studentname->firstname." ".$studentname->lastname."; ";
                $correctcode ++;
                if ($marked = $DB->get_record('attendance_log', array('sessionid' => $sessid, 'studentid' => $response->user_id))) {
                    $regrade = $DB->set_field('attendance_log', 'statusid', $attendance_statusid, array('sessionid' => $sessid, 'studentid' => $response->user_id));
                    $updatesession = $DB->set_field('attendance_sessions', 'lasttaken', time(), array('id' => $sessid));
                } else {
                    $attendance_log_insert = new stdClass();
                    $attendance_log_insert->sessionid = $sessid;
                    $attendance_log_insert->studentid = $response->user_id;
                    $attendance_log_insert->statusid = $attendance_statusid;
                    $attendance_log_insert->statusset = $statsst;
                    $attendance_log_insert->timetaken = time();
                    $attendance_log_insert->takenby = $USER->id;
                    $lastinsertid = $DB->insert_record('attendance_log', $attendance_log_insert);
                    $updatesession = $DB->set_field('attendance_sessions', 'lasttaken', time(), array('id' => $sessid));
                }
            }
        }
        echo "\n<br />$responsecount answered the question ";
        $question = $DB->get_record('question', array('id' => $questionid));
        echo "\n<br />".$question->questiontext;
        echo "\n<br />and $correctcode gave the correct access code.\n<br />";
    } else {
        echo "\n<br />No students have responded yet to this question;";
        $question = $DB->get_record('question', array('id' => $questionid));
        echo "\n<br />".$question->questiontext."\n<br />";
    }
    
}
$questions = $ipal->questions;
$question_list = explode(",", $questions);
echo "\n<br />Here are the questions.";
foreach ($question_list as $key => $questionid) {
    if ($question = $DB->get_record('question', array('id' => $questionid))) {
        if (preg_match("/Attendance question for session (\d+)/", $question->name, $matches)) {
            $sessid = $matches[1];// The id for the attendance session in the attendance module.
            $responsecount = $DB->count_records('ipal_answered', array('question_id' => "$questionid", 'ipal_id' => "$ipalid"));
            echo "\n<br /><br />$responsecount students have responded to this question:";
            echo "\n<br />".$question->questiontext;
            echo "<form action='attendanceupdate_ipal.php'>";
            echo "<input type='hidden' name='cmid' value='$cmid'>";
            echo "<input type='hidden' name='questionid' value='$questionid'>";
            echo "<input type='hidden' name='sessid' value='$sessid'>";
            echo "Update this attendance record? <input type='submit' name='update_attendance' value='Yes'>";
            echo "</form>";
        }
    }
}


echo $OUTPUT->footer();
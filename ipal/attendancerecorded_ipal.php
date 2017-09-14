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
 * Provides utilities and interface to use ipal responses to update attendance record.
 *
 * @package    mod_ipal
 * @copyright  2012 W. F. Junkin, Eckerd College, http://www.eckerd.edu
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
$ipalid = optional_param('ipalid', 0, PARAM_INT); // Course_module ID.
if (!($ipalid)) {
    echo "You must supply the id value for the IPAL activity.";
    exit;
}
$ipal = $DB->get_record('ipal', array('id' => $ipalid));
$course = $DB->get_record('course', array('id' => $ipal->course), '*', MUST_EXIST);
$cm = get_coursemodule_from_instance('ipal', $ipal->id, $course->id, false, MUST_EXIST);
require_login($course, true, $cm);
$contextinstance = context_module::instance($cm->id);
$PAGE->set_url('/mod/ipal/attendancerecorded_ipal.php', array('cmid' => $cm->id));
$PAGE->set_title('Attendance recorded through the '.$ipal->name.' activity');
$PAGE->set_heading($course->shortname);

// Output starts here.
echo $OUTPUT->header();
// Only authorized people can access this site.
if (!(has_capability('mod/ipal:instructoraccess', $contextinstance))) {
    echo "\n<br />You must be authorized to access this site";
    echo $OUTPUT->footer();
    exit;
}

echo "Click <a href='".$CFG->wwwroot."/mod/ipal/view.php?id=$cm->id'>here</a> to return to IPAL activity.";
$courseid = $course->id;
// There must be at least one attendance module in the course.
if (!$attendanceinstance = $DB->get_records('attendance', array('course' => $courseid))) {
    echo "\n<br />You must create at least one Attendance activity in this course before you can add attendance questions.";
    exit;
}

$qid = optional_param('qid', '0', PARAM_INT);
// Update attendance if requested.
$updateattendance = optional_param('update_record', 'No', PARAM_TEXT);
if ($updateattendance == 'Update_this_attendance_record') {
    $sessid = optional_param('sessid', '0', PARAM_INT);
    $responsecount = $DB->count_records('ipal_answered', array('question_id' => "$qid", 'ipal_id' => "$ipalid"));
    if ($responsecount > 0) {
        $attendancesession = $DB->get_record('attendance_sessions', array('id' => $sessid));
        // Verify session belongs to this course (and thus, this person has permission to update attendance module).
        $attendsessionid = $attendancesession->attendanceid;
        $attendance = $DB->get_record('attendance', array('id' => $attendsessionid));
        if ($attendance->course <> $courseid) {
            echo "\n<br />This attendance session does not belong in this course.";
            echo "\n<br />Please use the back button";
            echo "</body></html>";
            exit;
        }
        if (!($attendancestatus = $DB->get_record('attendance_statuses',
            array('attendanceid' => $attendsessionid, 'acronym' => 'P')))) {
            echo "\n<br />No attendance grade has a 'P' acronum. Ipal can't take attendance.";
            exit;
        }
        $attendancestatusid = $attendancestatus->id;
        $attendancestatuses = $DB->get_records_sql('SELECT * FROM {attendance_statuses}
            WHERE attendanceid = ? ORDER BY grade DESC', array( $attendsessionid ));
        $statusset = array();
        foreach ($attendancestatuses as $value) {
            $statusset[] = $value->id;
        }
        $statsst = implode(",", $statusset);
        $accesscode = $ipalid.fmod($ipal->timecreated, 100);
        $responses = $DB->get_records('ipal_answered', array('question_id' => "$qid", 'ipal_id' => "$ipalid"));
        $correctcode = 0;
        echo "\n<br />The following students have been marked present:\n<br /><br />";
        $studentnames = '';
        foreach ($responses as $response) {
            if (preg_match("/$accesscode/", $response->a_text, $matches)) {
                $studentname = $DB->get_record('user', array('id' => $response->user_id));
                $studentnames = $studentname->firstname." ".$studentname->lastname."; ".$studentnames;
                $correctcode ++;
                if ($marked = $DB->get_record('attendance_log', array('sessionid' => $sessid, 'studentid' => $response->user_id))) {
                    $regrade = $DB->set_field('attendance_log', 'statusid', $attendancestatusid,
                        array('sessionid' => $sessid, 'studentid' => $response->user_id));
                    $updatesession = $DB->set_field('attendance_sessions', 'lasttaken', time(), array('id' => $sessid));
                } else {
                    $attendanceloginsert = new stdClass();
                    $attendanceloginsert->sessionid = $sessid;
                    $attendanceloginsert->studentid = $response->user_id;
                    $attendanceloginsert->statusid = $attendancestatusid;
                    $attendanceloginsert->statusset = $statsst;
                    $attendanceloginsert->timetaken = time();
                    $attendanceloginsert->takenby = $USER->id;
                    $lastinsertid = $DB->insert_record('attendance_log', $attendanceloginsert);
                    $updatesession = $DB->set_field('attendance_sessions', 'lasttaken', time(), array('id' => $sessid));
                }
            }
        }
        echo $studentnames;
        echo "\n<br /><br />$responsecount answered the question ";
        echo "and $correctcode gave the correct access code.\n<br />";
        $question = $DB->get_record('question', array('id' => $qid));
        echo "\n<br />".$question->questiontext;
    } else {
        echo "\n<br />No students have responded yet to this question;";
        $question = $DB->get_record('question', array('id' => $qid));
        echo "\n<br />".$question->questiontext."\n<br />";
    }

}

echo $OUTPUT->footer();
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
    echo "\n<br />You must create at least one Attendance activity in this course before you can add attendance questions.";
    exit;
}

// The ipal instance must allow mobile responses so that the teacher can see the access code.
if (!($ipal->mobile > 0)) {
    echo "\n<br />You must allow mobile responses in this ipal instance so that the access code is displayed to the teacher.";
    exit;
}

// Remove attendance question from ipal if the teacher changes her or his mind.
if ($undo == 'UNDO') {
    $qid = optional_param('qid', '0', PARAM_INT);
    if ($qid > 0) {
        $questions = $ipal->questions;
        $remove = $qid.","; 
        $newquestions = preg_replace("/$remove/",'',$questions);
        $result = $DB->set_field('ipal', 'questions', $newquestions, array('id' => $ipal->id));
        if ($result) {
            echo "\n<br />question $qid removed";
        } else {
            echo "\n<br />question $qid not removed.";
        }
    }
}

echo "\n<form action='add_attendancequestion.php'>";
echo "\n<input type='hidden' name='cmid' value='$cmid'>";
// Getting the attendance sessions from the attendance module.
$sessioncount = 0;// Used to check if there is an attendance session.
foreach ($attendanceinstance as $attendanceid => $value) {
    $sessions = $DB->get_records('attendance_sessions', array('attendanceid' => $attendanceid));
    foreach ($sessions as $session){
        // There is an attendance session.
        $sessioncount++;
        // Date of the session in the attendance module.
        $date = $session->sessdate;
        // Id of the session in the attendance module.
        $sessid = $session->id;
        echo "\n<br /><input type='radio' name='attendsessionid' value='$sessid'>";
        echo "Are you here today (".date('D, d F Y h:i',$date).")? 
            If so, please type the posted access code in the text box and submit it so that you can be counted present.";
    }
}
if ($sessioncount) {
    echo "\n<br />Please select the Attendance Session and then ";
    echo "\n<br />click below to add the attendance question to this ipal activity (".$ipal->name.").<br />\n";
} else {
    echo "\n<br />You must create at least one attendance session in an Attendance Activity before you can add an Attendance question.";
}
echo "\n<br /><input type='submit' value='add this question to this ipal activity'>";
echo "\n<br /></form>";

// Finish the page.
echo $OUTPUT->footer();
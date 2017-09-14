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
 * Provides utilities and interface to add attendance questions (which are automatically graded) to IPAL activities.
 *
 * @package    mod_ipal
 * @copyright  2012 W. F. Junkin, Eckerd College, http://www.eckerd.edu
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
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
$PAGE->set_url('/mod/ipal/attendancequestion_ipal.php', array('id' => $cm->id));
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

echo "\n<br /><br />When you select one of the questions below, ipal will create a question (an Attendance Question)
    for students to answer to indicate their presence in the class.
    \n<br />This Attendance Question requires students to enter the attendance code.
    The attendance code is displayed on the teacher's computer beside the Attendance Question when it is the active question.
    You (the teacher) can display this attendance code from your computer or give it to the class in another way.
    Thus, the student must be present in class to know what number to enter.
    \n<br />The Attendance Question can be sent to the class at any time during this ipal activity.
    \n<br />Polling can be started and stopped at any time.";
echo "\n<br />Attendance Questions in this ipal activity have an'Update this attendance record' link
    at the end of the Attendance Question. Once some (or all) of the students have answered the Attendance Question,
    you (the teacher) should click on this link. Ipal will automatically examine all the student answers
    and will mark as present every student who has submitted the correct attendance code.
    This can be done while class is going on
    or after class is finished and can be done more than once.";
echo "\n<br />At any time you (the teacher) can go into the attendance module and correct, modify, or augment
    the record that the ipal program has placed in the attendance module.
    For example, if some students have forgotten their smart phones,
    you can mark them present in the attendance module if they hand to you a sheet of paper indicating their presence.";
echo "\n<br />Here is the way an attendance question will look:";
echo "\n<br /><br /><h4>";
echo get_string('attendancequestion', 'ipal')." (the Attendance Date)? ".
   get_string('attendancequestion2', 'ipal');
echo "</h4>";

// Javascript to check and uncheck all boxes.
?>
<script lang='javascript'>
function toggle(source) {
  checkboxes = document.getElementsByName('attendsessionid[]');
  for(var i=0, n=checkboxes.length;i<n;i++) {
    checkboxes[i].checked = source.checked;
  }
}
</script>

<?php
echo "\n<br /><form action='edit.php'>";
// Adding a toggle all checkbox.
echo "\n<br /><input type='checkbox' onClick='toggle(this)' /> Create questions for all dates.";
echo "\n<br />You can also check and uncheck individual items by hand.<br /><br />";
echo "\n<input type='hidden' name='cmid' value='$cmid'>";
// Getting the attendance sessions from the attendance module.
$sessioncount = 0;// Used to check if there is an attendance session.
$attenddate = array();// The array for dates for a given session.
$ipalqs = explode(",", $ipal->questions);// Get this list of question ids for this ipal activity.
$ipalsessions = array();// The array of attendance sessions that already have a question in this ipal.
// Find out which sessions are already in ipal questions.
foreach ($ipalqs as $keys => $value) {
    if ($value > 0) {
        $aquestion = $DB->get_record('question', array('id' => $value));
        if (preg_match("/Attendance question for session (\d+)/", $aquestion->name, $matchs)) {
            $ipalsessions[] = $matchs[1];
        }
    }
}
foreach ($attendanceinstance as $attendanceid => $value) {
    echo "\n<br />Attendance Dates for the following Attendance Activity: '".$value->name."'";
    $sessions = $DB->get_records('attendance_sessions', array('attendanceid' => $attendanceid));
    unset($attenddate);
    foreach ($sessions as $session) {
        // There is an attendance session.
        $sessioncount++;
        // Date of the session in the attendance module.
        $date = $session->sessdate;
        // Id of the session in the attendance module.
        $sessid = $session->id;
        $attenddate[$sessid] = $date;
    }
    asort($attenddate);
    foreach ($attenddate as $key => $value) {
        if (!(in_array($key, $ipalsessions))) {
            echo "\n<br /><input type='checkbox' name='attendsessionid[]' value='$key'>";
            echo date('D, d F Y h:i', $value)."\n<br />";
        } else {
            echo "\n<br />".date('D, d F Y h:i', $value)." is already in the ipal activity.\n<br />";
        }
    }

}

if ($sessioncount) {
    echo "\n<br />Please select the Attendance Session(s) and then ";
    echo "\n<br />click below to add the attendance question(s) to this ipal activity (".$ipal->name.").<br />\n";
} else {
    echo "\n<br />You must create at least one attendance session in an Attendance Activity before you can
        add an Attendance question.";
}
echo "\n<br /><input type='submit' name='submit' value='Add these questions to this ipal activity'>";
echo "\n<br /></form>";

// Finish the page.
echo $OUTPUT->footer();
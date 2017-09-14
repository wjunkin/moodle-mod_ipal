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
$PAGE->set_url('/mod/ipal/attendance_ipal.php', array('id' => $cm->id));
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

echo "\n<br />This utility in ipal allows the teacher to use ipal to take attendance in the Moodle attendance module.";
echo "There are three steps. Please click on the step that you wish to perform
    (or click above to return to the ipal session):";
echo "\n<ol>";
    echo "\n<li>Click <a href='".$CFG->wwwroot."/mod/ipal/attendancequestion_ipal.php?cmid=$cmid'>here</a>
        to create a question that students will answer to indicate their presence in the class.";
        echo "\n<br />The question requires them to enter the ipal access code
        (posted or displayed from the teachers computer).</li>";
        echo "\n<li>Once the question is created you (the teacher) should go to the ipal session and, at the appropriate time,
            send the attendance question to the students. You can start and stop polling at any time.</li>";
        echo "\n<li>Once some (or all) of the students have answered the attendance question, you (the teacher) should return to
            this page and click <a href='".$CFG->wwwroot."/mod/ipal/attendanceupdate_ipal.php?cmid=$cmid'>here</a>.
            The page that is displayed will allow you to have ipal check the student responses and
            mark as present every student who has submitted the correct access code. This can be done while class is going on
            or after class is finished and can be done more than once.</li>";
echo "\n</ol>";
echo "\n<br /><br />At any time you can access the attendance module and correct, modify, or augment the record that the ipal
    program has placed in the attendance module. For example, if some students have forgotten their smart phones,
    you can mark them present in the attendance module if they
    hand to you a sheet of paper indicating their presence.</li>";
echo "\n<br />The access code mentioned above is a random number, unique for every ipal instance, that is generated
    whenever mobile responses are allowed and is visible only to the teacher on the ipal web page where questions are
    sent during polling.";

// Finish the page.
echo $OUTPUT->footer();
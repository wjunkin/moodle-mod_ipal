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

 /**
 * Function to generate the random string required to identify questions.
 *
 * @param int $length The length of the string to be generated.
 * @return string The random string.
 */
function ipal_random_string ($length = 15) {
    $pool  = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $pool .= 'abcdefghijklmnopqrstuvwxyz';
    $pool .= '0123456789';
    $poollen = strlen($pool);
    mt_srand ((double) microtime() * 1000000);
    $string = '';
    for ($i = 0; $i < $length; $i++) {
        $string .= substr($pool, (mt_rand() % ($poollen)), 1);
    }
    return $string;
}

 /**
 * Function to add a question to ipal to take attendance in the attendance module.
 *
 * @param int $attendsessionid The id in the attendance module where attendance is being taken.
 * @param int $courseid The id of the course where attendance is being taken.
 * @return int The id of the question that was added.
 */
function add_attendancequestion($attendsessionid, $courseid) {
    global $DB;
    global $USER;
    $course = $DB->get_record('course', array('id' => "$courseid"));
    $shortname = $course->shortname;
    $contextid = $DB->get_record('context', array('instanceid' => "$courseid", 'contextlevel' => '50'));
    $mycontextid = $contextid->id;
    $categories = $DB->get_records_menu('question_categories', array('contextid' => "$mycontextid"));
    $categoryid = 0;
    foreach ($categories as $key => $value) {
        if (preg_match("/Default\ for/", $value)) {
            if (($value == "Default for $shortname") or ($categoryid == 0)) {
                $categoryid = $key;
            }
        }
    }
    if (!($categoryid > 0)) {
        echo "\n<br />Error obtaining categoryid\n<br />";
        return false;
    }
    $qname = 'Attendance question for session '.$attendsessionid;

    $sessions = $DB->get_records('attendance_sessions', array('id' => $attendsessionid));
    foreach ($sessions as $session){
        // Date of the session in the attendance module.
        $date = $session->sessdate;
        // Id of the session in the attendance module.
        $sessid = $session->id;
        $qtext = "Are you here today (".date('D, d F Y h:i',$date).")? 
            If so, please type the posted access code in the text box and submit it so that you can be counted present.";
    }

    $qessaycheckid = $DB->count_records('question', array('category' => "$categoryid", 'name' => "$qname"));
    if ($qessaycheckid > 0) {
        $qessays = $DB->get_records('question', array('category' => "$categoryid", 'name' => "$qname"));
        foreach ($qessays as $qessay) {
            if (isset($qessay->id)) {
                $qid = $qessay->id;
                echo "\n<br />$qtext";
                echo "\n<br /><input type='text'>";
                return $qid;
            } else {
                echo "\n<br />Something is wrong when trying to get a record";
                exit;
            }
        }
    }

    $hostname = 'unknownhost';
    if (!empty($_SERVER['HTTP_HOST'])) {
        $hostname = $_SERVER['HTTP_HOST'];
    } else if (!empty($_ENV['HTTP_HOST'])) {
        $hostname = $_ENV['HTTP_HOST'];
    } else if (!empty($_SERVER['SERVER_NAME'])) {
        $hostname = $_SERVER['SERVER_NAME'];
    } else if (!empty($_ENV['SERVER_NAME'])) {
        $hostname = $_ENV['SERVER_NAME'];
    }
    $date = gmdate("ymdHis");
    $stamp = $hostname .'+'. $date .'+'.ipal_random_string(6);
    $version = $hostname .'+'. $date .'+'.ipal_random_string(6);
    $questionfieldarray = array('category', 'parent', 'name', 'questiontext', 'questiontextformat', 'generalfeedback',
        'generalfeedbackformat', 'defaultgrade', 'penalty', 'qtype', 'length', 'stamp', 'version', 'hidden',
        'timecreated', 'timemodified', 'createdby', 'modifiedby');
    $questionnotnullarray = array('name', 'questiontext', 'generalfeedback');
    $questioninsert = new stdClass();
    $date = gmdate("ymdHis");
    $stamp = $hostname .'+'. $date .'+'.ipal_random_string(6);
    $version = $hostname .'+'. $date .'+'.ipal_random_string(6);
    $questioninsert->category = $categoryid;
    $questioninsert->parent = 0;
    $questioninsert->questiontextformat = 1;
    $questioninsert->generalfeedback = ' ';
    $questioninsert->generalfeedbackformat = 1;
    $questioninsert->defaultgrade = 1;
    $questioninsert->penalty = 0;
    $questioninsert->length = 1;
    $questioninsert->stamp = $stamp;
    $questioninsert->version = $version;
    $questioninsert->hidden = 0;
    $questioninsert->timecreated = time();
    $questioninsert->timemodified = time();
    $questioninsert->createdby = $USER->id;
    $questioninsert->modifiedby = $USER->id;
    $questioninsert->name = "$qname";// Title.
    $questioninsert->questiontext = "$qtext";// Text.
    $questioninsert->qtype = 'essay';
    $lastinsertid = $DB->insert_record('question', $questioninsert);
    $essayoptions = new stdClass;
    $essayoptions->questionid = $lastinsertid;
    $essayoptions->responsefieldlines = 3;
    $essayoptionsid = $DB->insert_record('qtype_essay_options', $essayoptions);
    $qid = $lastinsertid;
    // Print out the text for the teacher to examine.
    echo "\n<br />$qtext";
    echo "\n<br /><input type='text'>";// Provided so that the teacher can see how the form will look. 
    return $qid;
}

require_once('../../config.php');
$cmid = optional_param('cmid', 0, PARAM_INT); // Course_module ID.
$attendsessionid = optional_param('attendsessionid', 0, PARAM_INT); // ID of the attendance session.
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
    echo "\n<br />You must be authorized to access this site.";
    echo $OUTPUT->footer();
    exit;
}

echo "Click <a href='".$CFG->wwwroot."/mod/ipal/edit.php?cmid=$cmid'>here</a> to return to IPAL activity.";
$courseid = $coursemodules->course;
// There must be at least one attendance module in the course.
if (!$attendanceinstance = $DB->get_records('attendance', array('course' => $courseid))) {
    echo "\n<br />You must create at least one EJS App activity in this course before you can add it to questions.
    </body></html>";
    exit;
}

// The ipal instance must allow mobile responses so that the teacher can see the access code.
if (!($ipal->mobile > 0)) {
    echo "\n<br />You must allow mobile responses in this ipal instance so that the access code is displayed to the teacher.
    </body></html>";
    exit;
}

if (isset($attendsessionid) and $attendsessionid > 0) {
    // Adding attendance question to the database and to this ipal instance.
    echo "\n<br />This question has been added to the ipal activity so that attendance can be taken.";
    // The qid value is the id of the question that was added to the database.
    $qid = add_attendancequestion($attendsessionid, $courseid);
    $questions = $ipal->questions;
    $newquestions = $qid.",".$questions;
    $result = $DB->set_field('ipal', 'questions', $newquestions, array('id' => $ipal->id));
    echo "<form action='attendancequestion_ipal.php'>";
    echo "\n<input type='hidden' name='cmid' value='$cmid'>";
    echo "\n<input type='hidden' name='qid' value='$qid'>";
    echo "\n<input type='hidden' name='attendsessionid' value='$attendsessionid'>";
    echo "\n<br />Nope. I have changed my mind. <input type='submit' name='UNDO' value='UNDO'>";
    echo "\n</form>";
    
    echo "\n<form action='edit.php'>";
    echo "\n<input type='hidden' name='cmid' value='$cmid'>";
    echo "\nor<br /><input type='submit' value='Yes, this looks good.'>";
    echo "\n</form>";
} else {
    echo "\n<br />You must select an attendance session so that the appropriate question can be added to the ipal activity.";
    echo "\n<br /> Please use the back button and try again.</body></html>";
    exit;
}

echo $OUTPUT->footer();
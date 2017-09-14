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
 * Provides utilities and interface to use ipal to take attendance in the attendance activity module.
 *
 * @package    mod_ipal
 * @copyright  2012 W. F. Junkin, Eckerd College, http://www.eckerd.edu
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');

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

    $sessions = $DB->get_records('attendance_sessions', array('id' => $attendsessionid));
    foreach ($sessions as $session) {
        // Date of the session in the attendance module.
        $date = $session->sessdate;
        // Id of the session in the attendance module.
        $sessid = $session->id;
        $qtext = get_string('attendancequestion', 'ipal')." (".date('D, d F Y h:i', $date).")? ".
           get_string('attendancequestion2', 'ipal');
        // Name of the session in the attendance module.
        if ($attendname = $DB->get_record('attendance', array('id' => $session->attendanceid))) {
            $qname = 'Attendance question for session '.$attendsessionid.' ('.$attendname->name.')';
        } else {
            echo "error getting attendname when sessid is $sessid";exit;
        }
    }

    $qessaycheckid = $DB->count_records('question', array('category' => "$categoryid", 'name' => "$qname"));
    if ($qessaycheckid > 0) {
        $qessays = $DB->get_records('question', array('category' => "$categoryid", 'name' => "$qname"));
        foreach ($qessays as $qessay) {
            if (!(isset($qessay->id))) {
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
    return $qid;
}

$cmid = optional_param('cmid', 0, PARAM_INT); // Course_module ID.
$attendsessionids = optional_param_array('attendsessionid', 0, PARAM_INT); // ID of the attendance session.
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
if (!(has_capability('mod/ipal:instructoraccess', $contextinstance))) {
    echo "\n<br />You must be authorized to access this site.";
    exit;
}

$courseid = $coursemodules->course;
// There must be at least one attendance module in the course.
if (!$attendanceinstance = $DB->get_records('attendance', array('course' => $courseid))) {
    echo "\n<br />You must create at least one Attendance activity in this course before you can add
        an Attendance Question to this IPAL activity. Please use the back button.
    </body></html>";
    exit;
}

if (!(count($attendsessionids) > 0)) {
    echo "\n<br />You must select an attendance session so that the appropriate question can be added to the ipal activity.";
    echo "\n<br /> Please use the back button and try again.</body></html>";
    exit;
}

$questions = $ipal->questions;
foreach ($attendsessionids as $key => $attendsessionid) {
    if (isset($attendsessionid) and $attendsessionid > 0) {
        $attendsession = $DB->get_record('attendance_sessions', array('id' => $attendsessionid));
        $attendname = $DB->get_record('attendance', array('id' => $attendsession->attendanceid));
        // Adding attendance question to the database and to this ipal instance.
        // The qid value is the id of the question that was added to the database.
        $qid = add_attendancequestion($attendsessionid, $courseid);
        $questions = $qid.",".$questions;
        $result = $DB->set_field('ipal', 'questions', $questions, array('id' => $ipal->id));
    }
}
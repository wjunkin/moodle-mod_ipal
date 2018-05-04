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
 * Prints a particular instance of ipal
 *
 *
 * @package   mod_ipal
 * @copyright 2011 w. F. Junkin, Eckerd College (http://www.eckerd.edu)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


require_once(dirname(dirname(dirname(__FILE__))).'/config.php');

require_once("locallib.php");

$id = optional_param('id', 0, PARAM_INT); // Course_module ID.
$n  = optional_param('n', 0, PARAM_INT);  // Ipal instance ID - it should be named as the first character of the module.
$quizid = optional_param('quizid', 0, PARAM_INT); // The quiz instance id for the quiz that provides the questions for this IPAL activity.
if ($id) {
    $cm         = get_coursemodule_from_id('ipal', $id, 0, false, MUST_EXIST);
    $course     = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
    $ipal  = $DB->get_record('ipal', array('id' => $cm->instance), '*', MUST_EXIST);
} else {
    if ($n) {
        $ipal  = $DB->get_record('ipal', array('id' => $n), '*', MUST_EXIST);
        $course     = $DB->get_record('course', array('id' => $ipal->course), '*', MUST_EXIST);
        $cm         = get_coursemodule_from_instance('ipal', $ipal->id, $course->id, false, MUST_EXIST);
    } else {
        error('You must specify a course_module ID or an instance ID');
    }
}

require_login($course, true, $cm);

$context = context_module::instance($cm->id);

// Log this request.
$params = array(
    'objectid' => $ipal->id,
    'context' => $context
);
$event = \mod_ipal\event\course_module_viewed::create($params);
$event->add_record_snapshot('ipal', $ipal);
$event->trigger();

// Print the page header.

$PAGE->set_url('/mod/ipal/view.php', array('id' => $cm->id));
$PAGE->set_title($ipal->name);
$PAGE->set_heading($course->shortname);

$hascapability = has_capability('mod/ipal:instructoraccess', $context);

// Check if this IPAL activity uses a quiz.
if ($ipal->mobile == 4) {
    if ($hascapability) {
        if ($quizid > 0) {echo "\n<br />debug73 in view.php";
            // This id for the quiz that will be used has been submitted.
            $quiz = $DB->get_record('quiz', array('id' => $quizid));
            $record = new stdClass();
            $record->id = $ipal->id;
            $record->quizid = $quizid;
            $record->intro = $ipal->intro."\n<br />(using quiz: ".$quiz->name.")"; 
            $DB->update_record('ipal', $record);
            // Show description.
            $record1 = new stdClass();
            $record1->id = $cm->id;
            $record1->showdescription = '1';
            $DB->update_record('course_modules', $record1);
            make_label($ipal->id);
            submitform('labelform1');
            $ipal->quizid = $quizid;
        }
        if ($ipal->quizid > 0) {
            // The cm value for this IPAL instance is different from the cm value of the quiz it is using. Therefore use the quizid.
            $quizurl = $CFG->wwwroot.'/mod/ipal/liveview/quizview.php?n='.$ipal->quizid;
            redirect($quizurl);
            exit;
        } else {
            $choosequizurl = $CFG->wwwroot.'/mod/ipal/liveview/choosequiz.php?ipalid='.$ipal->id;
            redirect($choosequizurl);
            exit;
        }
    } else {
        if ($ipal->quizid > 0) {
            // Send students to the quiz iframe.
            $iframeurl = $CFG->wwwroot.'/mod/ipal/liveview/quiz_iframe.php?quizid='.$ipal->quizid;
            redirect($iframeurl);
            exit;
        } else {
            echo "\n<br />Evidently the teacher has not completed the set up of the quiz for polling.";
            exit;
        }
    }
}
function make_label($ipalid) {
    global $DB;
    global $CFG;
    $ipal = $DB->get_record('ipal', array('id' => $ipalid));
    $courseid = $ipal->course;
    $quizid = $ipal->quizid;
    $cm = get_coursemodule_from_instance('quiz', $quizid, $ipal->course, false, MUST_EXIST);
    // The course module id for the quiz.
    $cmid = $cm->id;
    $ipalcm = get_coursemodule_from_instance('ipal', $ipalid, $ipal->course, false, MUST_EXIST);
    $sectionid = $ipalcm->section;
    $coursesection = $DB->get_record('course_sections', array('id' => $sectionid));
    $section = $coursesection->section;
    $labelintro = "<p><img src='".$CFG->wwwroot."/mod/ipal/pix/icon.gif' alt='IPAL icon' width='24px' height='24px' class='img-responsive atto_image_button_left'>";
    $labelintro .= $ipal->name." For Mobile Moodle -- click <a href='".$CFG->wwwroot."/mod/quiz/view.php?id=$cmid'>here</a></p>";
    $labelmodule = $DB->get_record('modules', array('name' => 'label'));
    $labelmoduleid = $labelmodule->id;
    ipal_create_label_form ($section, $courseid, $labelmoduleid, $labelintro);
}
function ipal_create_label_form ($section, $courseid, $labelmoduleid, $labelintro) {
    global $CFG;
    global $USER;
    $form = "\n<form autocomplete=\"off\" action=\"".$CFG->wwwroot."/course/modedit.php\" method=\"post\" id=\"labelform1\">";
    $form .= "\n<input name=\"showdescription\" type=\"hidden\" value=\"1\" />";
    $form .= "\n<input name=\"completionunlocked\" type=\"hidden\" value=\"1\" />";
    $form .= "\n<input name=\"course\" type=\"hidden\" value=\"$courseid\" />";
    $form .= "\n<input name=\"coursemodule\" type=\"hidden\" value=\"\" />";
    $form .= "\n<input name=\"section\" type=\"hidden\" value=\"$section\" />";
    $form .= "\n<input name=\"module\" type=\"hidden\" value=\"$labelmoduleid\" />";
    $form .= "\n<input name=\"modulename\" type=\"hidden\" value=\"label\" />";
    $form .= "\n<input name=\"instance\" type=\"hidden\" value=\"\" />";
    $form .= "\n<input name=\"add\" type=\"hidden\" value=\"label\" />";
    $form .= "\n<input name=\"update\" type=\"hidden\" value=\"0\" />";
    $form .= "\n<input name=\"return\" type=\"hidden\" value=\"0\" />";
    $form .= "\n<input name=\"sr\" type=\"hidden\" value=\"0\" />";
    $form .= "\n<input name=\"sesskey\" type=\"hidden\" value=\"".$USER->sesskey."\" />";
    $form .= "\n<input name=\"_qf__mod_label_mod_form\" type=\"hidden\" value=\"1\" />";
    $form .= "\n<input name=\"mform_isexpanded_id_generalhdr\" type=\"hidden\" value=\"1\" />";
    $form .= "\n<input name=\"mform_isexpanded_id_modstandardelshdr\" type=\"hidden\" value=\"0\" />";
    $form .= "\n<input name=\"mform_isexpanded_id_availabilityconditionsheader\" type=\"hidden\" value=\"0\" />";
    $form .= "\n<input name=\"mform_isexpanded_id_activitycompletionheader\" type=\"hidden\" value=\"0\" />";
    $form .= "\n<input name=\"mform_isexpanded_id_tagshdr\" type=\"hidden\" value=\"0\" />";
    $form .= "\n<input name=\"mform_isexpanded_id_competenciessection\" type=\"hidden\" value=\"0\" />";
    $form .= "\n<input name=\"introeditor[text]\" type=\"hidden\" value=\"".$labelintro."\" />";
    $form .= "\n<input name=\"introeditor[format]\" id=\"menuintroeditorformat\" type=\"hidden\" value=\"1\"/>";
    $form .= "\n<input type=\"hidden\" name=\"introeditor[itemid]\" value=\"35533913\" />";
    $form .= "\n<input type=\"hidden\" name=\"visible\" value=\"1\">";
    $form .= "\n<input type=\"hidden\" name=\"availabilityconditionsjson\" value=\"\">";
    $form .= "\n</form>";
    echo $form;
}
function submitform($formid) {
    echo "<script>";
    echo "alert('An IPAL label for Mobile Moodle users is being created')";
    echo "\ndocument.getElementById(\"$formid\").submit()";
    echo "\n</script>";
   
}
// Output starts here.
echo $OUTPUT->header();
ipal_print_anonymous_message($ipal);
if ($hascapability) {
    ipal_display_instructor_interface($cm->id, $ipal->id);
} else {
    ipal_display_student_interface($ipal->id);
}

// Finish the page.
echo $OUTPUT->footer();
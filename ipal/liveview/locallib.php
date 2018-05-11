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
 * Provides all the functions used by the choosequiz.php script.
 *
 * None of these functions should be needed when IPAL using quizzes is integrated into the quiz module.
 * @package   mod_ipal
 * @copyright 2018 w. F. Junkin, Eckerd College (http://www.eckerd.edu)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(dirname(dirname(dirname(dirname(__FILE__)))).'/config.php');

/**
 * Displays all the quizzes associated with this course so the teacher can choose one to be used for in-class polling.
 *
 * @param int $courseid The id of the course for this instance of in-class polling.
 * @param int $cmid The course module id of this instance of in-class polling.
 * @param int $ipalid The id of this instance of in-class polling.
 * @param int $timecreated The timecreated for this instance of in-class polling, used to verify that the post came from this page.
 */
function quiz_table_form($courseid, $cmid, $ipalid, $timecreated) {
    global $DB;
    global $CFG;
    echo "\n<br />Here is the list of quizzes for this course. Please select one.";
    echo "\n<form action=\"".$CFG->wwwroot.'/mod/ipal/liveview/choosequiz.php?ipalid='.$ipalid."\" method='POST'>";
    // The $timemodified value is used to make sure the post came from this page.
    echo "\n<input type='hidden' name='timecreated' value=".$timecreated." >";
    echo "\n<br /><table border=1>";
    $quizzes = $DB->get_records('quiz', array('course' => $courseid));
    foreach ($quizzes as $quiz) {
        $previewurl = $CFG->wwwroot."/mod/ipal/liveview/quizpreview.php?quizid=".$quiz->id;
        //$tableradio = "\n<tr><td><input type='radio' name='quizid' value=".$quiz->id."></td>";
        $tablename = "<td> ".$quiz->name."</td>";
        $tableinfo = "<td>";
        //echo "\n<tr><td><input type='radio' name='quizid' value=".$quiz->id."></td><td> ".$quiz->name."</td><td>";
        // Adding in icon to preview quiz in a popup window for valid quizzes.
        $preview = true;
        if ($DB->get_records('quiz_attempts', array('quiz' => $quiz->id, 'preview' => 0))) {
            $tableinfo .= " This quiz has attempts. To use this quiz, duplicate it and use the new quiz without attempts.";
            $preview = false;
        }
        $slots = $DB->get_records('quiz_slots', array('quizid' => $quiz->id));
        $page = array();
        foreach ($slots as $slot) {
            $page[$slot->page] = 1;
        }
        if (count($slots) <> count($page)) {
            $tableinfo .= " There is more than one question per page. To use this quiz, you must edit and fix this quiz.";
            $preview = false;
        }
        if ($DB->get_records('quiz_active_questions', array('quiz_id' => $quiz->id))) {
            $tableinfo .= " Another IPAL instance is using this quiz. To use this quiz, duplicate it to get a new quiz not used by another IPAL.";
            $preview = false;
        }
        if ($preview) {
            // Only preview acceptable quizzes.
            $tableinfo .= "\n<a href=\"$previewurl\" target=\"_blank\">";
            $tableinfo .= ipal_create_quiz_preview_icon();
            $tableinfo .= "</a> Preview";
        }
        $tableinfo .= "</td></tr>";
        if ($preview) {
            $tableradio = "\n<tr><td><input type='radio' name='quizid' value=".$quiz->id."></td>";
        } else {
            $tableradio = "\n<tr><td>&nbsp;</td>";
        }
        echo $tableradio.$tablename.$tableinfo;
    }
    echo "\n</table>";
    echo "\n<br /><input type='submit' value='Select and return to course' size='6'> This quiz and its questions will be used for in-class polling.";
    echo "\n</form>";
}

/**
 * This function creates the HTML tag for the preview icon.
 */
function ipal_create_quiz_preview_icon() {
    global $CFG;
    global $PAGE;
    $previewimageurl = $CFG->wwwroot.'/theme/image.php/'.$PAGE->theme->name.'/core/'.$CFG->themerev.'/t/preview';
    $imgtag = "<img alt='Preview quiz' class='smallicon' title='Preview quiz' src='$previewimageurl' />";
    return $imgtag;
}

/**
 * Function to create a no_active_question essay question (telling students there is no active question yet) if it does not exist.
 *
 * The question is created in the default category for the course and the name of the question is no_active_question essay.
 * The function requires the ipal_random_string() function.
 * @param int $courseid The id of the course
 * @return the questionid if the question is found or if the creation is successful.
 */
function ipal_noactiveq_create_($courseid) {
    global $DB;
    global $USER;
    global $COURSE;
    global $CFG;
    $contextid = $DB->get_record('context', array('instanceid' => "$courseid", 'contextlevel' => '50'));
    $mycontextid = $contextid->id;
    $categories = $DB->get_records_menu('question_categories', array('contextid' => "$mycontextid"));
    $categoryid = 0;
    foreach ($categories as $key => $value) {
        if (preg_match("/Default\ for/", $value)) {
            if (($value == "Default for ".$COURSE->shortname) or ($categoryid == 0)) {
                $categoryid = $key;
            }
        }
    }
    if (!($categoryid > 0)) {
        debugging('Error obtaining category id for default question category.');
        return false;
    }
    $noactiveqfind = $DB->count_records('question', array('category' => "$categoryid",
        'name' => 'no_active_question'));
    if ($noactiveqfind > 0) {
        $noactiveqs = $DB->get_records('question', array('category' => "$categoryid",
        'name' => 'no_active_question'));
        foreach ($noactiveqs as $noactiveq) {
            $noactiveqid = $noactiveq->id;
        }
        return $noactiveqid;
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
    $questionfieldarray = array('category', 'parent', 'name', 'questiontext', 'questiontextformat', 'generalfeedback',
        'generalfeedbackformat', 'defaultmark', 'penalty', 'qtype', 'length', 'stamp', 'version', 'hidden',
        'timecreated', 'timemodified', 'createdby', 'modifiedby');
    $questionnotnullarray = array('name', 'questiontext', 'generalfeedback');
    $questioninsert = new stdClass();
    $date = gmdate("ymdHis");
    $questioninsert->category = $categoryid;
    $questioninsert->parent = 0;
    $questioninsert->questiontextformat = 1;
    $questioninsert->generalfeedback = ' ';
    $questioninsert->generalfeedbackformat = 1;
    $questioninsert->defaultmark = 1;
    $questioninsert->penalty = 0;
    $questioninsert->length = 1;
    $questioninsert->hidden = 0;
    $questioninsert->timecreated = time();
    $questioninsert->timemodified = time();
    $questioninsert->createdby = $USER->id;
    $questioninsert->modifiedby = $USER->id;
    $questioninsert->name = 'no_active_question';// Title.
    $questioninsert->questiontext = '<p>There is no active question right now. Please wait.</p>';
    $questioninsert->qtype = 'essay';
    $questioninsert->stamp = $hostname .'+'. $date .'+'.ipal_random_string(6);
    $questioninsert->version = $questioninsert->stamp;
    $noactiveqid = $DB->insert_record('question', $questioninsert);
    $essayoptions = new stdClass;
    $essayoptions->questionid = $noactiveqid;
    $essayoptions->responseformat = 'noinline';
    $essayoptions->responserequired = 0;
    $essayoptions->responsefieldlines = 0;
    $essayoptions->attachments = 0;
    $essayoptions->attachmentsrequired = 0;
    $essayoptionsid = $DB->insert_record('qtype_essay_options', $essayoptions);
    return $noactiveqid;
}

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
 * This function puts the desired question in the first slot of the quiz and moves the question that was there to another slot.
 *
 * This function also checks that there is one question per page and that the questions are not shuffled.
 * @param $quizid int The id of the quiz for which this is done.
 * @param $questionid int The id of the desired question that will be put in the first slot.
 * @return string The $message string is returned if anything goes wrong.
 **/
function ipal_add_firstquestion_to_quiz($quizid, $questionid) {
    global $DB;
    $message = '';// The returned message giving the result from this function.
    $insert = true;// The boolean to let the program know if the question has to be inserted.
    if (!($quizslots = $DB->get_records('quiz_slots', array('quizid' => $quizid)))) {
        $message .= "Error. There are no questions for this quiz.";
        return $message;
    }
    // A record to put a -1 in the field quiz_active_questions->questionid for this quiz. There should be no entry with this quizid in this table.
    if ($DB->get_records('quiz_active_questions', array('quiz_id' => $quizid))) {
        $message .= "\nError. This quiz is already being used by in IPAL instance.";
        return $message;
    } else {
        $record3 = new stdClass();
        $record3->quiz_id = $quizid;
        $quiz = $DB->get_record('quiz', array('id' => $quizid));
        $record3->course_id = $quiz->course;
        $record3->question_id = -1;
        $record3->ipal_is = 0;
        $record3->timemodified = time();
    }
    $page = array();// An array to keep track of how many pages are in the quiz.
    foreach ($quizslots as $quizslot) {
        if ($quizslot->slot == 1) {
            $oldfirstslot = $quizslot;
        }
        if ($quizslot->questionid == $questionid) {
            // The desired question is already in the quiz. If necessary it will be moved.
            $priordesiredquestion = $quizslot;
            if ($quizslot->slot == 1) {
            if (!($insertid = $DB->insert_record('quiz_active_questions', $record3))) {
                $message .= "\n<br />There was a problem inserting a -1 into the quiz_active_questions table.";
                return $message;
            }
                return $message;
            }
        }
        $page[$quizslot->page] = 1;
    }
    if (count($page) != count($quizslots)) {
        $message .= "There is more than one question per page. This must be fixed.";
        return $message;
    }
    // Make sure that questions aren't shuffled.
    $quizsection = $DB-> get_record('quiz_sections', array('quizid' => $quizid));
    if ($quizsection->shufflequestions > 0) {
        $record = new stdClass();
        $record->id = $quizsection->id;
        $record->shufflequestions = 0;
        $DB->update_record('quiz_sections', $record);
        $message .= "The questions are no longer shuffled.";
    }
    // The questions are displayed by slot, not by page, in the quiz preview and ipal with quiz.
    if (!(isset($oldfirstslot))) {
        $message .= "For some reason there was no question is slot 1 for this quiz.";
        return $message;
    } else {
        // Put the desired question in this slot.
        $record1 = new stdClass();
        $record1->id = $oldfirstslot->id;
        $record1->slot = 1;
        $record1->quizid = $quizid;
        $record1->questionid = $questionid;
        $record1->page = $oldfirstslot->page;// This is probably 1, but we don't want more than one question per page.
        if (isset($priordesiredquestion)) {
            // The question was already in the quiz. We now move it to slot 1.";
            $record1->requireprevious = $priordesiredquestion->requireprevious;
            $record1->maxmark = $priordesiredquestion->maxmark;            
        } else {
            $record1->requireprevious = 0;
            $record1->maxmark = '0.000';
        }
        if ($DB->update_record('quiz_slots', $record1)) {
            //$message .= "The question with id = $questionid has been put in slot 1";
        }
        // Put oldfirstslot into the quiz.
        $record2 = new stdClass();
        $record2->quizid = $quizid;
        $record2->questionid = $oldfirstslot->questionid;
        $record2->requireprevious = $oldfirstslot->requireprevious;
        $record2->maxmark = $oldfirstslot->maxmark;
        if (isset($priordesiredquestion)) {
            // Put the old first slot question where the desired question used to be.
            $record2->id = $priordesiredquestion->id;
            $record2->page = $priordesiredquestion->page;
            $record2->slot = $priordesiredquestion->slot;
            if ($DB->update_record('quiz_slots', $record2)) {
                $message .= " Question with questionid = ".$oldfirstslot->questionid." was moved to slot ".$priordesiredquestion->slot.". Success";
            } else {
                $message .= " The was an error moving question with questionid = ".$oldfirstslot->questionid." to slot ".$priordesiredquestion->slot;
                return $message;
            }
        } else {
            // The old first slot question will have to be inserted into the quiz_slots table.
            // Probably there is no slot greater than the number of questions.
            $newslot = count($quizslots) + 1;
            if (($DB->get_record('quiz_slots', array('quizid' => $quizid, 'slot' => $newslot))) or
                ($DB->get_record('quiz_slots', array('quizid' => $quizid, 'page' => $newslot)))){
                $message .= " Somehow this quiz already had more slots or pages than questions.";
                return $message;
            } else {
                $record2->slot = $newslot;
                $record2->page = $newslot;
                if ($newslotid = $DB->insert_record('quiz_slots', $record2)) {
                    //$message .= " The old question at slot 1 was moved to a new slot = $newslot with id in the quiz_slot table of $newslotid. Success";
                } else {
                    $message .= " Something went wrong trying to insert the question in slot one into a new slot.";
                }
            }
        }
        if (!($insertid = $DB->insert_record('quiz_active_questions', $record3))) {
            $message .= "\n<br />There was a problem inserting a -1 into the quiz_active_questions table.";
            return $message;
        }
    }
    return $message;
}

/**
 * Creates the information for the label with a link to the quiz used in this in-class polling instance.
 *
 * @param $ipalid The id of this in-class polling instance.
 **/
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
    $ipalname = $ipal->name;
    $nonlinkedipalname = preg_replace("/\ /", '_', $ipalname); 
    $labelintro .= $nonlinkedipalname." For Mobile Moodle -- click <a href='".$CFG->wwwroot."/mod/quiz/view.php?id=$cmid'>here</a></p>";
    $labelmodule = $DB->get_record('modules', array('name' => 'label'));
    $labelmoduleid = $labelmodule->id;
    ipal_create_label_form ($section, $courseid, $labelmoduleid, $labelintro);
}

/** Creates the form that will submit the information to the core API to create the label.
 *
 * @param $section int The section in the course where the label will appear.
 * @param $courseid int The id of the course where the label will appear.
 * @param $labelmoduleid int The value from the modules table for label modules (probably 12).
 * @param $labelintro String The text value for the intro field in the label table. 
 **/
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

/**
 * The function to create the javascript to submit the form with the label information to <wwwroot>/course/modedit.php
 *
 * @param $formid The string to identify the form that should be submitted. It's value usually is 'lableform1'.
 **/
function submitform($formid) {
    echo "<script>";
    echo "\ndocument.getElementById(\"$formid\").submit()";
    echo "\n</script>";
   
}
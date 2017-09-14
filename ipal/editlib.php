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
 * Internal library of functions for selecting the questions for module ipal
 *
 * All the ipal specific functions, needed to change the list of questions for ipal
 * logic, should go here. Never include this file from your lib.php!
 * Any function used in the running of an ipal instance should go in ipal/locallib.php
 * Thus, the function to review a question is not included here.
 *
 * @package   mod_ipal
 * @copyright 2014 Eckerd College
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once("$CFG->libdir/formslib.php");
require_once($CFG->dirroot . '/mod/ipal/locallib.php');
defined('MOODLE_INTERNAL') || die();

/**
 * Clean the question layout from various possible anomalies:
 * - Remove consecutive ","'s
 * - Remove duplicate question id's
 * - Remove extra "," from beginning and end
 * - Finally, add a ",0" in the end if there is none
 * Needed because it looks like the function quiz_clean_layout was dropped from Moodle 2.7
 *
 * @param $string $layout the quiz layout to clean up, usually from $quiz->questions.
 * @param bool $removeemptypages If true, remove empty pages from the quiz. False by default.
 * @return $string the cleaned-up layout
 */
function ipal_clean_layout($layout, $removeemptypages = false) {
    // Remove repeated ','s. This can happen when a restore fails to find the right
    // id to relink to.
    $layout = preg_replace('/,{2,}/', ',', trim($layout, ','));

    // Remove duplicate question ids.
    $layout = explode(',', $layout);
    $cleanerlayout = array();
    $seen = array();
    foreach ($layout as $item) {
        if ($item == 0) {
            $cleanerlayout[] = '0';
        } else if (!in_array($item, $seen)) {
            $cleanerlayout[] = $item;
            $seen[] = $item;
        }
    }

    if ($removeemptypages) {
        // Avoid duplicate page breaks.
        $layout = $cleanerlayout;
        $cleanerlayout = array();
        $stripfollowingbreaks = true; // Ensure breaks are stripped from the start.
        foreach ($layout as $item) {
            if ($stripfollowingbreaks && $item == 0) {
                continue;
            }
            $cleanerlayout[] = $item;
            $stripfollowingbreaks = $item == 0;
        }
    }

    // Add a page break at the end if there is none.
    if (end($cleanerlayout) !== '0') {
        $cleanerlayout[] = '0';
    }

    return implode(',', $cleanerlayout);
}

/**
 * Function to check that the question is a qustion type supported in ipal
 * @param int $questionid is the id of the question in the question table
 */
function ipal_acceptable_qtype($questionid) {
    global $DB;

    // An array of acceptable qutypes supported in ipal.
    $acceptableqtypes = array('multichoice', 'truefalse', 'essay');
    $qtype = $DB->get_field('question', 'qtype', array('id' => $questionid));
    if (in_array($qtype, $acceptableqtypes)) {
        return true;
    } else {
        return $qtype;
    }
}

/**
 * Add a question to a quiz  Modified from Add a question to quiz in mod_quiz_editlib.php
 *
 * Adds a question to a quiz by updating $quiz as well as the
 * quiz and quiz_question_instances tables. It also adds a page break
 * if required.
 * @param int $id The id of the question to be added
 * @param object $quiz The extended quiz object as used by edit.php
 *      This is updated by this function
 * @param int $page Which page in quiz to add the question on. If 0 (default),
 *      add at the end
 * @return bool false if the question was already in the quiz
 */
function ipal_add_quiz_question($id, $quiz, $page = 0) {
    global $DB;
    $questions = explode(',', ipal_clean_layout($quiz->questions));
    if (in_array($id, $questions)) {
        return false;
    }

    if (!(ipal_acceptable_qtype($id) === true)) {
        $alertmessage = "IPAL does not support ".ipal_acceptable_qtype($id)." questions.";
        echo "<script language='javascript'>alert('$alertmessage')</script>";
        return false;
    }
    // Remove ending page break if it is not needed.
    if ($breaks = array_keys($questions, 0)) {
        // Determine location of the last two page breaks.
        $end = end($breaks);
        $last = prev($breaks);
        $last = $last ? $last : -1;
        if (!$quiz->questionsperpage || (($end - $last - 1) < $quiz->questionsperpage)) {
            array_pop($questions);
        }
    }
    if (is_int($page) && $page >= 1) {
        $numofpages = substr_count(',' . $quiz->questions, ',0');
        if ($numofpages < $page) {
            // The page specified does not exist in quiz.
            $page = 0;
        } else {
            // Add ending page break - the following logic requires doing this at this point.
            $questions[] = 0;
            $currentpage = 1;
            $addnow = false;
            foreach ($questions as $question) {
                if ($question == 0) {
                    $currentpage++;
                    // The current page is the one after the one we want to add on.
                    // So, we add the question before adding the current page.
                    if ($currentpage == $page + 1) {
                        $questionsnew[] = $id;
                    }
                }
                $questionsnew[] = $question;
            }
            $questions = $questionsnew;
        }
    }
    if ($page == 0) {
        // Add question.
        $questions[] = $id;
        // Add ending page break.
        $questions[] = 0;
    }

    // Save new questionslist in database.
    $quiz->questions = implode(',', $questions);
    $DB->set_field('ipal', 'questions', $quiz->questions, array('id' => $quiz->id));

}

/**
 * Remove a question from an ipal session.
 * Needed because Moodle 2.7 no longer has a question list.
 * @param object $quiz the ipal object.
 * @param int $questionid The id of the question to be deleted.
 */
function ipal_remove_question($quiz, $questionid) {
    global $DB;

    $questionids = explode(',', $quiz->questions);
    $key = array_search($questionid, $questionids);
    if ($key === false) {
        return;
    }

    unset($questionids[$key]);
    $quiz->questions = implode(',', $questionids);
    $DB->set_field('ipal', 'questions', $quiz->questions, array('id' => $quiz->id));
}

/**
 * Private function used by the next two functions.
 * Needed because Moodle 2.7 no longer has a question list.
 * @param string $layout the existinng layout, $quiz->questions.
 * @param int $questionid the id of a question.
 * @param int $shift how far to shift the question (up or down).
 * @return the updated layout
 */
function _ipal_move_question($layout, $questionid, $shift) {
    if (!$questionid || !($shift == 1 || $shift == -1)) {
        return $layout;
    }

    $questionids = explode(',', $layout);
    $key = array_search($questionid, $questionids);
    if ($key === false) {
        return $layout;
    }

    $otherkey = $key + $shift;
    if ($otherkey < 0 || $otherkey >= count($questionids) - 1) {
        return $layout;
    }

    $temp = $questionids[$otherkey];
    $questionids[$otherkey] = $questionids[$key];
    $questionids[$key] = $temp;

    return implode(',', $questionids);
}

/**
 * Move a particular question one space earlier in the $quiz->questions list.
 * Needed because Moodle 2.7 no longer has a question list.
 * If that is not possible, do nothing.
 * @param string $layout the existinng layout, $quiz->questions.
 * @param int $questionid the id of a question.
 * @return the updated layout
 */
function ipal_move_question_up($layout, $questionid) {
    return _ipal_move_question($layout, $questionid, -1);
}

/**
 * Move a particular question one space later in the $quiz->questions list.
 * Needed because Moodle 2.7 no longer has a question list.
 * If that is not possible, do nothing.
 * @param string $layout the existinng layout, $quiz->questions.
 * @param int $questionid the id of a question.
 * @return the updated layout
 */
function ipal_move_question_down($layout, $questionid) {
    return _ipal_move_question($layout, $questionid, + 1);
}

// Parent class for the below, inheriting from either namespaced class (2.8+) or old flat class (2.7).
if (class_exists('mod_quiz\question\bank\custom_view')) {
    /**
     * Parent subclass for ipal_question_bank_view if there is a namespaced class in the quiz module (2.8+).
     *
     * @copyright 2015 William F. Junkin
     * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
     */
    class ipal_question_bank_view_parent extends mod_quiz\question\bank\custom_view {
    }
} else {
    require_once($CFG->dirroot . '/mod/quiz/editlib.php');
    /**
     * Parent subclass for ipal_question_bank_view for versions before 2.8.
     *
     * @copyright 2015 William F. Junkin
     * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
     */
    class ipal_question_bank_view_parent extends quiz_question_bank_view {
    }
}

/**
 * Subclass to customise the view of the question bank for the quiz editing screen.
 *
 * New class required for IPAL because the return URLs are hard coded in the class quiz_question_bank_view
 * @copyright  2009 Tim Hunt
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ipal_question_bank_view extends ipal_question_bank_view_parent {
    /** @var bool the quizhas attempts. */
    protected $quizhasattempts = false;
    /** @var object the quiz settings. */
    protected $quiz = false;

    /**
     * Function to provide the correct URL for use in IPAL.
     * This provides the change needed to use the class quiz_question_bank_view in IPAL.
     *
     * @param int $questionid The id of the question.
     * @return object the correct url for IPAL.
     */
    public function add_to_quiz_url($questionid) {
        global $CFG;
        $params = $this->baseurl->params();
        $params['addquestion'] = $questionid;
        $params['sesskey'] = sesskey();
        return new moodle_url('/mod/ipal/edit.php', $params);
    }

    /**
     * Function to provide the correct URL for use in IPAL in forms.
     *
     * @param bool $showquestiontext Display the text of the question within the list.
     * @param string $scriptpath path to the script displaying this page.
     * @param bool $showtextoption whether to include the 'Show question text' checkbox.
     */
    protected function display_options_form($showquestiontext, $scriptpath = '/mod/ipal/edit.php',
            $showtextoption = false) {
        // Overridden just to change the default values of the arguments.
        parent::display_options_form($showquestiontext, $scriptpath, $showtextoption);
    }

    // The custom_view class overrides this with an empty function.  We want it back.
    /**
     * Function to create the button to create new questions in ipal.
     *
     * @param int $category The category for the new question.
     * @param bool $canadd True if the user has permission to add a question.
     */
    protected function create_new_question_form($category, $canadd) {
        global $CFG;
        echo '<div class="createnewquestion">';
        if ($canadd) {
            create_new_question_button($category->id, $this->editquestionurl->params(),
                    get_string('createnewquestion', 'question'));
        } else {
            print_string('nopermissionadd', 'question');
        }
        echo '</div>';
    }

}

/**
 * Callback function called from question_list() function
 * (which is called from showbank())
 * Displays button in form with checkboxes for each question.
 * @param int $cmid The context ID
 * @param object $cmoptions The options for the context
 * @return string HTML code for the button.
 */
function module_specific_buttons($cmid, $cmoptions) {
    global $OUTPUT;
    $params = array(
        'type' => 'submit',
        'name' => 'add',
        'value' => $OUTPUT->larrow() . ' ' . get_string('addtoquiz', 'quiz'),
    );
    $cmoptions->hasattempts = false;
    if ($cmoptions->hasattempts) {// Prior attempts don’t matter with IPAL.
        $params['disabled'] = 'disabled';
    }
    return html_writer::empty_tag('input', $params);
}

/**
 * Callback function called from question_list() function
 * (which is called from showbank())
 * @param int $totalnumber A variable that is used in quiz but not in IPAL
 * @param bool $recurse Is it recursive
 * @param object $category The information about the Category
 * @param int $cmid The context id
 * @param object $cmoptions The options about this quiz context
 */
function module_specific_controls($totalnumber, $recurse, $category, $cmid, $cmoptions) {
    global $OUTPUT;
    $out = '';
    $catcontext = context::instance_by_id($category->contextid);
    if (has_capability('moodle/question:useall', $catcontext)) {
        $cmoptions->hasattempts = false;
        if ($cmoptions->hasattempts) {// Prior attempts don’t matter with IPAL.
            $disabled = ' disabled="disabled"';
        } else {
            $disabled = '';
        }
        $randomusablequestions = question_bank::get_qtype('random')->get_available_questions_from_category(
                        $category->id, $recurse);
        $maxrand = count($randomusablequestions);$maxrand = 0;// Adding random questions is not an IPAL option.
        if ($maxrand > 0) {
            for ($i = 1; $i <= min(10, $maxrand); $i++) {
                $randomcount[$i] = $i;
            }
            for ($i = 20; $i <= min(100, $maxrand); $i += 10) {
                $randomcount[$i] = $i;
            }
        } else {
            $randomcount[0] = 0;
            $disabled = ' disabled="disabled"';
        }

        $out = '<strong><label for="menurandomcount">'.get_string('addrandomfromcategory', 'quiz').
                '</label></strong><br />';
        $attributes = array();
        $attributes['disabled'] = $disabled ? 'disabled' : null;
        $select = html_writer::select($randomcount, 'randomcount', '1', null, $attributes);
        $out .= get_string('addrandom', 'quiz', $select);
        $out .= '<input type="hidden" name="recurse" value="'.$recurse.'" />';
        $out .= '<input type="hidden" name="categoryid" value="' . $category->id . '" />';
        $out .= ' <input type="submit" name="addrandom" value="'.
                get_string('addtoquiz', 'quiz').'"' . $disabled . ' />';
        $out .= $OUTPUT->help_icon('addarandomquestion', 'quiz');
    }
    return $out;
}

/**
 * Print all the controls for adding questions directly into the specific page in the edit tab of edit.php
 *
 * @param object $quiz Information about the iPAL instance
 * @param string $pageurl The URL of the page
 * @param object $page Information about the current page
 * @param bool $hasattempts Does this quiz have attempts (not used in IPAL)
 * @param object $defaultcategoryobj Information about the category
 */
function ipal_print_pagecontrols($quiz, $pageurl, $page, $hasattempts, $defaultcategoryobj) {
    global $CFG, $OUTPUT;
    static $randombuttoncount = 0;
    $randombuttoncount++;
    echo '<div class="pagecontrols">';
    $hasattempts = 0;// Modified for ipal this is added because attempts don't mean anything with ipal.
    // Get the current context.
    $thiscontext = context_module::instance($quiz->course);
    $contexts = new question_edit_contexts($thiscontext);

    // Get the default category.
    list($defaultcategoryid) = explode(',', $pageurl->param('cat'));
    if (empty($defaultcategoryid)) {
        $defaultcategoryid = $defaultcategoryobj->id;
    }

    // Create the url the question page will return to.
    $returnurladdtoquiz = new moodle_url($pageurl, array('addonpage' => $page));

    // Print a button linking to the choose question type page.
    $returnurladdtoquiz = str_replace($CFG->wwwroot, '', $returnurladdtoquiz->out(false));
    $newquestionparams = array('returnurl' => $returnurladdtoquiz,
            'cmid' => $quiz->cmid, 'appendqnumstring' => 'addquestion');
    create_new_question_button($defaultcategoryid, $newquestionparams,
            get_string('addaquestion', 'quiz'),
            get_string('createquestionandadd', 'quiz'), $hasattempts);

    if ($hasattempts) {
        $disabled = 'disabled="disabled"';
    } else {
        $disabled = '';
    }// IPAL removed lines that added a random button.
    echo "\n</div>";
}
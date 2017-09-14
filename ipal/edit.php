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
 * Page to edit questions selected for an ipal instance
 *
 * This page generally has two columns:
 * The right column lists all available questions in a chosen category and
 * allows them to be edited or more to be added. This column is only there if
 * the quiz does not already have student attempts
 * The left column lists all questions that have been added to the current quiz.
 * The lecturer can add questions from the right hand list to the quiz or remove them
 *
 * The script also processes a number of actions:
 * Actions affecting a quiz:
 * up and down  Changes the order of questions and page breaks
 * addquestion  Adds a single question to the quiz
 * add          Adds several selected questions to the quiz
 * addrandom    Adds a certain number of random questions to the quiz
 * repaginate   Re-paginates the quiz
 * delete       Removes a question from the quiz
 * savechanges  Saves the order and grades for questions in the quiz
 *
 * @package    mod_ipal
 * @copyright  2012 W. F. Junkin Eckerd College (http://www.eckerd.edu)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once($CFG->dirroot . '/mod/ipal/quiz/ipal_genericq_create.php');// Creates two generic questions for each IPAL activity.
require_once($CFG->dirroot . '/question/editlib.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');
require_once($CFG->dirroot . '/mod/quiz/addrandomform.php');
require_once($CFG->dirroot . '/question/category_class.php');
require_once($CFG->dirroot . '/mod/ipal/editlib.php');

// These params are only passed from page request to request while we stay on this page.
// Otherwise they would go in question_edit_setup.
$quizreordertool = optional_param('reordertool', -1, PARAM_BOOL);
$scrollpos = optional_param('scrollpos', '', PARAM_INT);

list($thispageurl, $contexts, $cmid, $cm, $quiz, $pagevars) = question_edit_setup
        ('editq', '/mod/ipal/edit.php', true);// Modified for IPAL.
$quiz->questions = ipal_clean_layout($quiz->questions);
$defaultcategoryobj = question_make_default_categories($contexts->all());
$defaultcategory = $defaultcategoryobj->id . ',' . $defaultcategoryobj->contextid;


if ($quizreordertool > -1) {
    $thispageurl->param('reordertool', $quizreordertool);
    set_user_preference('quiz_reordertab', $quizreordertool);
} else {
    $quizreordertool = get_user_preferences('quiz_reordertab', 0);
}

$canaddrandom = $contexts->have_cap('moodle/question:useall');
$canaddquestion = (bool) $contexts->having_add_and_use();

$PAGE->set_url($thispageurl);

// Get the course object and related bits.
$course = $DB->get_record('course', array('id' => $quiz->course));
if (!$course) {
    print_error('invalidcourseid', 'error');
}

// Log this visit.
$params = array(
    'courseid' => $course->id,
    'context' => $contexts->lowest(),
    'other' => array(
        'ipalid' => $quiz->id
    )
);
$event = \mod_ipal\event\edit_page_viewed::create($params);
$event->trigger();

// You need mod/quiz:manage in addition to question capabilities to access this page.
require_capability('mod/quiz:manage', $contexts->lowest());


// Process commands ============================================================.
if ($quiz->shufflequestions) {
    // Strip page breaks before processing actions, so that re-ordering works when shuffle questions is on.
    $quiz->questions = quiz_repaginate($quiz->questions, 0);
}

// Get the list of question ids had their check-boxes ticked.
$selectedquestionids = array();

$afteractionurl = new moodle_url($thispageurl);
if ($scrollpos) {
    $afteractionurl->param('scrollpos', $scrollpos);
}
if (($up = optional_param('up', false, PARAM_INT)) && confirm_sesskey()) {
    $quiz->questions = ipal_move_question_up($quiz->questions, $up);
    $DB->set_field('ipal', 'questions', $quiz->questions, array('id' => $quiz->id));// Changed for IPAL.
    redirect($afteractionurl);
}

if (($down = optional_param('down', false, PARAM_INT)) && confirm_sesskey()) {
    $quiz->questions = ipal_move_question_down($quiz->questions, $down);
    $DB->set_field('ipal', 'questions', $quiz->questions, array('id' => $quiz->id));// Changed for IPAL.
    redirect($afteractionurl);
}

if (optional_param('repaginate', false, PARAM_BOOL) && confirm_sesskey()) {
    // Re-paginate the quiz.
    $questionsperpage = optional_param('questionsperpage', $quiz->questionsperpage, PARAM_INT);
    $quiz->questions = quiz_repaginate($quiz->questions, $questionsperpage );
    $DB->set_field('quiz', 'questions', $quiz->questions, array('id' => $quiz->id));
    quiz_delete_previews($quiz);
    redirect($afteractionurl);
}

if (($addquestion = optional_param('addquestion', 0, PARAM_INT)) && confirm_sesskey()) {
    // Add a single question to the current quiz.
    $addonpage = optional_param('addonpage', 0, PARAM_INT);
    ipal_add_quiz_question($addquestion, $quiz, $addonpage);
    $thispageurl->param('lastchanged', $addquestion);
    redirect($afteractionurl);
}

if (optional_param('add', false, PARAM_BOOL) && confirm_sesskey()) {
    // Add selected questions to the current ipal. The use of data_submitted is copied from the quiz module.
    $rawdata = (array) data_submitted();
    // The data_submitted function is used because the value of the desired keys is unknown.
    foreach ($rawdata as $key => $value) { // Parse input for question ids.
        // Every desired key must be of the form q(integer). Only the key values are used in the program.
        if (preg_match('!^q([0-9]+)$!', $key, $matches)) {
            $key = $matches[1];
            ipal_add_quiz_question($key, $quiz);
        }
    }
    redirect($afteractionurl);
}

if ((optional_param('addrandom', false, PARAM_BOOL)) && confirm_sesskey()) {
    // Add random questions to the quiz.
    $recurse = optional_param('recurse', 0, PARAM_BOOL);
    $addonpage = optional_param('addonpage', 0, PARAM_INT);
    $categoryid = required_param('categoryid', PARAM_INT);
    $randomcount = required_param('randomcount', PARAM_INT);
    quiz_add_random_questions($quiz, $addonpage, $categoryid, $randomcount, $recurse);

    quiz_delete_previews($quiz);
    quiz_update_sumgrades($quiz);
    redirect($afteractionurl);
}

$remove = optional_param('remove', false, PARAM_INT);
if ($remove && confirm_sesskey()) {
    // Remove a question from the quiz.
    // We require the user to have the 'use' capability on the question.
    // So that then can add it back if they remove the wrong one by mistake.
    ipal_remove_question($quiz, $remove);
    $DB->set_field('ipal', 'questions', $quiz->questions, array('id' => $quiz->id));// Added for IPAL.
    redirect($afteractionurl);
}

if (optional_param('quizdeleteselected', false, PARAM_BOOL) &&
        !empty($selectedquestionids) && confirm_sesskey()) {
    foreach ($selectedquestionids as $questionid) {
        if (quiz_has_question_use($questionid)) {
            quiz_remove_question($quiz, $questionid);
        }
    }
    quiz_delete_previews($quiz);
    quiz_update_sumgrades($quiz);
    redirect($afteractionurl);
}

$submit = optional_param('submit', '', PARAM_ALPHA);
if ($submit == 'Addthesequestionstothisipalactivity') {
    // The PARAM_ALPHA function will remove all spaces from the value of submit.
    // The teacher has come from the attendancequestion_ipal.php page and wants to add Attendance Questions.
    require_once($CFG->dirroot . '/mod/ipal/add_attendancequestion.php');
}

// End of process commands =====================================================.

$PAGE->requires->skip_link_to('quizcontentsblock',
        get_string('skipto', 'access', get_string('questionsinthisquiz', 'quiz')));
$PAGE->set_title(get_string('editingquizx', 'quiz', format_string($quiz->name)));
$PAGE->set_heading($course->fullname);

echo $OUTPUT->header();
// Needed so that the preview buttons won't throw an error if preferredbehaviour='Grid'.
$quiz->preferredbehaviour = 'deferredfeedback';
// Initialise the JavaScript.
$quizeditconfig = new stdClass();
$quizeditconfig->url = $thispageurl->out(true, array('qbanktool' => '0'));
$quizeditconfig->dialoglisteners = array();
$numberoflisteners = 1;// Each quiz in IPAL is only on one page.
for ($pageiter = 1; $pageiter <= $numberoflisteners; $pageiter++) {
    $quizeditconfig->dialoglisteners[] = 'addrandomdialoglaunch_' . $pageiter;
}

$PAGE->requires->data_for_js('quiz_edit_config', $quizeditconfig);
$PAGE->requires->js('/question/qengine.js');
$module = array(
    'name'      => 'mod_quiz_edit',
    'fullpath'  => '/mod/quiz/edit.js',
    'requires'  => array('yui2-dom', 'yui2-event', 'yui2-container'),
    'strings'   => array(),
    'async'     => false,
);


// Question bank display.
// ============.

$questionbank = new ipal_question_bank_view($contexts, $thispageurl, $course, $cm, $quiz);
$questionbank->set_quiz_has_attempts(0);

$condition = new mod_ipal\bank\search\condition_unused($quiz);
$questionbank->add_searchcondition($condition);

echo '<div class="questionbankwindow block">';
if (get_config('mod_ipal', 'autocreate_generic') > 0) {
    ipal_create_genericq($quiz->course);
}
echo '<div class="header"><div class="title">';
echo "<h2>Add Questions</h2>";
echo "</div></div>";
echo '<div class="content"><div class="box generalbox questionbank">';
if (get_config('mod_ipal', 'enable_compadre') > 0) {
    // Include the ComPADRE question import form if enabled.
    require_once($CFG->dirroot . '/mod/ipal/quiz/compadre_access_form.php');
}
if ($DB->count_records('modules', array('name' => 'attendance'))) {
    echo "\n<form action='".$CFG->wwwroot."/mod/ipal/attendancequestion_ipal.php?cmid=$cmid' method='post'>";
    echo "\n<input type='submit' name='submit' value='Add an attendance question'>";
    echo "\n</form>";
}
echo '</div>';

echo '<span id="questionbank"></span>';
echo '<div class="container">';
echo '<div id="module" class="module">';
echo '<div class="bd">';
$questionbank->display('editq',
        $pagevars['qpage'],
        $pagevars['qperpage'],
        $pagevars['cat'], $pagevars['recurse'], $pagevars['showhidden'],
        $pagevars['qbshowtext']);
echo '</div>';
echo '</div>';
echo '</div>';

echo '</div>';
echo '</div>';

// ...================.
// End of question bank display.
echo '<div class="quizcontents" id="quizcontentsblock">';


$repaginatingdisabledhtml = '';
$repaginatingdisabled = false;

echo '<a href="view.php?id='.$quiz->cmid.'">Return to page to start polling with "'.$quiz->name."\"</a>\n<br />";
echo $OUTPUT->heading('Page to Add or Change Current Questions for "' . $quiz->name."\"", 2);// Modified for ipal.
echo $OUTPUT->help_icon('editingipal', 'ipal', get_string('basicideasofipal', 'ipal'));

$tabindex = 0;

$notifystrings = array();

echo '<div class="editq">';

$ipal = $quiz;
$ipalid = $ipal->id;
echo ipal_make_instructor_question_list($cmid, $ipalid);
echo '</div>';

// Close <div class="quizcontents">.
echo '</div>';
$canaddrandom = false;// No random questions allowed in IPAL.

echo $OUTPUT->footer();
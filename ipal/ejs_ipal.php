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
$PAGE->set_url('/mod/ipal/ejs_ipal.php', array('id' => $cm->id));
$PAGE->set_title('Adding EJS Activity to the '.$ipal->name.'activity');
$PAGE->set_heading($course->shortname);

// Output starts here.
echo $OUTPUT->header();
// Only authorized people can access this site.
if (!(has_capability('mod/ipal:instructoraccess', $contextinstance))) {
    echo "\n<br />You must be authorized to access this site";
    echo $OUTPUT->footer();
    exit;
}

echo "\n<br />Information: IPAL id is $ipalid.";
$ipalquestions = explode(",", $ipal->questions);
$numberofquestions = count($ipalquestions) - 1;
echo " There are $numberofquestions ipal questions in this IPAL activity.";
$courseid = $coursemodules->course;
if (!$ejsapps = $DB->get_records('ejsapp', array('course' => $courseid))) {
    echo "\n<br />You must create at least one EJS App activity in this course before you can add it to questions.";
    exit;
}
$context = $DB->get_record('context', array('instanceid' => $courseid, 'contextlevel' => '50'));
$contextid = $context->id;
$qcategories = $DB->get_records('question_categories', array('contextid' => $contextid));

$hasejs[0] = '';
$thisipal[0] = '';
$otherquestions[0] = '';
foreach ($qcategories as $categoryid => $value) {
    $questions = $DB->get_records('question', array('category' => $categoryid));
    $ejsapp = 'ejsapp';
    foreach ($questions as $qid => $question) {
        if (preg_match("/viewejs/", $question->questiontext)) {
            $hasejs[$qid] = $question->name;
        } else if (preg_match("/$ejsapp\/jarfiles/", $question->questiontext)) {
            $hasejs[$qid] = $question->name;
        } else if (in_array($qid, $ipalquestions)) {
            $thisipal[$qid] = $question->name;
        } else {
            $otherquestions[$qid] = $question->name;
        }
    }
}

echo "\n<form action='add_ejs.php'>";
echo "\n<br />Which EJS App Activity do you want to add to a question or several questions?";
foreach ($ejsapps as $ejsappid => $ejsapp) {
    echo "\n<br /><input type='radio' name='ejsappid' value='$ejsappid'>".$ejsapp->name;
}
echo "\n<br /><br />Check every question where you want to insert the selected EJS Activity into the text of a question.";
$qcount = 0;
if (count($thisipal) > 1) {
    echo "\n<br />Here are questions in this IPAL activity.";
    foreach ($thisipal as $qipalid => $qipaltitle) {
        if ($qipalid) {
            echo "\n<br /><input type='checkbox' value='$qipalid' name='qid[$qcount]'>".$qipaltitle;$qcount++;
        }
    }
}
if (count($otherquestions) > 1) {
    echo "\n<br /><br />Here are other questions which are not in this IPAL activity.";
    echo " You may add the selected EJS App activity to these questions as well.";
    foreach ($otherquestions as $qotherid => $qothertitle) {
        if ($qotherid) {
            echo "\n<br /><input type='checkbox' value='$qotherid' name='qid[$qcount]'>".$qothertitle;$qcount++;
        }
    }
}
echo "\n<input type='hidden' name='cmid' value='$cmid'><input type='hidden' name='ipalid' value='$ipalid'>";
echo "\n<input type='hidden' name='courseid' value='$courseid'>";
echo "\n<br /><input type='submit'></form>";
if (count($hasejs) > 1) {
    echo "\n<br /><br />These questions already have an EJS App activity in the question text.";
    foreach ($hasejs as $qhasej => $qhasejtitle) {
        echo "\n<br />".$qhasejtitle;
    }
}

// Finish the page.
echo $OUTPUT->footer();
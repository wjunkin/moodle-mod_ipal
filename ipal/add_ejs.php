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
 * Defines the Moodle forum used to add random questions to the quiz.
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
$PAGE->set_url('/mod/ipal/add_ejs.php', array('id' => $cm->id));
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
foreach ($_GET as $key => $value) {
    $$key = $value;
    if ($key == 'qid') {
        foreach ($_GET['qid'] as $qkey => $qvalue) {
            $qid[$qkey] = $qvalue;
        }
    }
}
echo "Click <a href='".$CFG->wwwroot."/mod/ipal/edit.php?cmid=$cmid'>here</a> to return to IPAL activity.";
if (isset($ejsappid) and $ejsappid > 0) {
    echo "\n<br />An EJS App activity is being added to the selected questions.";
} else {
    echo "\n<br />You must select an EJS App activity to be added to the selected questions.";
    echo "\n<br /> Please use the back button and try again.</body></html>";
    exit;
}

require_once('../../mod/ejsapp/external_interface/ejsapp_external_interface.php');
$ejscode = draw_ejsapp_instance($ejsappid);
$ejscode = 'EJS<ejsipal>'.$ejscode."</ejsipal>\n<br />";
echo "\n<be /> Here are the revised question(s):";
foreach ($qid as $qkey => $qvalue) {
    echo "\n<br /> question $qvalue";
    $questiontext = $DB->get_field('question', 'questiontext', array('id' => $qvalue));
    $result = $DB->set_field('question', 'questiontext', $ejscode.$questiontext, array('id' => $qvalue));
    $newquestiontext = $DB->get_field('question', 'questiontext', array('id' => $qvalue));
    echo "\n<br />".$newquestiontext;
}

// Finish the page.
echo $OUTPUT->footer();

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
 * Displays the questions that may be added to an IPAL instance.
 *
 * @package    mod_ipal
 * @copyright  2012 W. F. Junkin, Eckerd College, http://www.eckerd.edu
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once($CFG->dirroot . '/mod/ipal/locallib.php');
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
$PAGE->set_url('/mod/ipal/ipal_questionbank.php', array('id' => $cm->id));
$PAGE->set_title('Questiong that can be added to the '.$ipal->name.' activity');
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

$coursecontext = $DB->get_record('context', array('instanceid' => $course->id, 'contextlevel' => 50));

echo "\n<form method = 'post' action = '".$CFG->wwwroot."/mod/ipal/edit.php?cmid=$cmid&add=1&sesskey=".$USER->sesskey."'>";
$myform = '';
    $myform .= "\n<script language=\"javascript\" type=\"text/javascript\">
    \n function ipalpopup(id) {
        \n\t url = '".$CFG->wwwroot."/question/preview.php?id='+id+'&amp;cmid=";
    $myform .= $cm->id;
    $myform .= "&amp;behaviour=deferredfeedback&amp;correctness=0&amp;marks=1&amp;markdp=-2";
    $myform .= "&amp;feedback&amp;generalfeedback&amp;rightanswer&amp;history';";
    $myform .= "\n\t newwindow=window.open(url,'Question Preview','height=600,width=800,top=0,left=0,menubar=0,";
    $myform .= "location=0,scrollbars,resizable,toolbar,status,directories=0,fullscreen=0,dependent');";
    $myform .= "\n\t if (window.focus) {newwindow.focus()}
        \n\t return false;
    \n }
    \n </script>\n";
foreach (ipal_get_questionbank_questions($coursecontext->id, $cmid, $ipalid) as $items) {
    $previewurl = $CFG->wwwroot.'/question/preview.php?id='.
        $items['id'].'&cmid='.$cm->id.
        '&behaviour=deferredfeedback&correctness=0&marks=1&markdp=-2&feedback&generalfeedback&rightanswer&history';
    $addquestionurl = $CFG->wwwroot.'/mod/ipal/edit.php?cmid='.$cmid.'&addquestion='.$items['id'].'&sesskey='.$USER->sesskey;
    $myform .= "\n<input type=\"checkbox\" name=\"q".$items['id']."\" value=\"1\" />";
    $myform .= "\n<a href=\"$previewurl\" onclick=\"return ipalpopup('".$items['id']."')\" target=\"_blank\">";
    $myform .= ipal_create_preview_icon()."</a>";
    $myform .= "\n<a href=\"$addquestionurl\">";
    $myform .= ipal_create_standard_icon('add')."</a>";
    $myform .= "\n".$items['question']."<br /><br />\n";
}
echo $myform;
echo "\n<br /><input type='submit' value='Add selected questions to IPAL'></form>";
// Finish the page.
echo $OUTPUT->footer();

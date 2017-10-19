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
 *
 * This file is used with IPAL Apps to log in an authenticate students responding with IPAL Apps.
 *
 * This script checks to verify that the passcode is correct for some ipal instance for which responses
 * from mobile devices has been allowed by the teacher. If the passcode is correct and responses from mobile devices
 * is allowed, this program then determines the course and verifies that the user name is correct for someone
 * registered in the course. The verification that the password is correct for that user name is
 * done by the IPAL App so that the password is not saved on the mobile device. Thus, this page veifies
 * that the pass code is correct for an ipal instance and that the user name is correct for someone
 * in the course where the ipal instance is located, but this page does not require the usual Moodle
 * authentication.
 * If the authentication is correct, the page returns the active question (if there is one) and allows the user
 * to submit an answer.
 * @package    mod_ipal
 * @copyright  2013 Bill Junkin, Eckerd College (http://www.eckerd.edu)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once('./locallib.php');

$username = optional_param('user', '', PARAM_RAW_TRIMMED);
$passcode = optional_param('p', 0, PARAM_INT);
$regid = optional_param('r', '', PARAM_RAW);
$unreg = optional_param('unreg', 0, PARAM_INT);

$qtypemessage = '';
$setipal = true;
$setuser = true;

$itimecreate = fmod($passcode, 100);
$i = floor($passcode / 100);

if ($i) {
    try {
        $ipal = $DB->get_record('ipal', array('id' => $i), '*', MUST_EXIST);
        $course = $DB->get_record('course', array('id' => $ipal->course), '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('ipal', $ipal->id, $course->id, false, MUST_EXIST);
    } catch (dml_exception $e) {
        $qtypemessage = 'invalidpasscode';
        $setipal = false;
    }
    if (fmod($ipal->timecreated, 100) != $itimecreate) {
        $qtypemessage = 'invalidpasscode';
        $setipal = false;
    }
} else {
    $qtypemessage = 'invalidpasscode';
    $setipal = false;
}

if ($setipal) {
    $context = context_course::instance($course->id);
    $studentrole = $DB->get_record('role', array('shortname' => 'student'));
    $students = get_role_users($studentrole->id, $context);

    foreach ($students as $s) {
        // Checking the list of student enrolled in the course with id: $s->username.
        if (strcasecmp($username, $s->username) == 0) {
            $founduser = 1;
            if ($unreg == 1) {
                remove_regid($username);
            }
            $userid = $s->id;
        }
    }

    if ($founduser != 1) {
        $qtypemessage = 'invalidusername';
        $setuser = false;
    }
}

echo "<html>\n<head>\n<title>IPAL: ". $ipal->name."</title>\n</head>\n";
echo "<body>\n";

if (!$setipal || !$setuser) {
    echo "<p id=\"questiontype\">".$qtypemessage."<p>";
} else {
    // If providing a right username, right passcode, add the registration ID to the ipal_mobile table.
    $ipalid = $ipal->id;
    if ($regid) {
        add_regid($regid, $username, $ipalid);
    }
    if (!isset($ipalsesskey)) {
        $ipalsesskey = '';
    }
    ipal_tempview_display_question($userid, $passcode, $username, $ipalid, $ipalsesskey);
}
echo "</body>\n</html>";
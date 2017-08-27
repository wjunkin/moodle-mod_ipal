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
// Since this code logs in students using the IPAL app, the requirement that they be logged in occurs later in the code.
require_once($CFG->dirroot.'/lib/moodlelib.php');
$username = optional_param('username', '', PARAM_RAW_TRIMMED);
$password = optional_param('password', '', PARAM_RAW_TRIMMED);
$passcode = optional_param('p', 0, PARAM_INT);
$regid = optional_param('r', '', PARAM_RAW);
$ipalsesskey = optional_param('ipalsesskey', '', PARAM_ALPHANUMEXT);
$pageurl = $CFG->wwwroot."/mod/ipal/ipallogin.php";
$tempviewurl = $CFG->wwwroot."/mod/ipal/tempview.php";
if ($username == '') {
    $username = optional_param('user', '', PARAM_RAW_TRIMMED);
}

/**
 * Function to create login form if students come here to access an IPAL instance.
 *
 * @param string $pageurl The URL for this page, used by the action to return the user to this page.
 * @param string $msg The message to be displayed in this form.
 */
function ipal_create_login_form($pageurl, $msg) {
    echo "<html><head></head><body id = \"page-login-index\" class=\"notloggedin lang-en\">";
    echo "<form id=\"login\" class='notloggedin' message=\"$msg\" method='post' action=\"$pageurl\">";
    echo "\n<br />$msg";
    echo "\n<br />username <input type='text' name='username' />";
    echo "\n<br />password <input type='password' name='password' />";
    echo "\n<br /><input type='submit' />";
    echo "\n</body></html>";
}

if (($ipalsesskey == '') && ($username == '') && ($password == '') && ($passcode == 0)) {
    // Probably the person hasn't submitted a login form.
    $msg = "mustlogin";
    ipal_create_login_form($pageurl, $msg);
    exit;
}
if ($ipalsesskey == '') {// The user has not yet logged in.
    if ($username == '') {
        $msg = "username missing";
        ipal_create_login_form($pageurl, $msg);
        exit;
    } else {
        if ($password == '') {
            $msg = "password missing";
            ipal_create_login_form($pageurl, $msg);
            exit;
        } else {
            // Find out if user is in the database.
            if (!$user = $DB->get_record('user', array('username' => $username))) {
                $msg = "That user name is not in our database.";
                ipal_create_login_form($pageurl, $msg);
                exit;
            }
        }
    }

    // At this point a username and password has been supplied but no ipalsesskey.
    // Now I need to find out if this password is correct.
    // Initialize variables.
    $errormsg = '';
    $errorcode = 0;
    $username = trim(core_text::strtolower($username));
    $user = authenticate_user_login($username, $password, false, $errorcode);
    if ($user) {
        // This user, without an ipalsesskey, has authenticated. Most of this comes from the login/index.php page.

        // Language setup.
        if (isguestuser($user)) {
            // No predefined language for guests - use existing session or default site lang.
            unset($user->lang);

        } else if (!empty($user->lang)) {
            // Unset previous session language - use user preference instead.
            unset($SESSION->lang);
        }

        if (empty($user->confirmed)) {       // This account was never confirmed.
            $msg = "This user is not a confirmed user.";
            ipal_create_login_form($pageurl, $msg);
            die;
        }
        $ipalsesskey = hash('md4', $user->password);
        // Let's get them all set up.
        // This removes the password from the user object.
        complete_user_login($user);

        \core\session\manager::apply_concurrent_login_limit($user->id, session_id());

        // Sets the username cookie.
        if (!empty($CFG->nolastloggedin)) {
            // Do not store last logged in user in cookie.
            // Auth plugins can temporarily override this from loginpage_hook().
            // Do not save $CFG->nolastloggedin in database!
            $nolastloggedin = $CFG->nolastloggedin;

        } else if (empty($CFG->rememberusername) or ($CFG->rememberusername == 2 and empty($frm->rememberusername))) {
            // No permanent cookies, delete old one if exists.
            set_moodle_cookie('');

        } else {
            set_moodle_cookie($USER->username);
        }
        // Check if user password has expired.
        // Currently supported only for ldap-authentication module.
        $userauth = get_auth_plugin($USER->auth);
        if (!isguestuser() and !empty($userauth->config->expiration) and $userauth->config->expiration == 1) {
            if ($userauth->can_change_password()) {
                $passwordchangeurl = $userauth->change_password_url();
                if (!$passwordchangeurl) {
                    $passwordchangeurl = $CFG->httpswwwroot.'/login/change_password.php';
                }
            } else {
                $passwordchangeurl = $CFG->httpswwwroot.'/login/change_password.php';
            }
            $days2expire = $userauth->password_expire($USER->username);
            $PAGE->set_title("$site->fullname: $loginsite");
            $PAGE->set_heading("$site->fullname");
            if (intval($days2expire) > 0 && intval($days2expire) < intval($userauth->config->expiration_warning)) {
                echo $OUTPUT->header();
                echo $OUTPUT->confirm(get_string('auth_passwordwillexpire', 'auth', $days2expire), $passwordchangeurl, $urltogo);
                echo $OUTPUT->footer();
                exit;
            } else if (intval($days2expire) < 0 ) {
                echo $OUTPUT->header();
                echo $OUTPUT->confirm(get_string('auth_passwordisexpired', 'auth'), $passwordchangeurl, $urltogo);
                echo $OUTPUT->footer();
                exit;
            }
        }

    } else {
        if (empty($errormsg)) {
            if ($errorcode == AUTH_LOGIN_UNAUTHORISED) {
                $errormsg = get_string("unauthorisedlogin", "", $frm->username);
            } else {
                $errormsg = get_string("invalidlogin");
                $errorcode = 3;
            }
        }
        $msg = "User name and password pair are not valid.";
        ipal_create_login_form($pageurl, $msg);
        exit;
    }
}

// At this point the program has an ipalsesskey.
// We must check to make sure the user name, passcode, and ipalsesskey are valid.

if ($username == '') {
    echo "\n<br />Please enter a user name.";
    exit;
} else {
    if ($user = $DB->get_record('user', array('username' => $username))) {
        if (!$ipalsesskey == hash('md4', $user->password)) {
            $msg = "The password doesn't seem to be correct. Please try again.";
            ipal_create_login_form($pageurl, $msg);
            exit;
        }
    } else {
        $msg = "The user name is not in the database. Please try again.";
        ipal_create_login_form($pageurl, $msg);
        exit;
    }
}
/**
 * Function to create passcode form if students come here to access an IPAL instance.
 *
 * @param string $pageurl The URL for this page, used by the action to return the user to this page.
 * @param string $msg The message to be displayed in this form.
 * @param string $username The username for the student.
 * @param string $ipalsesskey The hash of the password for the user, used to verify that the student has logged in.
 */
function ipal_create_passcode_form($pageurl, $msg, $username, $ipalsesskey) {
    echo "<html><head></head><body id = \"page-site-index\" class=\"lang-en isloggedin\">";
    echo "\n<br />".$msg;
    echo "\n<form method='POST' action='$pageurl'>";
    echo "\n<input type='hidden' name='user' value='$username'>";
    echo "\n<input type='hidden' name='ipalsesskey' value='$ipalsesskey'>";
    echo "\n<br />IPAL passcode <input type='text' name='p'>";
    echo "\n<br /><input type='submit'>";
    echo "\n</body></html>";
}
if ($passcode == 0) {
    $msg = "You must enter a valid passcode.";
    ipal_create_passcode_form($pageurl, $msg, $username, $ipalsesskey);
    exit;
}
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
        $qtypemessage = 'invalidpasscode1';
        ipal_create_passcode_form($pageurl, $qtypemessage, $user, $ipalsesskey);
        $setipal = false;
    }
    if (fmod($ipal->timecreated, 100) != $itimecreate) {
        $qtypemessage = 'invalid passcode';
        ipal_create_passcode_form($pageurl, $qtypemessage, $user, $ipalsesskey);
        exit;
        $setipal = false;
    }
} else {
    $qtypemessage = 'Invalid passcode.';
    ipal_create_passcode_form($pageurl, $qtypemessage, $user, $ipalsesskey);
    exit;
    $setipal = false;
}

if ($setipal) {
    $context = context_course::instance($course->id);
    $studentrole = $DB->get_record('role', array('shortname' => 'student'));
    $students = get_role_users($studentrole->id, $context);
    $founduser = 0;
    foreach ($students as $s) {
        // Checking the list of student enrolled in the course with id: $s->username.
        if (strcasecmp($username, $s->username) == 0) {
            $founduser = 1;
            $userid = $s->id;
        }
    }

    if ($founduser != 1) {
        $qtypemessage = 'You are not a student in this course';
        ipal_create_login_form($pageurl, $qtypemessage);
        exit;
        $setuser = false;
    }
}
// At this point the person has logged in correctly, submitted an ipal passcode for an ipal session in a course,
// and is enrolled in that course.
require_once('./tempview.php');

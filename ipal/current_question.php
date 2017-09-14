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
 * Prints out the id for the current question (the id for the ipal_active_question based on the id of the ipal instance
 *
 * This script, with this one function, may be run thousands of times a second,
 * since it is run every 3 seconds by the browser on every student involved in any ipal polling session.
 * Therefore, it is very important that it run very fast and not overload the server.
 * For this reason, the GET method is used to obtain the ipalid instead of the optional_param function.
 * If someone uses this function outside of its intended use, the only information returned will be the
 * question id of the current question for that ipal. They will not have access to the question itself.
 * @package    mod_ipal
 * @copyright  2012 W. F. Junkin, Eckerd College, http://www.eckerd.edu
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
defined('MOODLE_INTERNAL') || die();
// This program needs to run very quickly to let students know when the current question has changed.
// Therefore it does not take time to check on login and
// it uses the inval($_GET['ipalid']) which is about 40 times faster than optional_param('ipalid', 0, PARAM_INT).
// It only returns the id number for the current question for a specific IPAL id.
$ipalid = intval($_GET['ipalid']);
if ($DB->record_exists('ipal_active_questions', array('ipal_id' => $ipalid))) {
    $question = $DB->get_record('ipal_active_questions', array('ipal_id' => $ipalid));
    echo $question->id;
}

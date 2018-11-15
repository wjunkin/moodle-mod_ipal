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
 * Use this file to configure the IPAL module
 *
 * This sets the settings.
 * The enable compadre setting allows teachers to get peer reviewed physics questions from the compadre web site.
 * This site is at https://www.compadre.org/. The default setting is to allow this.
 * The ipal_auto_create_generic setting, if set, will create two generic questions for every new instance of IPAL activity.
 * One generic question is a multichoice question with 8 possible choices but no text associated with any choice.
 * The other generic question is a essay question asking the student to fill in a response.
 * Obviously, the teacher will have to tell the student what the text of the question is.
 * However, having both of these questions available can be very handy when a teacher thinks of a question during class,
 * or doesn't have time to create a question before the class starts.
 *
 * @package    mod_ipal
 * @copyright  2013 Bill Junkin
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

$name = new lang_string('ipal_enable_compadre', 'mod_ipal');
$description = new lang_string('ipal_enable_compadre_help', 'mod_ipal');
$settings->add(new admin_setting_configcheckbox('mod_ipal/enable_compadre',
                                                $name,
                                                $description,
                                                1));

$name = new lang_string('ipal_autocreate_generic', 'mod_ipal');
$description = new lang_string('ipal_autocreate_generic_help', 'mod_ipal');
$settings->add(new admin_setting_configcheckbox('mod_ipal/autocreate_generic',
                                                $name,
                                                $description,
                                                1));

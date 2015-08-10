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
 * Returns the structure of ipal for doing a backup.
 *
 * @package    mod_ipal
 * @subpackage backup-moodle2
 * @copyright  2012 W. F. Junkin, Eckerd College, http://www.eckerd.edu
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * This class has functions to do the backup process.
 *
 * @copyright  2012 W. F. Junkin, Eckerd College, http://www.eckerd.edu
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_ipal_activity_structure_step extends backup_questions_activity_structure_step {

    /**
     * A function to return an object with all the ipal structure
     **/
    protected function define_structure() {
        global $DB;
        $myipalid = $this->task->get_activityid();
        // Update the slots table for question numbers for backup and restore.
        $n = 1;// The index for slot values.
        if ($myipalid > 0) {
            // Delete the prior values in the slots table.
            $DB->delete_records('ipal_slots', array('ipalid' => $myipalid));
            $ipal = $DB->get_record('ipal', array('id' => $myipalid), '*', MUST_EXIST);
            $questions = $ipal->questions;
            $questionarray = explode(',', $questions);
            foreach ($questionarray as $key => $value) {
                if ($value) {
                    $slot[$n] = $value;
                    $n++;
                }
            }
        }

        if ($n > 1) {// A question was in the questions field of the ipal.
            foreach ($slot as $key => $value) {
                $record = new stdClass();
                $record->id = '';
                $record->slot = $key;
                $record->ipalid = $myipalid;
                $record->page = 1;
                $record->questionid = $value;
                $record->maxmark = 1.000;
                $DB->insert_record('ipal_slots', $record);
            }
        }

        // To know if we are including userinfo.
        $userinfo = $this->get_setting_value('userinfo');

        // Define each element of the ipal table.
        $ipal = new backup_nested_element('ipal', array('id'), array('course',
            'name', 'intro', 'introformat', 'timeopen',
            'timeclose', 'preferredbehaviour', 'attempts',
            'attemptonlast', 'grademethod', 'decimalpoints', 'questiondecimalpoints',
            'reviewattempt', 'reviewcorrectness', 'reviewmarks',
            'reviewspecificfeedback', 'reviewgeneralfeedback',
            'reviewrightanswer', 'reviewoverallfeedback',
            'questionsperpage', 'shufflequestions', 'shuffleanswers',
            'questions', 'sumgrades', 'grade', 'timecreated',
            'timemodified', 'timelimit', 'password', 'subnet', 'popup',
            'delay1', 'delay2', 'showuserpicture',
            'showblocks'));

        $answered = new backup_nested_element('answered');

        $answer = new backup_nested_element('answer', array('id'), array(
            'user_id', 'question_id', 'quiz_id', 'answer_id', 'class_id', 'ipal_id', 'ipal_code', 'a_text', 'time_created'));

        $answeredarchive = new backup_nested_element('answeredarchive');

        $answeredarchiveelement = new backup_nested_element('answeredarchiveelement', array('id'), array(
            'user_id', 'question_id', 'quiz_id', 'answer_id', 'class_id', 'ipal_id',
            'ipal_code', 'a_text', 'shortname', 'instructor', 'time_created', 'sent'));

        $qinstances = new backup_nested_element('question_instances');

        $qinstance = new backup_nested_element('question_instance', array('id'), array(
            'slot', 'page', 'questionid', 'maxmark'));

        // Build the tree.
        $ipal->add_child($qinstances);
        $qinstances->add_child($qinstance);

        $ipal->add_child($answered);
        $answered->add_child($answer);

        $ipal->add_child($answeredarchive);
        $answeredarchive->add_child($answeredarchiveelement);

        $ipal->set_source_table('ipal', array('id' => backup::VAR_ACTIVITYID));

        $qinstance->set_source_table('ipal_slots',
                array('ipalid' => backup::VAR_PARENTID));

        // Define id annotations.
        $qinstance->annotate_ids('question', 'questionid');// Necessary to get questions in the backup file.

        if ($userinfo) {
            $answer->set_source_sql('
                SELECT *
                    FROM {ipal_answered}
                    WHERE ipal_id = ?',
                array(backup::VAR_ACTIVITYID));

            $answeredarchiveelement->set_source_sql('
                SELECT *
                    FROM {ipal_answered_archive}
                    WHERE ipal_id = ?',
                array(backup::VAR_ACTIVITYID));

        }

        return $this->prepare_activity_structure($ipal);
    }
}

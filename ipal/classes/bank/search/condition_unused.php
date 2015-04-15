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
 * Defines a class for filtering out used questions from the question bank.
 *
 * @package   mod_ipal
 * @copyright 2015 Paul Nicholls
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_ipal\bank\search;
defined('MOODLE_INTERNAL') || die();

/**
 * A class for filtering out used questions from the question bank.
 *
 * See also {@link question_bank_view::init_search_conditions()}.
 * @copyright 2015 Paul Nicholls
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class condition_unused extends \core_question\bank\search\condition {
    /** @var Where clause to return. */
    protected $where;

    /** @var Array of parameters to return. */
    protected $params;

    /**
     * Constructor.
     * @param string $quiz quiz object containing questions to skip.
     */
    public function __construct($quiz) {
        global $DB;
        $skipqs = explode(",", $quiz->questions);
        if (!empty($skipqs)) {
            list($where, $params) = $DB->get_in_or_equal($skipqs, SQL_PARAMS_NAMED, 'skipq', false);
            $this->where = 'q.id ' . $where;
            $this->params = $params;
        }
    }

    /**
     * Return an SQL fragment to be ANDed into the WHERE clause to filter which questions are shown.
     * @return string SQL fragment. Must use named parameters.
     */
    public function where() {
        if (!empty($this->where)) {
            return $this->where;
        }
    }

    /**
     * Return parameters to be bound to the above WHERE clause fragment.
     * @return array parameter name => value.
     */
    public function params() {
        if (!empty($this->params)) {
            return $this->params;
        }
    }
}

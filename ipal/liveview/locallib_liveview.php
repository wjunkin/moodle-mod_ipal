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
 * Provides all the functions used by the choosequiz.php script.
 *
 * 
 * It has the following functions: quiz_display_instructor_interface, quiz_clear_question, quiz_send_question, quiz_java_graphupdate.
 * More functions: quiz_instructor_buttons, quiz_make_instructor_form, quiz_show_current_question, quiz_update_attempts_layout, quiz_check_active_question.
 * And more functions: quiz_get_questions, quiz_create_preview_icon, quiz_get_answers.
 * @package   mod_ipal
 * @copyright 2018 w. F. Junkin, Eckerd College (http://www.eckerd.edu)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(dirname(dirname(dirname(dirname(__FILE__)))).'/config.php');

/**
 * This function puts all the elements together for the instructors interface.
 * This is the last stop before it is displayed.
 * @param int $cmid The id for the course module for this quiz instance.
 * @param int $quizid The id of this quiz instance.
 * @param int $cmipalid The id for the course module for this ipal instance.
 */
function quiz_display_instructor_interface($cmid, $quizid, $cmipalid) {
    global $DB;
    global $CFG;

    $clearquestion = optional_param('clearQuestion', null, PARAM_TEXT);
    $sendquestionid = optional_param('question', 0, PARAM_INT);

    if (isset($clearquestion)) {
        quiz_clear_question($quizid);
    }
    $state = $DB->get_record('quiz', array('id' => $quizid));
    $state->mobile = 0;// Mobile not implemented for quiz.
    if ($sendquestionid) {
        quiz_send_question($quizid, $state->mobile);
    }

    quiz_java_graphupdate($quizid, $cmid);
    $state->mobile = 0;// Not implemented yet.
    echo "<table><tr><td>".quiz_instructor_buttons($quizid)."</td>";
    echo "<td>&nbsp; &nbsp;<a href='".$CFG->wwwroot."/mod/quiz/edit.php?cmid=$cmid'>Add/Change Questions</a></td>";
    echo "<td>&nbsp; &nbsp;";
    echo "<a href='".$CFG->wwwroot."/mod/ipal/liveview/gridview.php?id=$cmid' target = '_blank'>Quiz spreadsheet</a></td>";
    echo "</tr></table>";
    // Script to make the preview window a popout.
    echo "\n<script language=\"javascript\" type=\"text/javascript\">
    \n function quizpopup(id) {
        \n\t url = '".$CFG->wwwroot."/question/preview.php?id='+id+'&amp;cmid=";
        echo $cmid;
        echo "&amp;behaviour=deferredfeedback&amp;correctness=0&amp;marks=1&amp;markdp=-2";
        echo "&amp;feedback&amp;generalfeedback&amp;rightanswer&amp;history';";
        echo "\n\t newwindow=window.open(url,'Question Preview','height=600,width=800,top=0,left=0,menubar=0,";
        echo "location=0,scrollbars,resizable,toolbar,status,directories=0,fullscreen=0,dependent');";
        echo "\n\t if (window.focus) {newwindow.focus()}
        \n\t return false;
    \n }
    \n </script>\n";

    echo  quiz_make_instructor_form($quizid, $cmid, $cmipalid);
    echo "<br><br>";

    if (quiz_show_current_question($quizid) == 1) {
        echo "<br>";
        echo "<br>";
        echo "<iframe id= \"graphIframe\" src=\"quizgraphics.php?quizid=".$quizid."\" height=\"540\" width=\"723\"></iframe>";
        echo "<br><br><a onclick=\"newwindow=window.open('quizpopupgraph.php?quizid=".$quizid."', '',
                'width=750,height=560,toolbar=no,location=no,menubar=no,copyhistory=no,status=no,";
        echo "directories=no,scrollbars=yes,resizable=yes');
                return false;\"
                href=\"quizpopupgraph.php?quizid=".$quizid."\" target=\"_blank\">Open a new window for the graph.</a>";
    }
}

/**
 * This function clears the current question.
 *
 * @param int $quizid The id of this quiz instance.
 */
function quiz_clear_question($quizid) {
    global $DB;

    $slot = $DB->get_record('quiz_slots', array('quizid' => $quizid, 'slot' => 1));
    // The question saying that there is not active question should always be in slot 1.
    $myquestionid = $slot->questionid;
    //$myquestionid = optional_param('question', 0, PARAM_INT);// The id of the question being sent.

    $quiz = $DB->get_record('quiz', array('id' => $quizid));
    $record = new stdClass();
    $record->id = '';
    $record->course = $quiz->course;
    $record->ipal_id = 0;
    $record->quiz_id = $quiz->id;
    //$record->question_id = $myquestionid;
    $record->question_id = -1;
    $record->timemodified = time();
    if ($DB->record_exists('quiz_active_questions', array('quiz_id' => $quiz->id))) {
        $mybool = $DB->delete_records('quiz_active_questions', array('quiz_id' => $quiz->id));
    }
    $lastinsertid = $DB->insert_record('quiz_active_questions', $record);
    quiz_update_attempts_layout($quizid, $myquestionid);// Hopefully not needed if better communication is developed.
}


/**
 * This function sets the question in the database so the client functions can find what quesiton is active.  And it does it fast.
 *
 * @param int $quizid The id of this quiz instance.
 */
function quiz_send_question($quizid) {
    global $DB;
    global $CFG;

    $myquestionid = optional_param('question', 0, PARAM_INT);// The id of the question being sent.

    $quiz = $DB->get_record('quiz', array('id' => $quizid));
    $record = new stdClass();
    $record->id = '';
    $record->course = $quiz->course;
    $record->ipal_id = 0;
    $record->quiz_id = $quiz->id;
    $record->question_id = $myquestionid;
    $record->timemodified = time();
    if ($DB->record_exists('quiz_active_questions', array('quiz_id' => $quiz->id))) {
        $mybool = $DB->delete_records('quiz_active_questions', array('quiz_id' => $quiz->id));
    }
    $lastinsertid = $DB->insert_record('quiz_active_questions', $record);
    quiz_update_attempts_layout($quizid, $myquestionid);// Hopefully not needed if better communication is developed.
}


/**
 * Prints out the javascript so that the display is updated whenever a student submits an answer.
 *
 * This is done by seeing if the most recent timemodified (supplied by graphicshash.php) has changed.
 * @param int $quizid The id for this quiz.
 * @param int $cmid The id in the course_modules table for this quiz.
 */
function quiz_java_graphupdate($quizid, $cmid) {
    global $DB;
    if ($configs = $DB->get_record('config', array('name' => 'sessiontimeout'))) {
        $timeout = intval($configs->value);
    } else {
        $timeout = 7200;
    }
    echo "\n<div id='timemodified' name='-1'></div>";
    echo "\n\n<script type=\"text/javascript\">\nvar http = false;\nvar x=\"\";\nvar myCount=0;
            \n\nif(navigator.appName == \"Microsoft Internet Explorer\")
            {\nhttp = new ActiveXObject(\"Microsoft.XMLHTTP\");\n} else {\nhttp = new XMLHttpRequest();}";
        echo "\n\nfunction replace() { ";
        $t = '&t='.time();
        echo "\n x=document.getElementById('timemodified');";
        echo "\n myname = x.getAttribute('name');";
        echo "\nvar t=setTimeout(\"replace()\",3000);\nhttp.open(\"GET\", \"graphicshash.php?id=".$cmid.$t."\", true);";
        echo "\nhttp.onreadystatechange=function() {\nif(http.readyState == 4) {";
        echo "\n if((parseInt(http.responseText) != parseInt(myname)) && (myCount < $timeout/3)){";
        echo "\n    document.getElementById('graphIframe').src=\"quizgraphics.php?quizid=".$quizid."\"";
        echo "\n x.setAttribute('name', http.responseText)";
        echo "\n}\n}\n}";
        echo "\n http.send(null);";
        echo "\nmyCount++}\n\nreplace();";
    echo "\n</script>";
}

/**
 * Make the button controls on the instructor interface.
 *
 * @param int $quizid The id of this quiz instance.
 */
function quiz_instructor_buttons($quizid) {
    $mycmid = optional_param('id', '0', PARAM_INT);// The cmid of the quiz instance.
    if ($mycmid) {
        $querystring = 'id='.$mycmid;
    } else {
        $querystring = '';
    }
    $disabled = "";
    $myform = "<form action=\"?".$querystring."\" method=\"post\">\n";
    $myform .= "\n";
    if (!quiz_check_active_question($quizid)) {
        $disabled = "disabled=\"disabled\"";
    }

    $myform .= "<input type=\"submit\" value=\"Stop Polling\" name=\"clearQuestion\" ".$disabled."/>\n</form>\n";

    return($myform);
}


/**
 * This function create the form for the instructors (or anyone higher than a student) to view.
 *
 * @param int $quizid The id of this quiz instance
 * @param int $cmid The id of this quiz course module.
 */
function quiz_make_instructor_form($quizid, $cmid, $cmipalid) {
    global $CFG;
    global $PAGE;

    $mycmid = optional_param('id', '0', PARAM_INT);// The cmid of the quiz instance.
    if ($mycmid) {
        $querystring = 'id='.$mycmid;
    } else if ($cmid > 0) {
        $querystring = 'id='.$cmipalid;
    } else {
        $querystring = '';
    }

    $myform = "<form action=\"?".$querystring."\" method=\"post\">\n";
    foreach (quiz_get_questions($quizid) as $items) {
        $previewurl = $CFG->wwwroot.'/question/preview.php?id='.
            $items['id'].'&cmid='.$cmid.
            '&behaviour=deferredfeedback&correctness=0&marks=1&markdp=-2&feedback&generalfeedback&rightanswer&history';
        $myform .= "\n<input type=\"radio\" name=\"question\" value=\"".$items['id']."\" />";
        $myform .= "\n<a href=\"$previewurl\" onclick=\"return quizpopup('".$items['id']."')\" target=\"_blank\">";
        $myform .= quiz_create_preview_icon()."</a>";
        $myform .= "\n<a href=\"quizgraphics.php?question_id=".$items['id']."&quizid=".$quizid."\" target=\"_blank\">[graph]</a>";
        $myform .= "\n".$items['question']."<br /><br />\n";
    }
    if (quiz_check_active_question($quizid)) {
        $myform .= "<input type=\"submit\" value=\"Send Question\" />\n</form>\n";
    } else {
        $myform .= "<input type=\"submit\" value=\"Start Polling\" />\n</form>\n";
    }

    return($myform);
}


/**
 * This function finds the current question that is active for the quiz that it was requested from.
 *
 * @param int $quizid The id of this quiz instance.
 */
function quiz_show_current_question($quizid) {
    global $DB;

    if ($DB->record_exists('quiz_active_questions', array('quiz_id' => $quizid))) {
        $question = $DB->get_record('quiz_active_questions', array('quiz_id' => $quizid));
        if ($question->question_id == -1) {
            // IPAL uses the absence of any questionid or a -1 for the questionid to indicate that polling has stopped or has not started.
            echo "There is no current question.";
            return(0);
        }
        $questiontext = $DB->get_record('question', array('id' => $question->question_id));
        echo "The current question is -> ".strip_tags($questiontext->questiontext);
        return(1);
    } else {
        return(0);
    }
}

/**
 * This function changes the layout field in the quiz_attempts table so that the student gets the correct question.
 *
 * @param int $quizid The id of this quiz instance.
 * @param int $questionid The id of the active question.
 */
 
 function quiz_update_attempts_layout($quizid, $questionid) {
     global $DB;
     $slot = $DB->get_record('quiz_slots', array('quizid' => $quizid, 'questionid' => $questionid));
     $p = $slot->page;
     $quizattempts = $DB->get_records('quiz_attempts', array('quiz' => $quizid));
     $record = new stdClass();
     $layout = "$p,0,$p,0,$p,0,$p,0,$p,0,$p,0,$p,0,$p,0,$p,0,$p,0,$p,0,$p,0,$p,0,$p,0,$p,0,$p,0,$p,0,$p,0";
     $layout .= ",$p,0,$p,0,$p,0,$p,0,$p,0,$p,0,$p,0,$p,0";
     $record->layout=$layout;
     foreach ($quizattempts as $quizattempt) {
         $record->id = $quizattempt->id;
         $DB->update_record('quiz_attempts', $record, true);
     }
 }
 
/**
 * The function finds out is there a question active?
 *
 * @param int $quizid The id of this quiz instance.
 */
function quiz_check_active_question($quizid) {
    global $DB;

    if ($DB->record_exists('quiz_active_questions', array('quiz_id' => $quizid))) {
        return(1);
    } else {
        return(0);
    }
}

/**
 * Get the questions in any context (like the instructor).
 *
 * @param int $quizid The id for this quiz instance.
 */
function quiz_get_questions($quizid) {
    global $DB;
    global $CFG;
    $q = '';
    $pagearray2 = array();
    $questions = array();
    if ($slots = $DB->get_records('quiz_slots', array('quizid' => $quizid))) {
        foreach ($slots as $slot) {
            $questions[] = $slot->questionid;
        }
    }
    // Get the questions and stuff them into an array.
    foreach ($questions as $q) {
        if (empty($q)) {
            continue;
        }
        $aquestions = $DB->get_record('question', array('id' => $q));
        if (isset($aquestions->questiontext)) {
            $aquestions->questiontext = strip_tags($aquestions->questiontext);
            $pagearray2[] = array('id' => $q, 'question' => $aquestions->questiontext,
                'answers' => quiz_get_answers($q));
        }
    }
    return($pagearray2);
}

/**
 * This function creates the HTML tag for the preview icon.
 */
function quiz_create_preview_icon() {
    global $CFG;
    global $PAGE;
    $previewimageurl = $CFG->wwwroot.'/theme/image.php/'.$PAGE->theme->name.'/core/'.$CFG->themerev.'/t/preview';
    $imgtag = "<img alt='Preview question' class='smallicon' title='Preview question' src='$previewimageurl' />";
    return $imgtag;
}

/**
 * Get Answers For a particular question id.
 * @param int $questionid The id of the question that has been answered in this quiz.
 */
function quiz_get_answers($questionid) {
    global $DB;
    global $CFG;
    $line = "";
    $answers = $DB->get_records('question_answers', array('question' => $questionid));
    foreach ($answers as $answers) {
        $line .= $answers->answer;
        $line .= "&nbsp;";
    }
    return($line);
}

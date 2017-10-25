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
 * Internal library of functions for module ipal
 *
 * All the ipal specific functions, needed to implement the module
 * logic, should go here. Never include this file from your lib.php!
 *
 * @package   mod_ipal
 * @copyright 2011 Eckerd College
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once("$CFG->libdir/formslib.php");
defined('MOODLE_INTERNAL') || die();

/**
 * Get Answers For a particular question id.
 * @param int $questionid The id of the question that has been answered in this ipal.
 */
function ipal_get_answers($questionid) {
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

/**
 * Find out ho has answered questions so far.
 * @param int $ipalid This id for the ipal instance.
 */
function ipal_who_sofar($ipalid) {
    global $DB;

    $records = $DB->get_records('ipal_answered', array('ipal_id' => $ipalid));

    foreach ($records as $records) {
        $answer[] = $records->user_id;
    }
    return(array_unique($answer));
}


/**
 * Find student name.
 * @param int $userid The id of the student.
 */
function ipal_find_student($userid) {
    global $DB;
    $user = $DB->get_record('user', array('id' => $userid));
    $name = $user->lastname.", ".$user->firstname;
    return($name);
}

/**
 * Find responses by Student id.
 * @param int $userid The user id
 * @param int $ipalid The id of the ipal instance.
 * @return string A comma separated string of responses from a student.
 */
function ipal_find_student_responses($userid, $ipalid) {
    global $DB;
    $responses = $DB->get_records('ipal_answered', array('ipal_id' => $ipalid, 'user_id' => $userid));
    foreach ($responses as $records) {
        $temp[] = "Q".$records->question_id." = ".$records->answer_id;
    }
    return(implode(",", $temp));
}

/**
 * Gets answers formated for the student display.
 * @param int $questionid The id of the question
 * @return array The array of answers submitted by students.
 */
function ipal_get_answers_student($questionid) {
    global $DB;
    global $CFG;

    $answerarray = array();
    $line = "";
    $answers = $DB->get_records('question_answers', array('question' => $questionid));
    foreach ($answers as $answers) {
        $answerarray[$answers->id] = $answers->answer;
    }
    return($answerarray);
}

/**
 * Get the Questions if in the student context.
 * @param int $qid Teh question id
 */
function ipal_get_questions_student($qid) {
    global $DB;
    global $CFG;

    $pagearray2 = array();

    $aquestions = $DB->get_record('question', array('id' => $qid));
    if ($aquestions->questiontext != "") {

        $pagearray2[] = array('id' => $qid, 'question' => $aquestions->questiontext, 'answers' => ipal_get_answers_student($qid));
    }
    return($pagearray2);
}


/**
 * Get the questions for the questionbank not yet used in the IPAL.
 *
 * @param int $coursecontextid The context id for the course.
 * @param int $cmid The course module id for the IPAL instance.
 * @param int $ipalid The IPAL id.
 */
function ipal_get_questionbank_questions($coursecontextid, $cmid, $ipalid) {

    global $DB;
    global $CFG;
    $pagearray2 = array();
    // Is there an quiz associated with an ipal?
    // Get quiz and put it into an array.
    $quiz = $DB->get_record('ipal', array('id' => $ipalid));

    // Get the question ids.
    $ipalquestions = explode(",", $quiz->questions);

    // Get the unused questions and stuff them into an array.
    $categories = $DB->get_records('question_categories', array('contextid' => $coursecontextid));
    foreach ($categories as $category) {
        $categoryid = $category->id;
        $questions = $DB->get_records('question', array('category' => $categoryid));
        foreach ($questions as $question) {
            $q = $question->id;
            if (isset($question->questiontext) and (!(in_array($q, $ipalquestions)))) {
                // Removing any EJS from the ipal/view.php page. Note: A dot does not match a new line without the s option.
                $question->questiontext = preg_replace("/EJS<ejsipal>.+<\/ejsipal>/s", "EJS ", $question->questiontext);
                $pagearray2[] = array('id' => $q, 'question' => strip_tags($question->questiontext));
            }
        }
    }
    return($pagearray2);
}

/**
 * Get the questions in any context (like the instructor).
 *
 * @param int $ipalid The id for this IPAL instance.
 */
function ipal_get_questions($ipalid) {
    global $DB;
    global $CFG;
    $q = '';
    $pagearray2 = array();
    $ipal = $DB->get_record('ipal', array('id' => $ipalid));

    // Get the question ids.
    $questions = explode(",", $ipal->questions);

    // Get the questions and stuff them into an array.
    foreach ($questions as $q) {
        if (empty($q)) {
            continue;
        }
        $aquestions = $DB->get_record('question', array('id' => $q));
        if (isset($aquestions->questiontext)) {
            // Removing any EJS from the ipal/view.php page. Note: A dot does not match a new line without the s option.
            $aquestions->questiontext = preg_replace("/EJS<ejsipal>.+<\/ejsipal>/s", "EJS ", $aquestions->questiontext);
            $aquestions->questiontext = strip_tags($aquestions->questiontext);
            if (preg_match("/Attendance question for session (\d+)/", $aquestions->name, $matchs)) {
                // Adding form to allow attendance update through ipal.
                $attendancelink = "<input type='button' onclick=\"location.href='attendancerecorded_ipal.php?";
                $attendancelink .= "ipalid=$ipalid";
                $attendancelink .= "&qid=$q";
                $sessid = $matchs[1];
                $attendancelink .= "&sessid=$sessid";
                $attendancelink .= "&update_record=Update_this_attendance_record';\" ";
                $attendancelink .= "value='Update this attendance record'>\n<br />";
                $aquestions->questiontext = $aquestions->questiontext.$attendancelink;
            }
            $pagearray2[] = array('id' => $q, 'question' => $aquestions->questiontext,
                'answers' => ipal_get_answers($q));
        }
    }
    return($pagearray2);
}

/**
 * This function counts anwers to a question based on ipal id.
 * @param int $questionid The question ID.
 * @param int $ipalid The id of this ipal instance.
 */
function ipal_count_questions($questionid, $ipalid) {
    global $DB;

    $answers = $DB->get_records('question_answers', array('question' => $questionid));
    foreach ($answers as $answers) {
        $labels[] = htmlentities(substr($answers->answer, 10));
        $data[] = $DB->count_records('ipal_answered', array('ipal_id' => $ipalid, 'answer_id' => $answers->id));
    }

    return( "?data=".implode(",", $data)."&labels=".implode(",", $labels)."&total=10");
}

/**
 * This function creates the HTML tag for the preview icon.
 */
function ipal_create_preview_icon() {
    global $CFG;
    global $PAGE;
    $previewimageurl = $CFG->wwwroot.'/theme/image.php/'.$PAGE->theme->name.'/core/'.$CFG->themerev.'/t/preview';
    $imgtag = "<img alt='Preview question' class='smallicon' title='Preview question' src='$previewimageurl' />";
    return $imgtag;
}

/**
 * This function creates the HTML tag for the standard icons (preview, edit, up, down, add, delete).
 *
 * @param String $action The action (preview, edit, up, down, delete) to be taken.
 */
function ipal_create_standard_icon($action) {
    global $CFG;
    global $PAGE;
    $standardimageurl = $CFG->wwwroot.'/theme/image.php/'.$PAGE->theme->name.'/core/'.$CFG->themerev.'/t/'.$action;
    $imgtag = "<img alt='$action question' class='smallicon' title='$action question' src='$standardimageurl' />";
    return $imgtag;
}


/**
 * This function create the form for the instructors (or anyone higher than a student) to view.
 *
 * @param int $ipalid The id of this IPAL instance
 * @param int $cmid The id of this IPAL course module.
 */
function ipal_make_instructor_form($ipalid, $cmid) {
    global $CFG;
    global $PAGE;

    $mycmid = optional_param('id', '0', PARAM_INT);// The cmid of the IPAL instance.
    if ($mycmid) {
        $querystring = 'id='.$mycmid;
    } else {
        $querystring = '';
    }

    $myform = "<form action=\"?".$querystring."\" method=\"post\">\n";
    $myform .= "\n";
    foreach (ipal_get_questions($ipalid) as $items) {
        $previewurl = $CFG->wwwroot.'/question/preview.php?id='.
            $items['id'].'&cmid='.$cmid.
            '&behaviour=deferredfeedback&correctness=0&marks=1&markdp=-2&feedback&generalfeedback&rightanswer&history';
        $myform .= "\n<input type=\"radio\" name=\"question\" value=\"".$items['id']."\" />";
        $myform .= "\n<a href=\"$previewurl\" onclick=\"return ipalpopup('".$items['id']."')\" target=\"_blank\">";
        $myform .= ipal_create_preview_icon()."</a>";
        $myform .= "\n<a href=\"standalone_graph.php?id=".$items['id']."&ipalid=".$ipalid."\" target=\"_blank\">[graph]</a>";
        $myform .= "\n".$items['question']."<br /><br />\n";
    }
    if (ipal_check_active_question($ipalid)) {
        $myform .= "<input type=\"submit\" value=\"Send Question\" />\n</form>\n";
    } else {
        $myform .= "<input type=\"submit\" value=\"Start Polling\" />\n</form>\n";
    }

    return($myform);
}

/**
 * This function create the list of questionsfor the instructors (or anyone higher than a student) to view.
 *
 * @param int $cmid The id for this course module.
 * @param int $ipalid The id for this IPAL instance.
 */
function ipal_make_instructor_question_list($cmid, $ipalid) {
    global $CFG;
    global $USER;

    $sesskey = $USER->sesskey;
    $myform = "";
    // Script to make the preview window a popout.
    $myform .= "\n<script language=\"javascript\" type=\"text/javascript\">
    \n function ipalpopup(id) {
        \n\t url = '".$CFG->wwwroot."/question/preview.php?id='+id+'&amp;cmid=";
    $myform .= $cmid;
    $myform .= "&amp;behaviour=deferredfeedback&amp;correctness=0&amp;marks=1&amp;markdp=-2";
    $myform .= "&amp;feedback&amp;generalfeedback&amp;rightanswer&amp;history';";
    $myform .= "\n\t newwindow=window.open(url,'Question Preview','height=600,width=800,top=0,left=0,menubar=0,";
    $myform .= "location=0,scrollbars,resizable,toolbar,status,directories=0,fullscreen=0,dependent');";
    $myform .= "\n\t if (window.focus) {newwindow.focus()}
        \n\t return false;
    \n }
    \n </script>\n";
    $nquestion = 1;
    $allquestions = ipal_get_questions($ipalid);
    foreach ($allquestions as $items) {
        $previewurl = $CFG->wwwroot.'/question/preview.php?id='.
            $items['id'].'&cmid='.$cmid.
            '&behaviour=deferredfeedback&correctness=0&marks=1&markdp=-2&feedback&generalfeedback&rightanswer&history';
        $myform .= "\n<a href=\"$previewurl\" onclick=\"return ipalpopup('".$items['id']."')\" target=\"_blank\">";
        $myform .= ipal_create_standard_icon('preview')."</a>";
        $editurl = $CFG->wwwroot.'/question/question.php?returnurl=%2Fmod%2Fipal%2Fedit.php%3Fcmid=';
        $editurl .= $cmid.'&cmid='.$cmid.'&id='.$items['id'];
        $myform .= "\n<a href=\"$editurl\">";
        $myform .= ipal_create_standard_icon('edit')."</a>";
        if ($nquestion < count($allquestions)) {
            $downurl = $CFG->wwwroot.'/mod/ipal/edit.php?cmid='.$cmid.'&down='.$items['id'].'&sesskey='.$sesskey;
            $myform .= "\n<a href=\"$downurl\">";
            $myform .= ipal_create_standard_icon('down')."</a>";
        }
        if ($nquestion > 1) {
            $upurl = $CFG->wwwroot.'/mod/ipal/edit.php?cmid='.$cmid.'&up='.$items['id'].'&sesskey='.$sesskey;
            $myform .= "\n<a href=\"$upurl\">";
            $myform .= ipal_create_standard_icon('up')."</a>";
        }
        $removeurl = $CFG->wwwroot.'/mod/ipal/edit.php?cmid='.$cmid.'&remove='.$items['id'].'&sesskey='.$sesskey;
        $myform .= "\n<a href=\"$removeurl\">";
        $myform .= ipal_create_standard_icon('delete')."</a>";
        $myform .= "\n".$items['question']."<br /><br />\n";
        $nquestion ++;
    }

    return($myform);
}


/**
 * This function sets the question in the database so the client functions can find what quesiton is active.  And it does it fast.
 *
 * @param int $ipalid The id of this ipal instance.
 */
function ipal_send_question($ipalid) {
    global $DB;
    global $CFG;

    $myquestionid = optional_param('question', 0, PARAM_INT);// The id of the question being sent.

    $ipal = $DB->get_record('ipal', array('id' => $ipalid));
    $record = new stdClass();
    $record->id = '';
    $record->course = $ipal->course;
    $record->ipal_id = $ipal->id;
    $record->quiz_id = $ipal->id;
    $record->question_id = $myquestionid;
    $record->timemodified = time();
    if ($DB->record_exists('ipal_active_questions', array('ipal_id' => $ipal->id))) {
        $mybool = $DB->delete_records('ipal_active_questions', array('ipal_id' => $ipal->id));
    }
    $lastinsertid = $DB->insert_record('ipal_active_questions', $record);
    if (($ipal->mobile == 1) || ($ipal->mobile == 3)) {
        ipal_refresh_firebase($ipalid);
    }

}

/**
 * This function clears the current question.
 *
 * @param int $ipalid The id of this ipal instance.
 */
function ipal_clear_question($ipalid) {
    global $DB;

    if ($DB->record_exists('ipal_active_questions', array('ipal_id' => $ipalid))) {
        $mybool = $DB->delete_records('ipal_active_questions', array('ipal_id' => $ipalid));
        $ipal = $DB->get_record('ipal', array('id' => $ipalid));
        if (($ipal->mobile == 1) || ($ipal->mobile == 3)) {
            ipal_refresh_firebase($ipalid);
        }
    }
}


/**
 * Java script for checking to see if the chart need to be updated.
 *
 * @param int $ipalid The id of this ipal instance.
 */
function ipal_java_graphupdate($ipalid) {
    global $DB;
    echo "\n\n<script type=\"text/javascript\">\nvar http = false;\nvar x=\"\";
        \n\nif(navigator.appName == \"Microsoft Internet Explorer\")
        {\nhttp = new ActiveXObject(\"Microsoft.XMLHTTP\");\n} else {\nhttp = new XMLHttpRequest();}";
    echo "\n\nfunction replace() { ";
    $t = '&t='.time();
    echo "\nvar t=setTimeout(\"replace()\",10000);\nhttp.open(\"GET\", \"graphicshash.php?ipalid=".$ipalid.$t."\", true);";
    echo "\nhttp.onreadystatechange=function() {\nif(http.readyState == 4) {\nif(http.responseText != x){";
    echo "\nx=http.responseText;\n";
    $state = $DB->get_record('ipal', array('id' => $ipalid));
    if ($state->preferredbehaviour == "Graph") {
        echo "document.getElementById('graphIframe').src=\"graphics.php?ipalid=".$ipalid."\"";
    } else {
        echo "document.getElementById('graphIframe').src=\"gridview.php?id=".$ipalid."\"";
    }

    echo "}\n}\n}\nhttp.send(null);\n}\nreplace();\n</script>";

}

/**
 * Java script for checking to see if the Question has Changed.
 *
 * @param int $ipalid The id of this ipal instance.
 */
function ipal_java_questionupdate($ipalid) {
    echo "\n\n<script type=\"text/javascript\">\nvar http = false;\nvar x=\"\";\nvar myCount=0;
        \n\nif(navigator.appName == \"Microsoft Internet Explorer\")
        {\nhttp = new ActiveXObject(\"Microsoft.XMLHTTP\");\n} else {\nhttp = new XMLHttpRequest();}";

    echo "\n\nfunction replace() {\nvar t=setTimeout(\"replace()\",3000)";
    echo "\nhttp.open(\"GET\", \"current_question.php?ipalid=".$ipalid."\", true);";
    echo "\nhttp.onreadystatechange=function() {\nif(http.readyState == 4) {\n\nif(http.responseText != x && myCount > 1){\n";
    echo "window.location = window.location.href+'&x';\n";
    echo "}\nx=http.responseText;}\n}\nhttp.send(null);\nmyCount++;}\n\nreplace();\n</script>";
}

/**
 * Make the button controls on the instructor interface.
 *
 * @param int $ipalid The id of this ipal instance.
 */
function instructor_buttons($ipalid) {
    $mycmid = optional_param('id', '0', PARAM_INT);// The cmid of the IPAL instance.
    if ($mycmid) {
        $querystring = 'id='.$mycmid;
    } else {
        $querystring = '';
    }
    $disabled = "";
    $myform = "<form action=\"?".$querystring."\" method=\"post\">\n";
    $myform .= "\n";
    if (!ipal_check_active_question($ipalid)) {
        $disabled = "disabled=\"disabled\"";
    }

    $myform .= "<input type=\"submit\" value=\"Stop Polling\" name=\"clearQuestion\" ".$disabled."/>\n</form>\n";

    return($myform);
}

/**
 * Toggles the view between the graph or answers to the spreadsheet view.
 * @param string $newstate Gives the state to be displayed.
 */
function ipal_toggle_view($newstate) {
    $mycmid = optional_param('id', '0', PARAM_INT);// The cmid of the IPAL instance.
    if ($mycmid) {
        $querystring = 'id='.$mycmid;
    } else {
        $querystring = '';
    }

    $myform = "<form action=\"?".$querystring."\" method=\"post\">\n";
    $myform .= "\n";
    $myform .= "<INPUT TYPE=hidden NAME=ipal_view VALUE=\"changeState\">";
    $myform .= "Change View to <input type=\"submit\" value=\"$newstate\" name=\"gridView\"/>\n</form>\n";

    return($myform);

}

/**
 * Create Compadre button for the ipal edit interface.
 * @param int $cmid The ipal id for this ipal instance.
 */
function ipal_show_compadre($cmid) {
    $myform = "<form action=\"edit.php?cmid=".$cmid."\" method=\"post\">\n";
    $myform .= "\n";
    $myform .= "<input type=\"submit\" value=\"Add/Change Questions\" />\n</form>\n";
    return($myform);
}

/**
 * This function puts all the elements together for the instructors interface.
 * This is the last stop before it is displayed.
 * @param int $cmid The id for the course module for this ipal instance.
 * @param int $ipalid The id of this ipal instance.
 */
function ipal_display_instructor_interface($cmid, $ipalid) {
    global $DB;
    global $CFG;

    $clearquestion = optional_param('clearQuestion', null, PARAM_TEXT);
    $sendquestionid = optional_param('question', 0, PARAM_INT);
    $ipalview = optional_param('ipal_view', '', PARAM_TEXT);// The output frm the button to change view of graph.

    if (isset($clearquestion)) {
        ipal_clear_question($ipalid);
    }
    $state = $DB->get_record('ipal', array('id' => $ipalid));
    if ($sendquestionid) {
        ipal_send_question($ipalid, $state->mobile);
    }

    if (($state->preferredbehaviour <> "Grid") and ($state->preferredbehaviour <> "Graph")){// Preferredbehaviour not set.
        $result = $DB->set_field('ipal', 'preferredbehaviour', 'Graph', array('id' => $ipalid));
        $state = $DB->get_record('ipal', array('id' => $ipalid));
    }
    if ((isset($ipalview)) and ($ipalview == "changeState")) {
        if ($state->preferredbehaviour == "Graph") {
            $result = $DB->set_field('ipal', 'preferredbehaviour', 'Grid', array('id' => $ipalid));
            $newstate = 'Histogram';
        } else {
            $result = $DB->set_field('ipal', 'preferredbehaviour', 'Graph', array('id' => $ipalid));
            $newstate = 'Spreadsheet';
        }
    } else {
        if ($state->preferredbehaviour == "Graph") {
            $newstate = 'Spreadsheet';
        } else {
            $newstate = 'Histogram';
        }
    }
    if (($newstate == 'Histogram') and (ipal_get_qtype(ipal_show_current_question_id($ipalid)) == 'essay')) {
        $newstate = 'Responses';
    }

    ipal_java_graphupdate($ipalid);
    echo "<table><tr><td>".instructor_buttons($ipalid)."</td><td>".ipal_show_compadre($cmid)."</td><td>".
        ipal_toggle_view($newstate)."</td>";
    if ($state->mobile) {
        $timecreated = $state->timecreated;
        $ac = $state->id.substr($timecreated, strlen($timecreated) - 2, 2);
        echo "<td>access code=$ac</td>";
    }
    echo "</tr></table>";
    // Script to make the preview window a popout.
    echo "\n<script language=\"javascript\" type=\"text/javascript\">
    \n function ipalpopup(id) {
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

    echo  ipal_make_instructor_form($ipalid, $cmid);
    echo "<br><br>";
    $state = $DB->get_record('ipal', array('id' => $ipalid));
    if ($state->preferredbehaviour == "Graph") {
        if (ipal_show_current_question($ipalid) == 1) {
            echo "<br>";
            echo "<br>";
            echo "<iframe id= \"graphIframe\" src=\"graphics.php?ipalid=".$ipalid."\" height=\"535\" width=\"723\"></iframe>";
            echo "<br><br><a onclick=\"newwindow=window.open('popupgraph.php?ipalid=".$ipalid."', '',
                    'width=620,height=450,toolbar=no,location=no,menubar=no,copyhistory=no,status=no,";
            echo "directories=no,scrollbars=yes,resizable=yes');
                    return false;\"
                    href=\"popupgraph.php?ipalid=".$ipalid."\" target=\"_blank\">Open a new window for the graph.</a>";
        }
    } else {
        echo "<br>";
        echo "<br>";
        echo "<iframe id= \"graphIframe\" src=\"gridview.php?id=".$ipalid.
            "\" height=\"535\" width=\"723\"></iframe>";
        echo "<br><br><a onclick=\"window.open('popupgraph.php?ipalid=".$ipalid."', '',
                'width=620,height=450,toolbar=no,location=no,menubar=no,copyhistory=no,status=no,
                directories=no,scrollbars=yes,resizable=yes');
                return false;\"
                href=\"popupgraph.php?ipalid=".$ipalid."\" target=\"_blank\">Open a new window for the graph.</a>";
    }
}

/**
 * This function finds the current question that is active for the ipal that it was requested from.
 *
 * @param int $ipalid The id of this ipal instance.
 */
function ipal_show_current_question($ipalid) {
    global $DB;

    if ($DB->record_exists('ipal_active_questions', array('ipal_id' => $ipalid))) {
        $question = $DB->get_record('ipal_active_questions', array('ipal_id' => $ipalid));
        $questiontext = $DB->get_record('question', array('id' => $question->question_id));
        // Removing any EJS from the ipal/view.php page. Note: A dot does not match a new line without the s option.
        $questiontext->questiontext = preg_replace("/EJS<ejsipal>.+<\/ejsipal>/s", "EJS ", $questiontext->questiontext);
        echo "The current question is -> ".strip_tags($questiontext->questiontext);
        if (preg_match("/Attendance question for session \d+/", $questiontext->name, $matchs)) {
            $ipal = $DB->get_record('ipal', array('id' => $ipalid));
            $timecreated = $ipal->timecreated;
            echo "\n<br /><br />The attendance code is ".
                $question->question_id.$ipalid.substr($timecreated, strlen($timecreated) - 2, 2);
        }
        return(1);
    } else {
        return(0);
    }
}


/**
 * The function finds out is there a question active?
 *
 * @param int $ipalid The id of this ipal instance.
 */
function ipal_check_active_question($ipalid) {
    global $DB;

    if ($DB->record_exists('ipal_active_questions', array('ipal_id' => $ipalid))) {
        return(1);
    } else {
        return(0);
    }
}


/**
 * This function finds the current question that is active for the ipal that it was requested from.
 *
 * @param int $ipalid The id of this ipal instance.
 */
function ipal_show_current_question_id($ipalid) {
    global $DB;

    if ($DB->record_exists('ipal_active_questions', array('ipal_id' => $ipalid))) {
        $question = $DB->get_record('ipal_active_questions', array('ipal_id' => $ipalid));
        return($question->question_id);
    } else {
        return(0);
    }
}

/**
 * Modification by Junkin.
 *  A function to optain the question type of a given question.
 * Redundant with function ipal_get_question_type in /mod/ipal/graphics.php.
 * @param int $questionid The id of the question.
 */
function ipal_get_qtype($questionid) {
    global $DB;
    if ($questiontype = $DB->get_record('question', array('id' => $questionid))) {
        return($questiontype->qtype);
    } else {
        return 'multichoice';
    }
}

/**
 * This is the function that makes the form for the student to answer from.
 *
 * @param obj $ipal The object with information about this ipal instance.
 */
function ipal_make_student_form($ipal) {
    global $DB;
    global $CFG;
    global $USER;

    $mycmid = optional_param('id', 0, PARAM_INT);// The cmid for the IPAL instance.

    $disabled = '';

    if ($DB->record_exists('ipal_active_questions', array('ipal_id' => $ipal->id))) {

        $question = $DB->get_record('ipal_active_questions', array('ipal_id' => $ipal->id));
        $qid = $question->question_id;
        $myformarray = ipal_get_questions_student($qid);
        echo "<br><br><br>";
        echo "<form action=\"?id=".$mycmid."\" method=\"post\">\n";
        $courseid = $ipal->course;
        $contextid = $DB->get_record('context', array('instanceid' => $courseid, 'contextlevel' => 50));
        // Put entry in question_usages table.
        $record = new Stdclass();
        $record->contextid = $contextid->id;
        $record->component = 'mod_ipal';
        $record->preferredbehaviour = 'deferredfeedback';
        $lastinsertid = $DB->insert_record('question_usages', $record);
        $entryid = $lastinsertid;
        $text = $myformarray[0]['question'];
        $text = file_rewrite_pluginfile_urls($text, 'pluginfile.php', $contextid->id, 'question',
            'questiontext/'.$entryid.'/1', $qid);
        echo  $text;
        echo "<br>";
        if (ipal_get_qtype($qid) == 'essay') {
            echo  "<INPUT TYPE=\"text\" NAME=\"a_text\" size=80>\n<br />";
            echo  "<INPUT TYPE=hidden NAME=\"answer_id\" value=\"-1\">";
        } else {
            foreach ($myformarray[0]['answers'] as $k => $v) {
                echo "<input type=\"radio\" name=\"answer_id\" value=\"$k\" ".$disabled."/> ".strip_tags($v)."<br />\n";
            }
            echo "<INPUT TYPE=hidden NAME=a_text VALUE=\" \">";
        }
        echo "<INPUT TYPE=hidden NAME=question_id VALUE=\"".$myformarray[0]['id']."\">";
        echo "<INPUT TYPE=hidden NAME=active_question_id VALUE=\"$question->id\">";
        echo "<INPUT TYPE=hidden NAME=course_id VALUE=\"$courseid\">";
        echo "<INPUT TYPE=hidden NAME=user_id VALUE=\"$USER->id\">";
        echo "<INPUT TYPE=submit NAME=submit VALUE=\"Submit\" ".$disabled.">";
        echo "<INPUT TYPE=hidden NAME=ipal_id VALUE=\"$ipal->id\">";
        echo "<INPUT TYPE=hidden NAME=instructor VALUE=\"".findinstructor($courseid)."\">";
        echo "</form>";
    } else {
        echo "<table width='450'><tr><td>No Current Question.</td>";
        if ($ipal->mobile > 1) {
            // We plan to enable the use of commercial clickers, with $ipal->mobile > 1, in a future version of IPAL.
            // This part of the if statement is designed for such a future version.
            if ($mobile = $DB->get_record('ipal_mobile', array('user_id' => $USER->id, 'course_id' => $ipal->course))) {
                $mobilemessage = "Update clicker registration";
            } else {
                $mobilemessage = "Register clicker";
            }
            echo "<td align='right'>";
            echo "<a href='ipal_register_clicker.php?cmid=".$mycmid."&ipal_id=".$ipal->id."'>$mobilemessage</a>";
            echo "</td>";
        }
        echo "</tr></table>";
    }
}

/**
 * function to return the encripted hash for an instructor.
 * This is used when sending sanatized data to ComPADRE.
 * @param int $cnum The course id
 */
function findinstructor($cnum) {
    global $DB;
    global $CFG;

    $query = "SELECT u.id FROM {user} u, {role_assignments} r,
            {context} cx, {course} c, {role} ro
            WHERE u.id = r.userid AND r.contextid = cx.id AND cx.instanceid = c.id AND
            ro.shortname='editingteacher' AND r.roleid =ro.id AND
            c.id = ? AND cx.contextlevel =50";
    // The variable $cnum is the id number for the course in the course table.
    $results = $DB->get_records_sql($query, array($cnum));
    if (!$results) {
        return('none');
    } else {
        $instructorwww = '';
        foreach ($results as $key => $result) {
            $instructorwww .= $result->id.$CFG->wwwroot;
        }
        return md5($instructorwww);
    }
}

/**
 * This is the code to insert the student responses into the database.
 * @param int $questionid The question id.
 * @param int $answerid The id of the answer the student subnitted.
 * @param int $activequestionid The id of the active question. Same as question id unless question has been changed.
 * @param string $atext The answer given by the student.
 * @param int $instructor The id of the teacher.
 */
function ipal_save_student_response($questionid, $answerid, $activequestionid, $atext, $instructor) {
    global $DB;
    global $CFG;
    global $USER;

    // Obtaining ipal id.
    if ($activequestionid > 0) {
        if ($activequestion = $DB->get_record('ipal_active_questions', array('id' => $activequestionid))) {
            $ipalid = $activequestion->ipal_id;
            $course = $DB->get_record('course', array('id' => $activequestion->course));
        } else {
            return(0); // Probably a new question has been sent.
        }
    }
    // Create insert for archive.
    $recordarc = new stdClass();
    $recordarc->id = '';
    $recordarc->user_id = $USER->id;
    $recordarc->question_id = $questionid;
    $recordarc->quiz_id = $ipalid;
    $recordarc->answer_id = $answerid;
    $recordarc->a_text = $atext;
    $recordarc->class_id = $course->id;
    $recordarc->ipal_id = $ipalid;
    $recordarc->ipal_code = $activequestionid;
    $recordarc->shortname = $course->shortname;
    $recordarc->instructor = $instructor;
    $recordarc->time_created = time();
    $recordarc->sent = '1';
    $lastinsertid = $DB->insert_record('ipal_answered_archive', $recordarc);

    // Create insert for current question.
    $record = new stdClass();
    $record->id = '';
    $record->user_id = $USER->id;
    $record->question_id = $questionid;
    $record->quiz_id = $ipalid;
    $record->answer_id = $answerid;
    $record->a_text = $atext;
    $record->class_id = $course->id;
    $record->ipal_id = $ipalid;
    $record->ipal_code = $activequestionid;
    $record->time_created = time();

    if ($DB->record_exists('ipal_answered', array('user_id' => $USER->id, 'question_id' => $questionid, 'ipal_id' => $ipalid ))) {
        $mybool = $DB->delete_records('ipal_answered', array('user_id' => $USER->id,
            'question_id' => $questionid, 'ipal_id' => $ipalid ));
    }
    $lastinsertid = $DB->insert_record('ipal_answered', $record);
}

/**
 * This is the function that puts the student interface together.
 * It is the last stop before the display.
 * @param int $ipalid The id of the ipal instance.
 */
function ipal_display_student_interface($ipalid) {
    global $DB;

    $ipal = $DB->get_record('ipal', array('id' => $ipalid));
    $answerid = optional_param('answer_id', 0, PARAM_INT);
    $atext = optional_param('a_text', '', PARAM_RAW_TRIMMED);
    $questionid = optional_param('question_id', 0, PARAM_INT);
    $activequestionid = optional_param('active_question_id', 0, PARAM_INT);
    $instructor = optional_param('instructor', 0, PARAM_ALPHANUM);

    ipal_java_questionupdate($ipalid);
    $priorresponse = '';

    if (isset($answerid) and ($answerid <> 0)) {
        if ($answerid == '-1') {
            $priorresponse = "\n<br />Last answer you submitted: ".strip_tags($atext);
        } else {
            $answerid = $answerid;
            $answer = $DB->get_record('question_answers', array('id' => $answerid));
            $priorresponse = "\n<br />Last answer you submitted: ".strip_tags($answer->answer);
        }
        ipal_save_student_response($questionid, $answerid, $activequestionid,
            $atext, $instructor);
    }
    // Print the anonymous message and prior response.
    echo $priorresponse;

    // Print the question.
    ipal_make_student_form($ipal);
}

/**
 * Print a message to tell users whether the form is anonymous or non-anonymous.
 *
 * @param obj $ipal The object with information about this ipal instance.
 */
function ipal_print_anonymous_message($ipal) {

    if ($ipal->anonymous) {
        echo get_string('anonymousmess', 'ipal');
    } else {
        echo get_string('nonanonymousmess', 'ipal');
    }
}

/**
 * Return true there is any records (of answers) in every questions of the IPAL instance.
 * Return false otherwise.
 *
 * @param int $ipalid the ID of the ipal instance in the ipal table
 * @return boolean
 */
function ipal_check_answered($ipalid) {
    global $DB;
    if (count($DB->get_records('ipal_answered', array('ipal_id' => $ipalid)))) {
        return true;
    } else {
        return false;
    }
}

/**
 * Display question in tempview.php, modified so that
 * its html can be easily parsed to the android application.
 *
 * @param int $userid The id for this user.
 * @param string $passcode The pass code to access the ipal instance.
 * @param string $username The username for the student.
 * @param int $ipalid The id for this ipal instance.
 * @param string $ipalsesskey The ipal session key for this user.
 */
function ipal_tempview_display_question($userid, $passcode, $username, $ipalid, $ipalsesskey = "") {
    global $DB;
    global $CFG;
    global $USER;

    $answerid = optional_param('answer_id', 0, PARAM_INT);
    $atext = optional_param('a_text', '', PARAM_RAW_TRIMMED);
    $questionid = optional_param('question_id', 0, PARAM_INT);
    $activequestionid = optional_param('active_question_id', 0, PARAM_INT);
    $instructor = optional_param('instructor', 0, PARAM_ALPHANUM);
    ipal_java_questionupdate($ipalid);
    $priorresponse = '';

    if ($answerid <> 0) {
        if ($answerid == '-1') {
            $priorresponse = "\n<br />Last answer you submitted:".strip_tags($atext);
        } else {
            $answerid = $answerid;
            $answer = $DB->get_record('question_answers', array('id' => $answerid));
            $priorresponse = "\n<br />Last answer you submitted: ".strip_tags($answer->answer);
        }
        // Save student response.
        ipal_tempview_save_response($questionid, $answerid,
            $activequestionid, $atext, $instructor, $userid);

    }
    // Print the anonymous message and prior response.
    echo $priorresponse;

    $disabled = '';

    if ($DB->record_exists('ipal_active_questions', array('ipal_id' => $ipalid))) {
        $question = $DB->get_record('ipal_active_questions', array('ipal_id' => $ipalid));
        $questionid = $question->question_id;
        $courseid = $question->course;
        $myformarray = ipal_get_questions_student($questionid);
        echo "<br><br><br><br>";
        echo "<p id=\"questiontype\">".ipal_get_qtype($questionid)."<p>";
        echo "<form class=\"ipalquestion\" action=\"?p=".$passcode."&user=".$username."\" method=\"post\">\n";
        echo "<INPUT TYPE=hidden NAME=\"ipalsesskey\" value=\"$ipalsesskey\">";
        // Display question text.
        echo "<fieldset>\n<legend>";
        $myquestion = $myformarray[0]['question'];
        // Remove bad tags from a question.
        $myquestion = preg_replace("/\<\!\-\-.+?\-\-\>/s", '', $myquestion);
        echo $myquestion;
        echo "</legend>\n";

        if (ipal_get_qtype($questionid) == 'essay') { // Display text field if essay question.
            echo  "<INPUT TYPE=\"text\" NAME=\"a_text\" >\n<br>";
            echo  "<INPUT TYPE=hidden NAME=\"answer_id\" value=\"-1\">";
        } else {// Display choices if multiple-choice question.
            $countid = 0;
            foreach ($myformarray[0]['answers'] as $key => $value) {
                echo "<span>";
                echo "<input type=\"radio\" name=\"answer_id\" id=\"choice".$countid."\" value=\"$key\" ".$disabled."/>";
                echo "<label class=\"choice\" for=\"choice".$countid."\">".strip_tags($value)."</label>";
                echo "</span>\n";
                echo "<br>";
                $countid++;
            }
            echo "<br>";
            echo "<INPUT TYPE=hidden NAME=a_text VALUE=\" \">";
        }

        // Hidden inputs.
        echo "<INPUT TYPE=hidden NAME=question_id VALUE=\"".$myformarray[0]['id']."\">";
        // The object question is a row from the ipal_active_questions table."
        echo "<INPUT TYPE=hidden NAME=active_question_id VALUE=\"".$question->id."\">";
        echo "<INPUT TYPE=hidden NAME=course_id VALUE=\"$courseid\">";
        echo "<INPUT TYPE=hidden NAME=user_id VALUE=\"$userid\">";
        echo "<INPUT TYPE=submit NAME=submit VALUE=\"Submit\" ".$disabled.">";
        echo "<INPUT TYPE=hidden NAME=ipal_id VALUE=\"$ipalid\">";
        echo "<INPUT TYPE=hidden NAME=instructor VALUE=\"".findinstructor($courseid)."\">";
        echo "\n</fieldset>";
        echo "</form>\n";
    } else {
        echo "<p id=\"questiontype\">nocurrentquestion<p>";
        echo "<form class=\"ipalquestion\" action=\"?p=".$passcode."&user=".$username."\" method=\"post\">\n";
        echo "<INPUT TYPE=hidden NAME=\"ipalsesskey\" value=\"$ipalsesskey\">";
        echo "<fieldset>\n<legend>";
            $qtypemessage = 'No question has been sent at this time.';
        echo "\n$qtypemessage";
        echo "</legend>\n";
            echo  "<INPUT TYPE=hidden NAME=\"answer_id\" value=\"-2\">";// Removing doesn't work with refresh.
        $ipal = $DB->get_record('ipal', array('id' => $ipalid));
        $courseid = $ipal->course;
        echo "<INPUT TYPE=hidden NAME=question_id VALUE=\"2\">";// Removing doesn't work with refresh.
        // The object question is a row from the ipal_active_questions table.
        echo "<INPUT TYPE=hidden NAME=active_question_id VALUE=\"24\">";// Removing doesn't work with refresh.
        echo "<INPUT TYPE=hidden NAME=course_id VALUE=\"$courseid\">";
        echo "<INPUT TYPE=hidden NAME=user_id VALUE=\"$userid\">";
        echo "<INPUT TYPE=submit NAME=submit VALUE=\"Refresh\" ".$disabled.">";
        echo "<INPUT TYPE=hidden NAME=ipal_id VALUE=\"$ipalid\">";
        echo "<INPUT TYPE=hidden NAME=instructor VALUE=\"".findinstructor($courseid)."\">";
        echo "\n</fieldset>";
        echo "</form>\n";

        exit;
    }
}


/**
 * These function save response to database from the tempview
 *
 * @param int $questionid
 * @param int $answerid
 * @param int $activequestionid
 * @param String $atext
 * @param String $instructor
 * @param int $userid
 */
function ipal_tempview_save_response($questionid, $answerid, $activequestionid, $atext, $instructor, $userid) {
    global $DB;
    global $CFG;
    global $USER;
    // Obtaining ipal id.
    if ($activequestionid > 0) {
        if ($activequestion = $DB->get_record('ipal_active_questions', array('id' => $activequestionid))) {
            $ipalid = $activequestion->ipal_id;
            $course = $DB->get_record('course', array('id' => $activequestion->course));
        } else {
            return(0); // Probably a new question has been sent.
        }
    }
    // Create insert for archive.
    $recordarc = new stdClass();
    $recordarc->id = '';
    $recordarc->user_id = $userid;
    $recordarc->question_id = $questionid;
    $recordarc->quiz_id = $ipalid;
    $recordarc->answer_id = $answerid;
    $recordarc->a_text = $atext;
    $recordarc->class_id = $course->id;
    $recordarc->ipal_id = $ipalid;
    $recordarc->ipal_code = $activequestionid;
    $recordarc->shortname = $course->shortname;
    $recordarc->instructor = $instructor;
    $recordarc->time_created = time();
    $recordarc->sent = '1';
    $lastinsertid = $DB->insert_record('ipal_answered_archive', $recordarc);

    // Create insert for current question.
    $record = new stdClass();
    $record->id = '';
    $record->user_id = $userid;
    $record->question_id = $questionid;
    $record->quiz_id = $ipalid;
    $record->answer_id = $answerid;
    $record->a_text = $atext;
    $record->class_id = $course->id;
    $record->ipal_id = $ipalid;
    $record->ipal_code = $activequestionid;
    $record->time_created = time();

    if ($DB->record_exists('ipal_answered', array('user_id' => $userid, 'question_id' => $questionid, 'ipal_id' => $ipalid ))) {
        $mybool = $DB->delete_records('ipal_answered', array('user_id' => $userid, 'question_id' => $questionid,
        'ipal_id' => $ipalid ));
    }
    $lastinsertid = $DB->insert_record('ipal_answered', $record);

}

/**
 * Send a message to IPAL Android Application to signal refreshing questions.
 * Only send to the students in the class with a valid registrationId
 *
 * @param obj $course The object with information about the course.
 */
function ipal_send_message_to_device($course) {
    global $DB;

    // Replace with real BROWSER API key from Google APIs.
    $apikey = "AIzaSyARBhzl2L5MCV4-_rZNH6nz4xGHvhXpW2E";

    // Replace with real client registration IDs.
    // Get the regIDs from the ipal_mobile table for students in this course.
    $context = context_course::instance($course->id);
    $studentrole = $DB->get_record('role', array('shortname' => 'student'));
    $sql = 'SELECT user_id, reg_id
                FROM {role_assignments} ra INNER JOIN {ipal_mobile} im ON ra.userid=im.user_id
                WHERE ra.contextid = :contextid AND ra.roleid = :roleid';
    $results = $DB->get_recordset_sql($sql, array('contextid' => $context->id, 'roleid' => $studentrole->id));

    $regids = array();
    foreach ($results as $r) {
        if ($r->reg_id != '') {
            array_push($regids, $r->reg_id);
        }
    }

    if (count($regids) === 0) {
        // No students to push it to - don't bother contacting GCM.
        return;
    }

    // Message to be sent.
    $message = "x";

    // Set POST variables.
    $url = 'https://android.googleapis.com/gcm/send';

    $fields = array(
            'registration_ids'  => $regids,
            'data'              => array( "message" => $message ),
    );

    $headers = array(
            'Authorization: key=' . $apikey,
            'Content-Type: application/json'
    );

    // Open connection.
    $ch = curl_init();

    // Set the url, number of POST vars, POST data.
    curl_setopt( $ch, CURLOPT_URL, $url);
    curl_setopt( $ch, CURLOPT_POST, true );
    curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
    curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt( $ch, CURLOPT_POSTFIELDS, json_encode( $fields ) );

    // Execute post.
    $result = curl_exec($ch);

    // Close connection.
    curl_close($ch);

}

/**
 * Add or Update (if existed) the regID that is associated with a userid
 *
 * @param string $regid The token associated with the users mobile device.
 * @param string $user The username of the user.
 * @param int $ipalid The id for the ipal instance.
 */
function add_regid($regid, $user, $ipalid) {
    global $DB;
    if ($user = $DB->get_record('user', array('username' => $user))) {
        if ($record = $DB->get_record('ipal_devices', array('user_id' => $user->id))) {
            $record->token = $regid;
            $record->ipal_id = $ipalid;
            $record->time_modified = time();
            $record->mobile_type = 3;// 3 is the number I have chosen for android devices.
            $DB->update_record('ipal_devices', $record);
            return true;
        } else {
            $recordnew = new stdClass();
            $recordnew->id = '';
            $recordnew->user_id = $user->id;
            $recordnew->token = $regid;
            $recordnew->ipal_id = $ipalid;
            $recordnew->time_created = time();
            $recordnew->time_modified = time();
            $recordnew->mobile_type = 3;
            $DB->insert_record('ipal_devices', $recordnew);
            return true;
        }
    }
}

/**
 * Remove the regID that is associated with a userid
 *
 * @param string $username
 */
function remove_regid($username) {
    global $DB;
    if ($user = $DB->get_record('user', array('username' => $username))) {
        if ($record = $DB->get_record('ipal_mobile', array('user_id' => $user->id))) {
            $record->reg_id = '';
            $record->time_created = time();
            $DB->update_record('ipal_mobile', $record);
            return true;
        }
    }
}
/**
 * Refresh mobile devices using Firebase.
 * @param int $ipalid the ID of the IPAL instance.
 */
function ipal_refresh_firebase($ipalid) {
    global $DB;
    $dbman = $DB->get_manager(); // Loads ddl manager and xmldb classes.
    $table = 'ipal_devices';
    if ($dbman->table_exists($table)) {
        $mobiletype = 3;// 3 is the integer that I have assigned to android devices.
        $message = "refresh";
        $title = "Notice";
        if ($tokens = $DB->get_records('ipal_devices', array('ipal_id' => $ipalid, 'mobile_type' => $mobiletype))) {
            $message = "refresh";
            $title = "Notice";
            $pathtofcm = 'https://fcm.googleapis.com/fcm/send';
            $serverkey = 'AAAANKCV1q4:APA91bEHOj63SE8SSAxbXbriMn8iNX9AqWtlXBk6aDNGL15NHvnjZ1o7L';
            $serverkey .= '-ZVRx2jX6bN_gHOhjU5uo7t819VfFHJs0NV_B0q4SBB4c9inr9o_qGWamjxGVxQuRUiGOaY2NNoQg4roS60';
            $headers = array(
                'Authorization: key='.$serverkey,
                'Content-Type: application/json'
            );
            foreach ($tokens as $ipaldevice) {
                $token = $ipaldevice->token;
                $fields = array(
                    'to' => $token,
                    'notification' => array('title' => $title, 'body' => $message)
                );
                $payload = json_encode($fields);
                // Initializing curl to open a connection.
                $ch = curl_init();

                // Setting the curl url.
                curl_setopt($ch, CURLOPT_URL, $pathtofcm);

                // Setting the method as post.
                curl_setopt($ch, CURLOPT_POST, true);

                // Adding headers.
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

                // Disabling ssl support.
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
                // Adding the fields in json format.
                curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
                echo "\n<br />";
                // Finally executing the curl request.
                $result = curl_exec($ch);
                if ($result === false) {
                    die('Curl failed: ' . curl_error($ch));
                }
                // Now close the connection.
                curl_close($ch);

            }

        }

    }
}
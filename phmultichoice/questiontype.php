<?php  
/*
 * @author: atarplpl.co.il  based on Evgeny Orsky code by Technion Physics Faculty
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package questions
 */

///////////////////
/// PH_MULTICHOICE ///
///////////////////
require_once($CFG->dirroot.'/question/type/phmultichoice/phmultichoice_function.php');
require_once("$CFG->dirroot/question/type/shortanswer/questiontype.php");


/// QUESTION TYPE CLASS //////////////////

///
/// This class contains some special features in order to make the
/// question type embeddable within a multianswer (cloze) question
///



class question_phmultichoice_qtype extends default_questiontype {

    function name() {
        return 'phmultichoice';
    }
    function menu_name() {
		return 'Physics Multichoice';
    }
    function has_html_answers() {
        return true;
    }
	function set_father($question,$request) {
			global $CFG;
			$father = get_record('ph_parent', 'questionid', $question);
			if (! $father) {
				$father = new stdClass();
				$father->questionid = $question;
				$father->parentid = $request;
				insert_record('ph_parent', $father);
			}
	}
    
    function get_question_options(&$question) {
        // Get additional information from database
        // and attach it to the question object

        if (!$question->options = get_record('question_multichoice', 'question', $question->id)) {
			notify('Error: Missing question options for multichoice question'.$question->id.'!');
            return false;
        }

        if (!$question->options->answers = get_records_select('question_answers', 'id IN ('.$question->options->answers.')', 'id')) {
			notify('Error: Missing question answers for multichoice question'.$question->id.'!');
			return false;
        }
	
		$question->options->params = get_records_select('ph_params', 'question='.$question->id, 'id');
		$question->options->parent = get_record_select('ph_parent', 'questionid='.$question->id);
		return true;
    }

    function save_question_options($question) {
        $result = new stdClass;
        if (!$oldanswers = get_records('question_answers', 'question',
                                       $question->id, 'id ASC')) {
            $oldanswers = array();
        }

        // following hack to check at least two answers exist
        $answercount = 0;
        foreach ($question->answer as $key => $dataanswer) {
            if ($dataanswer != '') {
                $answercount++;
            }
        }
        $answercount += count($oldanswers);
        if ($answercount < 2) { // check there are at lest 2 answers for multiple choice
            $result->notice = get_string('notenoughanswers', 'qtype_multichoice', '2');
            return $result;
        }
	//save question params
		if ((isset($question->import_process))&&($question->import_process==true)) {
	    	qtype_phmultichoice_functions::save_question_params_from_import($question);// for import questions
        } else {
            qtype_phmultichoice_functions::save_question_params($question);
			question_phmultichoice_qtype::set_father($question->id ,$question->fatherid);
        }
		if ((isset($question->import_process))&&($question->import_process==true)) {
			qtype_phmultichoice_functions::save_question_parent_from_import($question);
        }
	// Insert all the new answers

        $totalfraction = 0;
        $maxfraction = -1;
        $answers = array();

        foreach ($question->answer as $key => $dataanswer) {
			if ($dataanswer != '') {
                if ($answer = array_shift($oldanswers)) {  // Existing answer, so reuse it
                    $answer->answer     = $dataanswer;
                    $answer->fraction   = $question->fraction[$key];
                    $answer->feedback   = $question->feedback[$key];
                    if (!update_record('question_answers', $answer)) {
                        $result->error = "Could not update quiz answer! (id=$answer->id)";
                        return $result;
                    }
                } else {
					unset($answer);
					$answer->answer   = $dataanswer;
					$answer->question = $question->id;
					$answer->fraction = $question->fraction[$key];
					$answer->feedback = $question->feedback[$key];
					if (!$answer->id = insert_record('question_answers', $answer)) {
						$result->error = 'Could not insert quiz answer! ';
						return $result;
					}
                }
                $answers[] = $answer->id;
                if ($question->fraction[$key] > 0) {                 
                    $totalfraction += $question->fraction[$key];
                }
                if ($question->fraction[$key] > $maxfraction) {
                    $maxfraction = $question->fraction[$key];
                }
            }
        }

        $update = true;
        $options = get_record('question_multichoice', 'question', $question->id);
        if (!$options) {
            $update = false;
            $options = new stdClass;
            $options->question = $question->id;

        }
        $options->answers = implode(',',$answers);
        $options->single = $question->single;
        $options->shuffleanswers = $question->shuffleanswers;
        $options->correctfeedback = trim($question->correctfeedback);
        $options->partiallycorrectfeedback = trim($question->partiallycorrectfeedback);
        $options->incorrectfeedback = trim($question->incorrectfeedback);
        if ($update) {
            if (!update_record('question_multichoice', $options)) {
                $result->error = "Could not update quiz multichoice options! (id=$options->id)";
                return $result;
            }
        } else {
            if (!insert_record('question_multichoice', $options)) {
                $result->error = 'Could not insert quiz multichoice options!';
                return $result;
            }
        }

        // delete old answer records
        if (!empty($oldanswers)) {
            foreach ($oldanswers as $oa) {
                delete_records('question_answers', 'id', $oa->id);
            }
        }

        /// Perform sanity checks on fractional grades
        if ($options->single) {
            if ($maxfraction != 1) {
                $maxfraction = $maxfraction * 100;
                $result->noticeyesno = get_string('fractionsnomax', 'qtype_multichoice', $maxfraction);
                return $result;
            }
        } else {
            $totalfraction = round($totalfraction, 2);
            if ($totalfraction != 1) {
                $totalfraction = $totalfraction * 100;
                $result->noticeyesno = get_string('fractionsaddwrong', 'qtype_multichoice', $totalfraction);
                return $result;
            }
        }
        return true;
    }

    /**
    * Deletes question from the question-type specific tables
    *
    * @return boolean Success/Failure
    * @param object $question  The question being deleted
    */
	function delete_question($questionid) {
        delete_records('question_multichoice', 'question', $questionid);
		delete_records('ph_params', 'question', $questionid);
		delete_records('ph_stored', 'question', $questionid);
		delete_records('ph_parent', 'questionid', $questionid);
        return true;
    }
    
    //function &next_wizard_form($submiturl, $question, $wizardnow){
   
     function &next_wizard_form($submiturl, $question, $wizardnow, $formeditable = false) {
        global $CFG, $SESSION, $COURSE;

        // Catch invalid navigation & reloads
        if (empty($question->id)) {
            redirect('edit.php?courseid='.$COURSE->id, 'The page you are loading has expired. Cannot get next wizard form.', 3);
        }
		if (optional_param('backtoquiz')) {
			qtype_phmultichoice_functions::update_question_params($question);
			$returnurl = optional_param('returnurl');
			redirect($returnurl);
		}
	
        // See where we're coming from
        switch($wizardnow) {
            case 'params':
                require("$CFG->dirroot/question/type/phmultichoice/edit_ph_params_form.php");
                $mform =& new question_ph_params_form("$submiturl?wizardnow=params", $question);
                break;
            default:
                error('Incorrect or no wizard page specified!');
                break;
        }

        return $mform;
    }
    function finished_edit_wizard(&$form) {
        return isset($form->backtoquiz);
    }
    function create_session_and_responses(&$question, &$state, $cmoptions, $attempt) {
        // create an array of answerids ??? why so complicated ???
        $answerids = array_values(array_map(create_function('$val',
																	'return $val->id;'), $question->options->answers));
        // Shuffle the answers if required
        if ($cmoptions->shuffleanswers and $question->options->shuffleanswers) {
			$answerids = swapshuffle($answerids);
        }
        $state->options->order = $answerids;
        // Create empty responses
        if ($question->options->single) {
            $state->responses = array('' => '');
        } else {
            $state->responses = array();
        }
	/**
	    *	Generate new values for question variables
	*/
		$state->vars=qtype_phmultichoice_functions::generate_vars($question);
        return true;
    }


    function restore_session_and_responses(&$question, &$state) {
        // The serialized format for multiple choice quetsions
        // is an optional comma separated list of answer ids (the order of the
        // answers) followed by a colon, followed by another comma separated
        // list of answer ids, which are the radio/checkboxes that were
        // ticked.
        // E.g. 1,3,2,4:2,4 means that the answers were shown in the order
        // 1, 3, 2 and then 4 and the answers 2 and 4 were checked.

        $pos = strpos($state->responses[''], ':');
        if (false === $pos) { // No order of answers is given, so use the default
            $state->options->order = array_keys($question->options->answers);
        } else { // Restore the order of the answers
            $state->options->order = explode(',', substr($state->responses[''], 0, $pos));
            $state->responses[''] = substr($state->responses[''], $pos + 1);
        }
        // Restore the responses
        // This is done in different ways if only a single answer is allowed or
        // if multiple answers are allowed. For single answers the answer id is
        // saved in $state->responses[''], whereas for the multiple answers case
        // the $state->responses array is indexed by the answer ids and the
        // values are also the answer ids (i.e. key = value).
        if (empty($state->responses[''])) { // No previous responses
            $state->responses = array('' => '');
        } else {
            if ($question->options->single) {
                $state->responses = array('' => $state->responses['']);
            } else {
                // Get array of answer ids
                $state->responses = explode(',', $state->responses['']);
                // Create an array indexed by these answer ids
                $state->responses = array_flip($state->responses);
                // Set the value of each element to be equal to the index
                array_walk($state->responses, create_function('&$a, $b',
                 '$a = $b;'));
            }

        }
    /**
     *	Restore old values of question variables, for speciefic attempt
    */
		if ($vars=qtype_phmultichoice_functions::get_attempt_vars($question, $state->attempt)) {
			$state->vars=$vars;
		}
     return true;
    }

    function save_session_and_responses(&$question, &$state) {
        // Bundle the answer order and the responses into the legacy answer
        // field.
        // The serialized format for multiple choice quetsions
        // is (optionally) a comma separated list of answer ids
        // followed by a colon, followed by another comma separated
        // list of answer ids, which are the radio/checkboxes that were
        // ticked.
        // E.g. 1,3,2,4:2,4 means that the answers were shown in the order
        // 1, 3, 2 and then 4 and the answers 2 and 4 were checked.
        $responses  = implode(',', $state->options->order) . ':';
        $responses .= implode(',', $state->responses);

        // Set the legacy answer field
        if (!set_field('question_states', 'answer', $responses, 'id', $state->id)) {
            return false;
        }
        /**
        * save the values of question variables, for speciefic attempt
        */

        if (isset($state->vars)) {
			qtype_phmultichoice_functions::save_attempt_vars($question->id, $state->attempt, $state->vars);
        }
        return true;
	}

    function get_correct_responses(&$question, &$state) {
        if ($question->options->single) {
            foreach ($question->options->answers as $answer) {
                if (((int) $answer->fraction) === 1) {
                    return array('' => $answer->id);
                }
            }
            return null;
        } else {
            $responses = array();
            foreach ($question->options->answers as $answer) {
                if (((float) $answer->fraction) > 0.0) {
                    $responses[$answer->id] = (string) $answer->id;
                }
            }
            return empty($responses) ? null : $responses;
        }
    }

    function print_question_formulation_and_controls(&$question, &$state, $cmoptions, $options) {
        global $CFG;
	//fix question text, and question answers

        $statevars = isset($state->vars) ? $state->vars : '';
		$question->questiontext=qtype_phmultichoice_functions::strip_math_html($question->questiontext, 5,
					 $statevars, $question->id, $state->attempt);

		$question->questiontext=qtype_phmultichoice_functions::convert_relative_src_to_absolute_src(
											$question->questiontext, $CFG->wwwroot);

		foreach ($question->options->answers as $answer) {
			$answer->answer=qtype_phmultichoice_functions::strip_math_html($answer->answer, 5,
									$statevars, $question->id, $state->attempt);

			$answer->answer=qtype_phmultichoice_functions::convert_relative_src_to_absolute_src(
									$answer->answer, $CFG->wwwroot);

		}
        $answers = &$question->options->answers;
        $correctanswers = $this->get_correct_responses($question, $state);
        $readonly = empty($options->readonly) ? '' : 'disabled="disabled"';
        $formatoptions = new stdClass;
        $formatoptions->noclean = true;
        $formatoptions->para = false;

        // Print formulation
        $questiontext = format_text($question->questiontext,
        $question->questiontextformat,
        $formatoptions, $cmoptions->course);
        $image = get_question_image($question, $cmoptions->course);
        $answerprompt = ($question->options->single) ? get_string('singleanswer', 'quiz') :
        get_string('multipleanswers', 'quiz');

        // Print each answer in a separate row
        foreach ($state->options->order as $key => $aid) {
            $answer = &$answers[$aid];
            $qnumchar = chr(ord('a') + $key);
            $checked = '';
            $chosen = false;

        if ($question->options->single) {
			$type = 'type="radio"';
            $name   = "name=\"{$question->name_prefix}\"";
            if (isset($state->responses['']) and $aid == $state->responses['']) {
				$checked = 'checked="checked"';
                $chosen = true;
            }
         } else {
			$type = ' type="checkbox" ';
            $name   = "name=\"{$question->name_prefix}{$aid}\"";
            if (isset($state->responses[$aid])) {
				$checked = 'checked="checked"';
                $chosen = true;
            }
		}
        $a = new stdClass;
        $a->id   = $question->name_prefix . $aid;
        $a->class = '';
		$a->feedbackimg = '';
            // Print the control

        $a->control = "<input $readonly id=\"$a->id\" $name $checked $type value=\"$aid\" />";
		if ($options->correct_responses && $answer->fraction > 0) {
			$a->class = question_get_feedback_class(1);
        }
        if (($options->feedback && $chosen) || $options->correct_responses) {
			$a->feedbackimg = question_get_feedback_image($answer->fraction > 0 ? 1 : 0, $chosen && $options->feedback);
        }

            // Print the answer text
       $a->text = '<span class="anun">' . $qnumchar .
                    '<span class="anumsep">.</span></span> ' . 
      format_text($answer->answer, FORMAT_MOODLE, $formatoptions, $cmoptions->course);

            // Print feedback if feedback is on
		if (($options->feedback || $options->correct_responses) && $checked) {
                $a->feedback = format_text($answer->feedback, true, $formatoptions, $cmoptions->course);
            } else {
                $a->feedback = '';
            }

            $anss[] = clone($a);
        }

        $feedback = '';
        if ($options->feedback) {
            if ($state->raw_grade >= $question->maxgrade/1.01) {
                $feedback = $question->options->correctfeedback;
            } else if ($state->raw_grade > 0) {
                $feedback = $question->options->partiallycorrectfeedback;
            } else {
                $feedback = $question->options->incorrectfeedback;
            }
            $feedback = format_text($feedback,
                    $question->questiontextformat,
                    $formatoptions, $cmoptions->course);
        }

        include("$CFG->dirroot/question/type/phmultichoice/display.html");
    }

    function grade_responses(&$question, &$state, $cmoptions) {
        $state->raw_grade = 0;

        if ($question->options->single) {
            $response = reset($state->responses);
            if ($response) {
                $state->raw_grade = $question->options->answers[$response]->fraction;
            }
        } else {
            foreach ($state->responses as $response) {
                if ($response) {
                    $state->raw_grade += $question->options->answers[$response]->fraction;
                }
            }
        }

        // Make sure we don't assign negative or too high marks
        $state->raw_grade = min(max((float) $state->raw_grade,
                            0.0), 1.0) * $question->maxgrade;

        // Apply the penalty for this attempt
        $state->penalty = $question->penalty * $question->maxgrade;

        // mark the state as graded
        $state->event = ($state->event ==  QUESTION_EVENTCLOSE) ? QUESTION_EVENTCLOSEANDGRADE : QUESTION_EVENTGRADE;

        return true;
    }

    // ULPGC ecastro
    function get_actual_response($question, $state) {
        $answers = $question->options->answers;
        $responses = array();
        if (!empty($state->responses)) {
            foreach ($state->responses as $aid => $rid){
                if (!empty($answers[$rid])) {
                    $responses[] = $this->format_text($answers[$rid]->answer, $question->questiontextformat);
                }
            }
        } else {
            $responses[] = '';
        }
        return $responses;
    }

    function response_summary($question, $state, $length = 80) {
        return implode(',', $this->get_actual_response($question, $state));
    }
	function import_from_xml(&$data, &$question, &$format, &$extra) {
		if ($data['@']['type']=='phmultichoice') {
			$qo = $format->import_headers( $data );

        // 'header' parts particular to multichoice
			$qo->qtype = 'phmultichoice';
			$single = $format->getpath( $data, array('#','single',0,'#'), 'true' );
			$qo->single = $format->trans_single( $single );
			$shuffleanswers = $format->getpath( $data, array('#','shuffleanswers',0,'#'), 'false' );
			$qo->answernumbering = $format->getpath( $data, array('#','answernumbering',0,'#'), 'abc' );
			$qo->shuffleanswers = $format->trans_single($shuffleanswers);
			$qo->correctfeedback = $format->getpath( $data, array('#','correctfeedback',0,'#','text',0,'#'), '', true );
			$qo->partiallycorrectfeedback = $format->getpath( $data, array('#','partiallycorrectfeedback',0,'#','text',0,'#'), '', true );
			$qo->incorrectfeedback = $format->getpath( $data, array('#','incorrectfeedback',0,'#','text',0,'#'), '', true );

        // run through the answers
			$answers = $data['#']['answer'];
			$a_count = 0;
			foreach ($answers as $answer) {
				$ans = $format->import_answer( $answer );
				$qo->answer[$a_count] = $ans->answer;
				$qo->fraction[$a_count] = $ans->fraction;
				$qo->feedback[$a_count] = $ans->feedback;
				++$a_count;
			}
	//start parameters 
			if (isset($data['#']['parameter'])) {
				$params = $data['#']['parameter'];
				foreach ($params as $param) {
					$parameter=new stdClass;
					$parameter->name = $param['#']['name'][0]['#'];
					$parameter->value = $param['#']['value'][0]['#'];
					$parameter->decorder = $param['#']['decorder'][0]['#'];
					$qo->options->params[]=$parameter;
				}
			}
			if (isset($data['#']['theparent'])){
				$parent = $data['#']['theparent'];
				foreach ($parent as $father){
					$theparent=new stdClass;
					$theparent->questionid = $father['#']['questionid'][0]['#'];
					$theparent->parentid = $father['#']['parentid'][0]['#'];
					$qo->options->parent[]=$theparent;
				}
			}
            if (isset($data['#']['parentid'])){
				$qo->old_id = $data['#']['questionid'][0]['#'];
				$qo->old_parent = $data['#']['parentid'][0]['#'];
			}

        return $qo;
		}
	}
	
	function export_to_xml(&$question, &$format, &$extra) {
		$expout='';
		$expout .= '    <single>'.$format->get_single($question->options->single)."</single>\n";
		$expout .= '    <shuffleanswers>'.$format->get_single($question->options->shuffleanswers)."</shuffleanswers>\n";
		$expout .= '    <correctfeedback>'.$format->writetext($question->options->correctfeedback, 3)."</correctfeedback>\n";
		$expout .= '    <partiallycorrectfeedback>'.$format->writetext($question->options->partiallycorrectfeedback, 3).
									"</partiallycorrectfeedback>\n";
		$expout .= '    <incorrectfeedback>'.$format->writetext($question->options->incorrectfeedback, 3).
									"</incorrectfeedback>\n";
		$expout .= "    <answernumbering>{$question->options->answernumbering}</answernumbering>\n";
		foreach ($question->options->answers as $answer) {
			$percent = $answer->fraction * 100;
			$expout .= "      <answer fraction=\"$percent\">\n";
			$expout .= $format->writetext( $answer->answer, 4, false );
			$expout .= "      <feedback>\n";
			$expout .= $format->writetext( $answer->feedback, 5, false );
			$expout .= "      </feedback>\n";
			$expout .= "   </answer>\n";
		}
	//start parameters 
		$params = $question->options->params;
		if ($params) {
			foreach ($params as $param) {
				$expout .= "<parameter>\n";
				$expout .= '    <name>'.$param->name."</name>\n";
				$expout .= '    <value>'.$param->value."</value>\n";
				$expout .= '   <decorder>'.$param->decorder."</decorder>\n";
				$expout .="</parameter>\n";
			}
			$parent = $question->options->parent;
			if ($parent) {
				$expout .= "<theparent>\n";
				$expout .= '    <questionid>'.$parent->questionid."</questionid>\n";
				$expout .= '    <parentid>'.$parent->parentid."</parentid>\n";
				$expout .= "</theparent>\n";
			}
		}
	return $expout ; 
	}
	
/// BACKUP FUNCTIONS ////////////////////////////

    /*
     * Backup the data in the question
     *
     * This is used in question/backuplib.php
     */
    function backup($bf,$preferences,$question,$level=6) {

		$status = true;
		$multichoices = get_records('question_multichoice', 'question', $question, 'id');
        //If there are multichoices
        if ($multichoices) {
            //Iterate over each multichoice
            foreach ($multichoices as $multichoice) {
                $status = fwrite ($bf,start_tag('MULTICHOICE', $level, true));
                //Print multichoice contents
                fwrite ($bf, full_tag('LAYOUT', $level+1, false, $multichoice->layout));
                fwrite ($bf, full_tag('ANSWERS', $level+1, false, $multichoice->answers));
                fwrite ($bf, full_tag('SINGLE', $level+1, false, $multichoice->single));
                fwrite ($bf, full_tag('SHUFFLEANSWERS', $level+1, false, $multichoice->shuffleanswers));
                fwrite ($bf, full_tag('CORRECTFEEDBACK', $level+1, false, $multichoice->correctfeedback));
                fwrite ($bf, full_tag('PARTIALLYCORRECTFEEDBACK', $level+1, false, $multichoice->partiallycorrectfeedback));
                fwrite ($bf, full_tag('INCORRECTFEEDBACK', $level+1, false, $multichoice->incorrectfeedback));
                $status = fwrite ($bf, end_tag('MULTICHOICE', $level, true));
            }

            //Now print question_answers
            $status = question_backup_answers($bf, $preferences, $question);
        }
        return $status;
    }

/// RESTORE FUNCTIONS /////////////////

    /*
     * Restores the data in the question
     *
     * This is used in question/restorelib.php
     */
    function restore($old_question_id, $new_question_id, $info, $restore) {

        $status = true;

        //Get the multichoices array
        $multichoices = $info['#']['MULTICHOICE'];

        //Iterate over multichoices
        for ($i = 0; $i < sizeof($multichoices); $i++) {
            $mul_info = $multichoices[$i];

            //Now, build the question_multichoice record structure
            $multichoice = new stdClass;
            $multichoice->question = $new_question_id;
            $multichoice->layout = backup_todb($mul_info['#']['LAYOUT']['0']['#']);
            $multichoice->answers = backup_todb($mul_info['#']['ANSWERS']['0']['#']);
            $multichoice->single = backup_todb($mul_info['#']['SINGLE']['0']['#']);
            $multichoice->shuffleanswers = isset($mul_info['#']['SHUFFLEANSWERS']['0']['#'])?backup_todb($mul_info['#']['SHUFFLEANSWERS']['0']['#']):'';
            if (array_key_exists('CORRECTFEEDBACK', $mul_info['#'])) {
                $multichoice->correctfeedback = backup_todb($mul_info['#']['CORRECTFEEDBACK']['0']['#']);
            } else {
                $multichoice->correctfeedback = '';
            }
            if (array_key_exists('PARTIALLYCORRECTFEEDBACK', $mul_info['#'])) {
                $multichoice->partiallycorrectfeedback = backup_todb($mul_info['#']['PARTIALLYCORRECTFEEDBACK']['0']['#']);
            } else {
                $multichoice->partiallycorrectfeedback = '';
            }
            if (array_key_exists('INCORRECTFEEDBACK', $mul_info['#'])) {
                $multichoice->incorrectfeedback = backup_todb($mul_info['#']['INCORRECTFEEDBACK']['0']['#']);
            } else {
                $multichoice->incorrectfeedback = '';
            }

            //We have to recode the answers field (a list of answers id)
            //Extracts answer id from sequence
            $answers_field = '';
            $in_first = true;
            $tok = strtok($multichoice->answers,',');
            while ($tok) {
                //Get the answer from backup_ids
                $answer = backup_getid($restore->backup_unique_code,'question_answers',$tok);
                if ($answer) {
                    if ($in_first) {
                        $answers_field .= $answer->new_id;
                        $in_first = false;
                    } else {
                        $answers_field .= ','.$answer->new_id;
                    }
                }
                //check for next
                $tok = strtok(',');
            }
            //We have the answers field recoded to its new ids
            $multichoice->answers = $answers_field;

            //The structure is equal to the db, so insert the question_shortanswer
            $newid = insert_record ('question_multichoice',$multichoice);

            //Do some output
            if (($i+1) % 50 == 0) {
                if (!defined('RESTORE_SILENTLY')) {
                    echo '.';
                    if (($i+1) % 1000 == 0) {
                        echo '<br />';
                    }
                }
                backup_flush(300);
            }

            if (!$newid) {
                $status = false;
            }
        }

        return $status;
    }

    function restore_recode_answer($state, $restore) {
        $pos = strpos($state->answer, ':');
        $order = array();
        $responses = array();
        if (false === $pos) { // No order of answers is given, so use the default
            if ($state->answer) {
                $responses = explode(',', $state->answer);
            }
        } else {
            $order = explode(',', substr($state->answer, 0, $pos));
            if ($responsestring = substr($state->answer, $pos + 1)) {
                $responses = explode(',', $responsestring);
            }
        }
        if ($order) {
            foreach ($order as $key => $oldansid) {
                $answer = backup_getid($restore->backup_unique_code, 'question_answers', $oldansid);
                if ($answer) {
                    $order[$key] = $answer->new_id;
                } else {
                    echo 'Could not recode multichoice answer id '.$oldansid.' for state '.$state->oldid.'<br />';
                }
            }
        }
        if ($responses) {
            foreach ($responses as $key => $oldansid) {
                $answer = backup_getid($restore->backup_unique_code, 'question_answers', $oldansid);
                if ($answer) {
                    $responses[$key] = $answer->new_id;
                } else {
                    echo 'Could not recode multichoice response answer id '.$oldansid.' for state '.$state->oldid.'<br />';
                }
            }
        }
        return implode(',', $order).':'.implode(',', $responses);
    }

    /**
     * Decode links in question type specific tables.
     * @return bool success or failure.
     */ 
    function decode_content_links_caller($questionids, $restore, &$i) {
        $status = true;

        // Decode links in the question_multichoice table.
        if ($multichoices = get_records_list('question_multichoice', 'question',
													implode(',',  $questionids), '', 'id, correctfeedback, partiallycorrectfeedback, incorrectfeedback')) {

            foreach ($multichoices as $multichoice) {
                $correctfeedback = restore_decode_content_links_worker($multichoice->correctfeedback, $restore);
                $partiallycorrectfeedback = restore_decode_content_links_worker($multichoice->partiallycorrectfeedback, $restore);
                $incorrectfeedback = restore_decode_content_links_worker($multichoice->incorrectfeedback, $restore);
                //maybe change
				if ($correctfeedback != $multichoice->correctfeedback ||
								$partiallycorrectfeedback != $multichoice->partiallycorrectfeedback ||
								$incorrectfeedback != $multichoice->incorrectfeedback) {

						$subquestion->correctfeedback = addslashes($correctfeedback);
						$subquestion->partiallycorrectfeedback = addslashes($partiallycorrectfeedback);
						$subquestion->incorrectfeedback = addslashes($incorrectfeedback);
						if (!update_record('question_multichoice', $multichoice)) {
							$status = false;
						}
                }

                // Do some output.
                if (++$i % 5 == 0 && !defined('RESTORE_SILENTLY')) {
                    echo '.';
                    if ($i % 100 == 0) {
                        echo '<br />';
                    }
                    backup_flush(300);
                }
            }
        }

        return $status;
    }
}
// start formula validation

// the same function as 'qtype_calculated_find_formula_errors'
	function qtype_phmultichoice_find_formula_errors($formula) {
    
/// Validates the formula submitted from the question edit page.
/// Returns false if everything is alright.
/// Otherwise it constructs an error message
    // Strip away dataset names
		while (ereg('\\{[[:alpha:]][^>} <{"\']*\\}', $formula, $regs)) {
			$formula = str_replace($regs[0], '1', $formula);
		}

    // Strip away empty space and lowercase it
		$formula = strtolower(str_replace(' ', '', $formula));

		//$safeoperatorchar = '-+/*%>:^~<?=&|!'; /* */
		$safeoperatorchar = '-+/*'; //modified by EVG
		$operatorornumber = "[$safeoperatorchar.0-9eE]";


		while (ereg("(^|[$safeoperatorchar,(])([a-z0-9_]*)\\(($operatorornumber+(,$operatorornumber+((,$operatorornumber+)+)?)?)?\\)",
			$formula, $regs)) {

			switch ($regs[2]) {
            // Simple parenthesis
				case '':
					if ($regs[4] || strlen($regs[3])==0) {
						return get_string('illegalformulasyntax', 'quiz', $regs[0]);
					}
					break;

				// Zero argument functions
				case 'pi':
					if ($regs[3]) {
						return get_string('functiontakesnoargs', 'quiz', $regs[2]);
					}
					break;

            // Single argument functions (the most common case)
				case 'abs': case 'acos': case 'acosh': case 'asin': case 'asinh':
				case 'atan': case 'atanh': case 'bindec': case 'ceil': case 'cos':
				case 'cosh': case 'decbin': case 'decoct': case 'deg2rad':
				case 'exp': case 'expm1': case 'floor': case 'is_finite':
				case 'is_infinite': case 'is_nan': case 'log10': case 'log1p':
				case 'octdec': case 'rad2deg': case 'sin': case 'sinh': case 'sqrt':
				case 'tan': case 'tanh': case 'withsign':
					if ($regs[4] || empty($regs[3])) {
						return get_string('functiontakesonearg', 'quiz', $regs[2]);
					}
					break;

            // Functions that take one or two arguments
				case 'log': case 'round':
					if ($regs[5] || empty($regs[3])) {
						return get_string('functiontakesoneortwoargs', 'quiz', $regs[2]);
					}
					break;

				// Functions that must have two arguments
				case 'atan2': case 'fmod': case 'pow':
					if ($regs[5] || empty($regs[4])) {
						return get_string('functiontakestwoargs', 'quiz', $regs[2]);
					}
					break;

				// Functions that take two or more arguments
				case 'min': case 'max':
					if (empty($regs[4])) {
						return get_string('functiontakesatleasttwo', 'quiz', $regs[2]);
					}
					break;

				default:
					return get_string('unsupportedformulafunction', 'quiz', $regs[2]);
			}

        // Exchange the function call with '1' and then chack for
        // another function call...
			if ($regs[1]) {
				// The function call is proceeded by an operator
				$formula = str_replace($regs[0], $regs[1] . '1', $formula);
			} else {
				// The function call starts the formula
				$formula = ereg_replace("^$regs[2]\\([^)]*\\)", '1', $formula);
			}
		}

		if (ereg("[^$safeoperatorchar.0-9eE]+", $formula, $regs)) {
			return get_string('illegalformulasyntax', 'quiz', $regs[0]);
		} else {
			// Formula just might be valid
			return false;
		}
	}


	function qtype_phmultichoice_find_formula_eval_errors($formula) {
		$answererrors='';
		preg_match_all("/{(.*?)}/", $formula, $paramarr);
		if ($paramarr) {
			 $paramarruniq=array_unique($paramarr[1]);
			 $parameters='';
			 foreach ($paramarruniq as $param) {
				 if (preg_match('/([^a-zA-z0-9_])/', $param)) {
					 $answererrors.= 'illegal parameter syntax in {'.$param.'}';
					 break;
				 }
				 $parameters.= '$'.$param.'='.'1'.';';
			 }

			 if (strlen($answererrors)==0) {
				 eval($parameters);
				 ob_start();
				 try {
						$formula = preg_replace('/withsign/', 'qtype_phmultichoice_functions::withsign', $formula);
						if (eval(preg_replace("/{(.*?)}/","$"."$1", $formula).';')===FALSE) {
						throw new Exception('failed to evaluate');
						}
				 }
				 catch(Exception $e) {
					 preg_match('/Parse error[^:]*:\s+(.*) in/', ob_get_contents(), $eval_error);
					 //$answererrors.=$eval_error[1];
					 $answererrors.='syntax error';
				 }
				 ob_end_clean();
			 }
		}
	return $answererrors;
	}
// end formula validation
//// END OF CLASS ////

//////////////////////////////////////////////////////////////////////////
//// INITIATION - Without this line the question type is not in use... ///
//////////////////////////////////////////////////////////////////////////
	question_register_questiontype(new question_phmultichoice_qtype());


?>

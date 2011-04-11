<?php
/*
 * @author: atarplpl.co.il  based on Evgeny Orsky code by Technion Physics Faculty	
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package questions
 */
 ///////////////////
/// PH_NUMERICAL ///
///////////////////
 
require_once("$CFG->dirroot/question/type/shortanswer/questiontype.php");
require_once("$CFG->dirroot/question/type/phnumerical/phnumerical_functions.php");
/**
 * NUMERICAL QUESTION TYPE CLASS
 *
 * This class contains some special features in order to make the
 * question type embeddable within a multianswer (cloze) question
 *
 * This question type behaves like shortanswer in most cases.
 * Therefore, it extends the shortanswer question type...
 */






class question_phnumerical_qtype extends question_shortanswer_qtype {

    function name() {
        return 'phnumerical';
    }
    function menu_name() {
		return 'Physics Numerical';
    }
	//save in ph_parent the id question of the parent question
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
	

        // Get the question answers and their respective tolerances
        // Note: question_calculated is an extension of the answer table rather than
        //       the question table as is usually the case for qtype
        //       specific tables.
        global $CFG;
	
        if (! $question->options->answers = get_records_sql(
                                'SELECT a.*, n.tolerance ,n.tolerancetype,n.correctanswerlength,n.correctanswerformat ' .
                                "FROM {$CFG->prefix}question_answers a, " .
                                "     {$CFG->prefix}question_calculated n " .
                                "WHERE a.question = $question->id " .
                                '    AND   a.id = n.answer ' .
                                'ORDER BY a.id ASC')) {
            notify('Error: Missing question answer!');
            return false;
        }
		$question->options->params = get_records_select('ph_params', 'question='.$question->id, 'id');
		$question->options->parent = get_record_select('ph_parent', 'questionid='.$question->id);
        return true;
   
	}


    /**
     * Save the  answers associated with this question.
     */
	function save_question_options($question) {

      // Get old versions of the objects
	
        if (!$oldanswers = get_records('question_answers', 'question', $question->id, 'id ASC')) {
            $oldanswers = array();
        }

        if (!$oldoptions = get_records('question_calculated', 'question', $question->id, 'answer ASC')) {
            $oldoptions = array();
        }
	//save question params
		if ((isset($question->import_process)) && ($question->import_process == true)) {
	    	qtype_phnumerical_functions::save_question_params_from_import($question);//for import questions
        } else {
            qtype_phnumerical_functions::save_question_params($question);
			question_phnumerical_qtype::set_father($question->id, $question->fatherid);
        }
		
	if ((isset($question->import_process))&&($question->import_process==true)) {
			
	    	qtype_phnumerical_functions::save_question_parent_from_import($question);
        }
		
        // Insert all the new answers

        foreach ($question->answer as $key => $dataanswer) {
			//maybe change
            if (!isset( $question->deleteanswer[$key] ) && !( trim($dataanswer) == 0 && $question->fraction[$key]== 0 &&trim($question->feedback[$key])=='')) { 
				$answer = new stdClass;
                $answer->question = $question->id;
                if (trim($dataanswer) == '*') {
                    $answer->answer = '*';
                } else {
					if (trim(strip_tags($dataanswer))!='-') {
						if (strstr($dataanswer,'##')) {
                            // import case
                            $answer->answer = $dataanswer;
						} else {
                            //usual case
							$answer->answer = '##'.$dataanswer.'##';
						}
					}
                }
                $answer->fraction = $question->fraction[$key];
                $answer->feedback = trim($question->feedback[$key]);
		
                if ($oldanswer = array_shift($oldanswers)) {  // Existing answer, so reuse it
                    $answer->id = $oldanswer->id;

                    if (! update_record('question_answers', $answer)) {
                        $result->error = "Could not update quiz answer! (id=$answer->id)";
                        return $result;
                    }
                } else { // This is a completely new answer
                    if (! $answer->id = insert_record('question_answers', $answer)) {
                        $result->error = 'Could not insert quiz answer!';
                        return $result;
                    }
                }

                // Set up the options object
                if (!$options = array_shift($oldoptions)) {
                    $options = new stdClass;
                }
                $options->question  = $question->id;
                $options->answer    = $answer->id;
                if (trim($question->tolerance[$key]) == '') {
                    $options->tolerance = '';
                } 
                $options->tolerancetype=1;
				$options->tolerance=trim($question->tolerance[$key]);
				$options->correctanswerlength=$question->correctanswerlength[$key];
				$options->correctanswerformat=1;// check EVG
                // Save options
                if (isset($options->id)) { // reusing existing record
                    if (! update_record('question_calculated', $options)) {
                        $result->error = "Could not update quiz numerical options! (id=$options->id)";
                        return $result;
                    }
                } else { // new options
                    if (! insert_record('question_calculated', $options)) {
                        $result->error = 'Could not insert quiz numerical options!';
                        return $result;
                    }
                }
            }
		
        }
        // delete old answer records
        if (!empty($oldanswers)) {
            foreach ($oldanswers as $oa) {
                delete_records('question_answers', 'id', $oa->id);
            }
        }

        // delete old answer records
        if (!empty($oldoptions)) {
            foreach ($oldoptions as $oo) {
                delete_records('question_calculated', 'id', $oo->id);
            }
        }

        // Report any problems.
        if (!empty($result->notice)) {
            return $result;
        }
        
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
			qtype_phnumerical_functions::update_question_params($question);
            $returnurl = optional_param('returnurl');
           redirect($returnurl);
		}
	
        // See where we're coming from
        switch ($wizardnow) {
            case 'params':
                require("$CFG->dirroot/question/type/phnumerical/edit_ph_params_form.php");
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
    function tolerance_types() {
        return array(
                '1'  => get_string('relative', 'quiz'),
                '2'  => get_string('nominal', 'quiz'),
                '3'  => get_string('geometric', 'quiz'));
    }
    
    /**
     * Deletes question from the question-type specific tables
     *
     * @return boolean Success/Failure
     * @param object $question  The question being deleted
     */
    function delete_question($questionid) {
        delete_records('question_calculated', 'question', $questionid);
		delete_records('ph_params', 'question', $questionid);
		delete_records('ph_stored', 'question', $questionid);
		delete_records('ph_parent', 'questionid', $questionid);
        return true;
    }
    /**
     *	Generate new values for question variables
    */
    function create_session_and_responses(&$question, &$state, $cmoptions, $attempt) {
		$state->vars = qtype_phnumerical_functions::generate_vars($question);
        return true;
    }
    /**
     *	Restore old values of question variables, for speciefic attempt
    */
    function restore_session_and_responses(&$question, &$state) {
		if ($vars=qtype_phnumerical_functions::get_attempt_vars($question, $state->attempt)) {
			$state->vars=$vars;
        }       
        return true;
    }
    /**
     *	save the values of question variables, for speciefic attempt
    */
    function save_session_and_responses(&$question, &$state) {
		if (isset($state->vars)){
			qtype_phnumerical_functions::save_attempt_vars($question->id, $state->attempt, $state->vars);
        }
        return true;
    }
    function compare_responses(&$question, $state, $teststate) {
        if (isset($state->responses['']) && isset($teststate->responses[''])) {
            return $state->responses[''] == $teststate->responses[''];
        }
        return false;
    }

    /**
     * Checks whether a response matches a given answer, taking the tolerance
     *  into account. Returns a true for if a response matches the
     * answer, false if it doesn't.
     */
    function test_response(&$question, &$state, $answer) {
	//fix answer
        global $CFG;
		//$query = "SELECT answer, fraction, feedback FROM {$CFG->prefix}question_answers WHERE question = $question->id";
        $htmlanswers = get_records('question_answers', 'question', $question->id);
		//$result=mysql_query($query);
        $isrightanswer=0;
        //while ($answerhtml=mysql_fetch_array($result)) {
        foreach ($htmlanswers as $answerhtml) {
			$statevars = isset($state->vars) ? $state->vars : '';
            //the next line creates problems with regrade method of the quiz. To avoid it, $answer->answer was changed to $answerhtml['answer']
            //fix answer
 		    $answer->answer=qtype_phnumerical_functions::strip_math_html($answerhtml->answer, 99, $statevars, $question->id, $state->attempt);
            // Deal with the match anything answer.
            if ($answer->answer == '*') {
                return true;
            }
            $response = $state->responses[''];
            if ($response === false || !is_numeric(trim($response))) {
                return false; // The student did not type a number.
            }

            // The student did type a number, so check it with tolerances.
            $this->get_tolerance_interval($answer);

            //return ($answer->min <= $response && $response <= $answer->max);
            //added multi answer support
            if ($answer->min <= $response && $response <= $answer->max) {
                $isrightanswer++;
                $answer->feedback=$answerhtml->feedback;
                $answer->fraction=$answerhtml->fraction;
                break;
            }
        }

        return $isrightanswer;
    }
	function check_response(&$question, &$state) {
	
        $answers = &$question->options->answers;
        foreach ($answers as $aid => $answer) {
            if ($this->test_response($question, $state, $answer)) {
                return $aid;
            }
        }
        return false;
    }
	
    function get_correct_responses(&$question, &$state) {
        $correct = parent::get_correct_responses($question, $state);
		foreach($correct as $aid=>$answer)
		{
			$answer=str_replace('\\\"', '\"', $answer);//for some reason the parent
			//fix answer
            $statevars = isset($state->vars) ? $state->vars : '';
			$correct[$aid]=qtype_phnumerical_functions::strip_math_html($answer, 99, $statevars, $question->id, $state->attempt);
		}
        return $correct;
    }

    function get_all_responses(&$question, &$state) {
        $result = new stdClass;
        $answers = array();
	
        if (is_array($question->options->answers)) {
            foreach ($question->options->answers as $aid=>$answer) {
                $r = new stdClass;
		//fix answer
    			$r->answer = qtype_phnumerical_functions::strip_math_html($answer->answer, 99, $state->vars, $question->id, $state->attempt);
                $r->credit = $answer->fraction;
                $this->get_tolerance_interval($answer);

                if ($answer->max != $answer->min) {
                    $max = "$answer->max"; //format_float($answer->max, 2);
                    $min = "$answer->min"; //format_float($answer->max, 2);
                    $r->answer .= ' ('.$min.'..'.$max.')';
                }
                $answers[$aid] = $r;
            }
        }
        $result->id = $question->id;
        $result->responses = $answers;
        return $result;
    }

    function print_question_formulation_and_controls(&$question, &$state, $cmoptions, $options) {
        global $CFG;
	//fix question text, and question answers
        $statevars = isset($state->vars) ? $state->vars : ''; 
	
        $question->questiontext=qtype_phnumerical_functions::strip_math_html($question->questiontext, 5, $statevars, $question->id, $state->attempt);
		$question->questiontext=qtype_phnumerical_functions::convert_relative_src_to_absolute_src($question->questiontext, $CFG->wwwroot);
		foreach($question->options->answers as $answer)
		{
			$answer->answer=qtype_phnumerical_functions::strip_math_html($answer->answer, 99, $statevars, $question->id, $state->attempt);
		}
    /// This implementation is also used by question type 'numerical'
        $readonly = empty($options->readonly) ? '' : 'readonly="readonly"';
        $formatoptions = new stdClass;
        $formatoptions->noclean = true;
        $formatoptions->para = false;
        $nameprefix = $question->name_prefix;

		/// Print question text and media
        $questiontext =  format_text($question->questiontext,
        $question->questiontextformat,
        $formatoptions, $cmoptions->course);
        $image = get_question_image($question, $cmoptions->course);

        /// Print input controls

        if (isset($state->responses['']) && $state->responses['']!='' ) {
            $value = ' value="'.s($state->responses[''], true).'" ';
        } else {
            $value = ' value="" ';
        }
        $inputname = ' name="'.$nameprefix.'" ';
        $feedback = '';
        $class = '';
        $feedbackimg = '';

        if ($options->feedback) {
            $class = question_get_feedback_class(0);
            $feedbackimg = question_get_feedback_image(0);
            foreach ($question->options->answers as $answer) {
                if ($this->test_response($question, $state, $answer)) {
                    // Answer was correct or partially correct.
                    $class = question_get_feedback_class($answer->fraction);
                    $feedbackimg = question_get_feedback_image($answer->fraction);
                    if ($answer->feedback) {
                        $feedback = format_text($answer->feedback, true, $formatoptions, $cmoptions->course);
                    }
                    break;
                }
            }
        }

        /// Removed correct answer, to be displayed later MDL-7496
        include("$CFG->dirroot/question/type/phnumerical/display.html");
    }
	function import_from_xml(&$data, &$question, &$format, &$extra) {
		if ($data['@']['type']=='phnumerical') {
			$qo = $format->import_headers($data);
			$qo->qtype='phnumerical';
			$answers = $data['#']['answer'];
			$qo->answer = array(); // answers changed to answer by Natan
			$qo->feedback = array();
			$qo->fraction = array();
			$qo->tolerance = array();
			$qo->tolerancetype = array();
			$qo->correctanswerformat = array();
			$qo->correctanswerlength = array();
			$qo->feedback = array();

			foreach ($answers as $answer) {
				// answer outside of <text> is deprecated
				if (!empty( $answer['#']['text'] )) {
					$answertext = $format->import_text( $answer['#']['text'] );
				} else {
					$answertext = trim($answer['#'][0]);
				}
				if ($answertext == '') {
					$qo->answer[] = '*';
				} else {
					$qo->answer[] = $answertext;
				}
				$qo->feedback[] = $format->import_text( $answer['#']['feedback'][0]['#']['text'] );
				$qo->tolerance[] = $answer['#']['tolerance'][0]['#'];
				// fraction as a tag is deprecated
				if (!empty($answer['#']['fraction'][0]['#'])) {
					$qo->fraction[] = $answer['#']['fraction'][0]['#'];
				} else {
					$qo->fraction[] = $answer['@']['fraction'] / 100;
				}
				$qo->tolerancetype[] = $answer['#']['tolerancetype'][0]['#'];
				$qo->correctanswerformat[] = $answer['#']['correctanswerformat'][0]['#'];
				$qo->correctanswerlength[] = $answer['#']['correctanswerlength'][0]['#'];
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
			if (isset($data['#']['theparent'])) {
				$parent = $data['#']['theparent'];
				foreach ($parent as $father) {
					$theparent = new stdClass;
					$theparent->questionid = $father['#']['questionid'][0]['#'];
					$theparent->parentid = $father['#']['parentid'][0]['#'];
					$qo->options->parent[]=$theparent;
				}
			}
			if (isset($data['#']['parentid'])) {
				$qo->old_id = $data['#']['questionid'][0]['#'];
				$qo->old_parent = $data['#']['parentid'][0]['#'];
			}
			return $qo ;
		}
	}
	function export_to_xml(&$question, &$format, &$extra) {
		$expout='';
		foreach ($question->options->answers as $answer) {
			$tolerance = $answer->tolerance;
			$tolerancetype = $answer->tolerancetype;
			$correctanswerlength= $answer->correctanswerlength ;
			$correctanswerformat= $answer->correctanswerformat;
			$percent = 100 * $answer->fraction;
			$expout .= "<answer fraction=\"$percent\">\n";
			// "<text/>" tags are an added feature, old files won't have them
			$expout .= "    <text>{$answer->answer}</text>\n";
			$expout .= "    <tolerance>$tolerance</tolerance>\n";
			$expout .= "    <tolerancetype>$tolerancetype</tolerancetype>\n";
			$expout .= "    <correctanswerformat>$correctanswerformat</correctanswerformat>\n";
			$expout .= "    <correctanswerlength>$correctanswerformat</correctanswerlength>\n";
			$expout .= "    <feedback>".$format->writetext( $answer->feedback )."</feedback>\n";
			$expout .= "</answer>\n";
		}
//start parameters 
		$params = $question->options->params;
		if ($params) {
			foreach ($params as $param) {
				$expout .= "<parameter>\n";
				$expout .= "    <name>".$param->name."</name>\n";
				$expout .= "    <value>".$param->value."</value>\n";
				$expout .= "    <decorder>".$param->decorder."</decorder>\n";
				$expout .= "</parameter>\n";
			}
		}
		$parent = $question->options->parent;
		if ($parent) {
			$expout .= "<theparent>\n";
			$expout .= '    <questionid>'.$parent->questionid."</questionid>\n";
			$expout .= '    <parentid>'.$parent->parentid."</parentid>\n";
			$expout .= "</theparent>\n";
		}
		return $expout;
	}
    function get_tolerance_interval(&$answer) {
        // No tolerance
        if (empty($answer->tolerance)) {
            $answer->tolerance = 0;
        }

        // Calculate the interval of correct responses (min/max)
        if (!isset($answer->tolerancetype)) {
            $answer->tolerancetype = 2; // nominal
        }

        // We need to add a tiny fraction depending on the set precision to make the
        // comparison work correctly. Otherwise seemingly equal values can yield
        // false. (fixes bug #3225)
        $tolerance = (float)$answer->tolerance + ('1.0e-'.ini_get('precision'));
        switch ($answer->tolerancetype) {
            case '1': case 'relative':
                /// Recalculate the tolerance and fall through
                /// to the nominal case:
                $tolerance = $answer->answer * $tolerance;
                // Do not fall through to the nominal case because the tiny fraction is a factor of the answer

                 $tolerance = abs($tolerance); // important - otherwise min and max are swapped
                $max = $answer->answer + $tolerance;
                $min = $answer->answer - $tolerance;
                break;
            case '2': case 'nominal':
                $tolerance = abs($tolerance); // important - otherwise min and max are swapped
                // $answer->tolerance 0 or something else
                if ((float)$answer->tolerance == 0.0  &&  abs((float)$answer->answer) <= $tolerance ){
                    $tolerance = (float) ('1.0e-'.ini_get('precision')) * abs((float)$answer->answer) ; //tiny fraction
					////maybe change
                } else if ((float)$answer->tolerance != 0.0 && abs((float)$answer->tolerance) < abs((float)$answer->answer) &&  abs((float)$answer->answer) <= $tolerance){
                    $tolerance = (1+('1.0e-'.ini_get('precision')) )* abs((float) $answer->tolerance) ;//tiny fraction
               }     
               
                $max = $answer->answer + $tolerance;
                $min = $answer->answer - $tolerance;
                break;
           case '3': case 'geometric':
                $quotient = 1 + abs($tolerance);
                $max = $answer->answer * $quotient;
                $min = $answer->answer / $quotient;
                break;
            default:
                error("Unknown tolerance type $answer->tolerancetype");
        }

        $answer->min = $min;
        $answer->max = $max;
        return true;
    }

    
    
    /// BACKUP FUNCTIONS ////////////////////////////

    /**
     * Backup the data in the question
     *
     * This is used in question/backuplib.php
     */
    function backup($bf, $preferences, $question, $level=6) {

        $status = true;

        $numericals = get_records('question_calculated', 'question', $question, 'id ASC');
        //If there are numericals
        if ($numericals) {
            //Iterate over each numerical
            foreach ($numericals as $numerical) {
                $status = fwrite($bf, start_tag('NUMERICAL', $level, true));
                //Print numerical contents
                fwrite($bf, full_tag('ANSWER', $level+1, false, $numerical->answer));
                fwrite($bf, full_tag('TOLERANCE', $level+1, false, $numerical->tolerance));
                $status = fwrite ($bf, end_tag('NUMERICAL', $level, true));
            }
            //Now print question_answers
            $status = question_backup_answers($bf, $preferences, $question);
        }
        return $status;
    }

    /// RESTORE FUNCTIONS /////////////////

    /**
     * Restores the data in the question
     *
     * This is used in question/restorelib.php
     */
    function restore($old_question_id, $new_question_id, $info, $restore) {

        $status = true;

        //Get the numerical array
        $numericals = $info['#']['NUMERICAL'];

        //Iterate over numericals
        for($i = 0; $i < sizeof($numericals); $i++) {
            $num_info = $numericals[$i];

            //Now, build the question_numerical record structure
            $numerical = new stdClass;
            $numerical->question = $new_question_id;
            $numerical->answer = backup_todb($num_info['#']['ANSWER']['0']['#']);
            $numerical->tolerance = backup_todb($num_info['#']['TOLERANCE']['0']['#']);

            //We have to recode the answer field
            $answer = backup_getid($restore->backup_unique_code, 'question_answers', $numerical->answer);
            if ($answer) {
                $numerical->answer = $answer->new_id;
            }

            //The structure is equal to the db, so insert the question_numerical
            $newid = insert_record ('question_calculated', $numerical);

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

    /**
    * Performs response processing and grading
    *
    * This function performs response processing and grading and updates
    * the state accordingly.
    * @return boolean         Indicates success or failure.
    * @param object $question The question to be graded. Question type
    *                         specific information is included.
    * @param object $state    The state of the question to grade. The current
    *                         responses are in ->responses. The last graded state
    *                         is in ->last_graded (hence the most recently graded
    *                         responses are in ->last_graded->responses). The
    *                         question type specific information is also
    *                         included. The ->raw_grade and ->penalty fields
    *                         must be updated. The method is able to
    *                         close the question session (preventing any further
    *                         attempts at this question) by setting
    *                         $state->event to QUESTION_EVENTCLOSEANDGRADE
    * @param object $cmoptions
    */
    function grade_responses(&$question, &$state, $cmoptions) {
        // The default implementation uses the test_response method to
        // compare what the student entered against each of the possible
        // answers stored in the question, and uses the grade from the
        // first one that matches. It also sets the marks and penalty.
        // This should be good enought for most simple question types.
        $state->raw_grade = 0;
        foreach ($question->options->answers as $answer) {
            if ($this->test_response($question, $state, $answer)) {
                $state->raw_grade = $answer->fraction;
                break;
            }
        }

        // Make sure we don't assign negative or too high marks.
        $state->raw_grade = min(max((float) $state->raw_grade,
                            0.0), 1.0) * $question->maxgrade;

        // Update the penalty.
        $state->penalty = $question->penalty * $question->maxgrade;

        // mark the state as graded
        $state->event = ($state->event ==  QUESTION_EVENTCLOSE) ? QUESTION_EVENTCLOSEANDGRADE : QUESTION_EVENTGRADE;

        return true;
		}

	}


//start formula validation

// the same function as 'qtype_calculated_find_formula_errors'

	function qtype_phnumerical_find_formula_errors($formula) {
/// Validates the formula submitted from the question edit page.
/// Returns false if everything is alright.
/// Otherwise it constructs an error message
    // Strip away dataset names
		while (ereg('\\{[[:alpha:]][^>} <{"\']*\\}', $formula, $regs)) {
			$formula = str_replace($regs[0], '1', $formula);
		}

    // Strip away empty space and lowercase it
		$formula = strtolower(str_replace(' ', '', $formula));
		$safeoperatorchar = '-+/*';
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


	function qtype_phnumerical_find_formula_eval_errors($formula) {
		$answererrors='';
		preg_match_all('/{(.*?)}/', $formula, $paramarr);
		if ($paramarr) {
			$paramarruniq=array_unique($paramarr[1]);
			$parameters='';
			foreach ($paramarruniq as $param) {
				if (preg_match('/([^a-zA-z0-9_])/', $param)){
					$answererrors.= 'illegal parameter syntax in {'.$param.'}';
						break;
				}
				$parameters.= '$'.$param.'='.'1'.';';
			}

				if (strlen($answererrors)==0) {
					 eval($parameters);
					 ob_start();
					 try {
							$formula = preg_replace('/withsign/', 'qtype_phnumerical_functions::withsign', $formula);
							if (eval(preg_replace("/{(.*?)}/","$"."$1", $formula).';')===FALSE) {
								throw new Exception('failed to evaluate');
							}
					 } catch(Exception $e) {
							preg_match('/Parse error[^:]*:\s+(.*) in/', ob_get_contents(), $eval_error);
							//$answererrors.=$eval_error[1];
							$answererrors.='syntax error';
					 }
					 ob_end_clean();
				 }
		}
	return $answererrors;
	}
	//end formula validation



		// INITIATION - Without this line the question type is not in use.
		question_register_questiontype(new question_phnumerical_qtype());
?>

<?php
/**
 * Defines the editing form for the phmultichoice question type.
 
 * @author: atarplpl.co.il  based on Evgeny Orsky code by Technion Physics Faculty
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package questions
 */

/**
 * multiple choice editing form definition.
 */
class question_edit_phmultichoice_form extends question_edit_form {
    /**
     * Add question-type specific form fields.
     *
     * @param object $mform the form being built.
     */

    function definition_inner(&$mform) {

        global $CFG;

        //include the required js
        require_js($CFG->wwwroot . '/question/type/phmultichoice/phformula.js');
        require_js($CFG->wwwroot . '/question/type/phmultichoice/webtoolkit.md5.js');

        $mform->insertElementBefore($mform->createElement('text', 'tbmathexp', 'Math Expression: ',
								array ('size' => 400, 'style' => 'width:470px' )), 'questiontextformat');

        $htmleditor='editor_'.md5('questiontext');
        $mform->insertElementBefore($mform->createElement('button', 'btnaddmathexp', 'Insert',
																			array ('onclick' => "insertMathExpression($htmleditor,document.getElementsByName('tbmathexp')[0])" )),
																		'questiontextformat');

        $menu = array (get_string('answersingleno', 'qtype_phmultichoice'),
										get_string('answersingleyes', 'qtype_phmultichoice') );

        $mform->addElement('select', 'single', get_string('answerhowmany', 'qtype_phmultichoice'), $menu);
        $mform->setDefault('single', 1);

        $mform->addElement('advcheckbox', 'shuffleanswers', get_string('shuffleanswers', 'qtype_phmultichoice'),
									null, null, array (0, 1 ));

        $mform->setHelpButton('shuffleanswers', array ('multichoiceshuffle',
									get_string('shuffleanswers', 'qtype_phmultichoice'), 'quiz' ));

        $mform->setDefault('shuffleanswers', 1);

        $creategrades = get_grade_options();
        $gradeoptions = $creategrades->gradeoptionsfull;
        $repeated = array ( );
        $repeated [] = & $mform->createElement('header', 'choicehdr', get_string('choiceno', 'qtype_phmultichoice', '{no}'));
        //---------------------------------------------------------------------------------------------
        //the controls needed by the formula editor, for working with html editor answers
        $repeated [] = & $mform->createElement('htmleditor', 'answer');
        $repeated [] = & $mform->createElement('text', 'txtmathexp', 'Math Expression: ', array (
																																																'size' => 400,
																																																'style' => 'width:470px' ));
        $repeated [] = & $mform->createElement('button', 'btnaddmathexp', 'Insert');
        //--------------------------------------------------------------------------------------------
        $repeated [] = & $mform->createElement('select', 'fraction', get_string('grade'), $gradeoptions);
        $repeated [] = & $mform->createElement('htmleditor', 'feedback', get_string('feedback', 'quiz'));

        if (isset($this->question->options)) {
            $countanswers = count($this->question->options->answers);
        } else {
            $countanswers = 0;
        }
        $repeatsatstart = max(5, QUESTION_NUMANS_START, $countanswers + QUESTION_NUMANS_ADD);
        $repeatedoptions = array ( );
        $repeatedoptions ['fraction'] ['default'] = 0;
        $mform->setType('answer', PARAM_RAW);
        $repeatsnumber=$this->repeat_elements($repeated, $repeatsatstart, $repeatedoptions, 'noanswers',
																								'addanswers', QUESTION_NUMANS_ADD, get_string('addmorechoiceblanks',
																								'qtype_phmultichoice'));

        //----------------------------------------------------------------------------------------
        for ($i = 0; $i < $repeatsnumber; $i++) {
            $htmleditor='editor_'.md5("answer[$i]");
            $btn = $mform->getElement("btnaddmathexp[$i]");
            $attributes['onclick']="insertMathExpression($htmleditor,document.getElementsByName('txtmathexp[$i]')[0])";
            $btn->updateAttributes($attributes);
        }

        //----------------------------------------------------------------------------------------
        $mform->addElement('header', 'overallfeedbackhdr', get_string('overallfeedback', 'qtype_phmultichoice'));

        $mform->addElement('htmleditor', 'correctfeedback', get_string('correctfeedback', 'qtype_phmultichoice'));
        $mform->setType('correctfeedback', PARAM_RAW);

        $mform->addElement('htmleditor', 'partiallycorrectfeedback',
									get_string('partiallycorrectfeedback', 'qtype_phmultichoice'));

        $mform->setType('partiallycorrectfeedback', PARAM_RAW);

        $mform->addElement('htmleditor', 'incorrectfeedback', get_string('incorrectfeedback', 'qtype_phmultichoice'));
        $mform->setType('incorrectfeedback', PARAM_RAW);

        $mform->addElement('hidden', 'wizard', 'params');
        $mform->setType('wizard', PARAM_ALPHA);

        $mform->addElement('hidden', 'parent',optional_param('parent', 0, PARAM_INT));
        $mform->setType('parent', PARAM_INT);

		$mform->addElement('hidden', 'fatherid',optional_param('fatherid', 0, PARAM_INT));
        $mform->setType('fatherid', PARAM_INT);
        
		if($fname = optional_param('fname') ) {
			$mform->setDefault('name',$fname);
		}

		$mform->_defaultValues['fraction[0]']=1;
    }

    function set_data($question) {
		global $CFG ;
        if (isset($question->options)) {
            $answers = $question->options->answers;
            if (count($answers)) {
                $key = 0;
                foreach ($answers as $answer) {
                    $default_values ['answer[' . $key . ']'] = $answer->answer;
                    $default_values ['fraction[' . $key . ']'] = $answer->fraction;
                    $default_values ['feedback[' . $key . ']'] = $answer->feedback;
                    $key ++;
                }
            }
            $default_values ['single'] = $question->options->single;
            $default_values ['shuffleanswers'] = $question->options->shuffleanswers;
            $default_values ['correctfeedback'] = $question->options->correctfeedback;
            $default_values ['partiallycorrectfeedback'] = $question->options->partiallycorrectfeedback;
            $default_values ['incorrectfeedback'] = $question->options->incorrectfeedback;

			//count num of parts-'sons' in this question
			$partnum = count_records('ph_parent', 'parentid', $question->id);
			//start from part 2
            $partnum += 2;

			if (((empty($this->question->id)) && (($this->question->formoptions->canedit || ! $this->question->formoptions->cansaveasnew)))) {
				$permission = 1;
			}
			else {
				$permission = 0;
			}
			//if has perrmission and father exist - allow creare son
			if (! $this->question->formoptions->movecontext || $permission) {
				$this->_form->insertElementBefore($this->_form->createElement('button', 'addsubn', 'Sub Question(n)',
									array ('onclick' => "window.location='" . basename($_SERVER ['PHP_SELF']) . "?fname=".$question->name.
									" (part $partnum)"."&fatherid=".$question->id . "&qtype=phnumerical&category=".$question->category .
									"&courseid=" . $question->courseid . "'" )), 'questiontext');

				$this->_form->insertElementBefore($this->_form->createElement('button', 'addsubm', 'Sub Question(m)',
									array ('onclick' => "window.location='" . basename($_SERVER ['PHP_SELF']) . "?fname=".$question->name.
									" (part $partnum)"."&fatherid=".$question->id. "&qtype=phmultichoice&category=".$question->category.
									"&courseid=" . $question->courseid . "'" )), 'questiontext');

			}
			$question = ( object ) (( array ) $question + $default_values);
        }
        parent::set_data($question);
    }

    function qtype() {
        return 'phmultichoice';
    }

    function validation($data) {
        $errors = array ( );

        //start QUESTIONTEXT formula validation
        $code = array ( ); //array wich will contain the matched string
        $formula = $data ['questiontext'];
        //replace line breaks that can damage the match

	//all var must be writen in ##{v}## pormat
        while (preg_match("/##(.*?)##/", $formula, $code)) {
             //every rotation check only one
			if (! empty($code [1])) {
                $questiontexterrors = '';
                $formulaerrors = qtype_phmultichoice_find_formula_errors($code [1]);
                if (FALSE !== $formulaerrors) {
                    $questiontexterrors .= $formulaerrors;
                } else {
                    $code [1] = preg_replace('/\s+/', '', $code [1]);
                    //check that there are no ##{a+*b}## etc
					if (preg_match('/([\-\+\*\/])([\-\+\*\/])/', $code [1])) {
                        $questiontexterrors .= 'Operator (+-*/) cannot folow another operator';
                    }
                }
                if (strlen($questiontexterrors) == 0) {
                    $questiontexterrors .= qtype_phmultichoice_find_formula_eval_errors($code [1]);
                }

                if ($questiontexterrors != '') {
                    if (isset($errors ['questiontext'])) {
                        $errors ['questiontext'] .= '<br/>' . $questiontexterrors . ' in the:  ' . $code [0];
                    } else {
                        $errors ['questiontext'] = $questiontexterrors . ' in the:  ' . $code [0];
                    }
                }
            }
			//replace it to {v} formt and check the next
            $formula = preg_replace("/##(.*?)##/", '', $formula, 1);
        }
        //end QUESTIONTEXT formula validation


        // Check the answers.
        $answercount = 0;
        $answers = $data ['answer'];
        $totalfraction = 0;
        $maxfraction = - 1;

        foreach ($answers as $key => $answer) {
            $trimmedanswer = trim($answer);
            if (! empty($trimmedanswer)) {
                $answercount ++;
            }
            //check grades
            if ($answer != '') {
                if ($data ['fraction'] [$key] > 0) {
                    $totalfraction += $data ['fraction'] [$key];
                }
                if ($data ['fraction'] [$key] > $maxfraction) {
                    $maxfraction = $data ['fraction'] [$key];
                }
            }

            //start ANSWERS formula validation
            $code = array ( ); //array wich will contain the matched string
            $formula = $trimmedanswer;
            //replace line breaks that can damage the match
            //$formula=preg_replace("/[\n\r]/","",$formula);


            while (preg_match("/##(.*?)##/", $formula, $code)) {
                $trimmedanswer = $code [1];
                if (($trimmedanswer != '') || $answercount == 0) {
                    $answererrors = '';
                    $eqerror = qtype_phmultichoice_find_formula_errors($trimmedanswer);
                    if (FALSE !== $eqerror) {
                        $answererrors .= $eqerror;
                    } else {
                        $trimmedanswer = preg_replace('/\s+/', '', $trimmedanswer);
                        if (preg_match('/([\-\+\*\/])([\-\+\*\/])/', $trimmedanswer)) {
                            $answererrors .= 'Operator (+-*/) cannot folow another operator';
                        }
                    }
                    if (strlen($answererrors) == 0) {
                        $answererrors .= qtype_phmultichoice_find_formula_eval_errors($trimmedanswer);
                    }
                    if ($answererrors != '') {
                        if (isset($errors ['answer[$key]'])) {
                            $errors ['answer[' . $key . ']'] .= '<br/>' . $answererrors . ' in the:  ' . $code [0];
                        } else {
                            $errors ['answer[' . $key . ']'] = $answererrors . ' in the:  ' . $code [0];
                        }
                    }

                }
                $formula = preg_replace("/##(.*?)##/", '', $formula, 1);
            }
        }

        //end ANSWERS formula validation



        if ($answercount == 0) {
            $errors ['answer[0]'] = get_string('notenoughanswers', 'qtype_phmultichoice', 2);
            $errors ['answer[1]'] = get_string('notenoughanswers', 'qtype_phmultichoice', 2);
        } elseif ($answercount == 1) {
            $errors ['answer[1]'] = get_string('notenoughanswers', 'qtype_phmultichoice', 2);

        }

        /// Perform sanity checks on fractional grades
        if ($data ['single']) {
            if ($maxfraction != 1) {
                $maxfraction = $maxfraction * 100;
                $errors ['fraction[0]'] = get_string('errfractionsnomax', 'qtype_phmultichoice', $maxfraction);
            }
        } else {
            $totalfraction = round($totalfraction, 2);
            if ($totalfraction != 1) {
                $totalfraction = $totalfraction * 100;
                $errors ['fraction[0]'] = get_string('errfractionsaddwrong', 'qtype_phmultichoice', $totalfraction);
            }
        }
        return $errors;
    }
}
?>

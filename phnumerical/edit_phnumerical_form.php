<?php
/**
 * Defines the editing form for the phnumerical question type.
 *
 * @author: atarplpl.co.il  based on Evgeny Orsky code by Technion Physics Faculty
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package questions
 */

class question_edit_phnumerical_form extends question_edit_form {
    /**
     * Add question-type specific form fields.
     *
     * @param MoodleQuickForm $mform the form being built.
     */
    var $qtypeobj;

	function definition_inner(&$mform) {
        global $CFG, $QTYPES;

        //include the required js
        require_js($CFG->wwwroot .'/question/type/phnumerical/phformula.js');
        require_js($CFG->wwwroot . '/question/type/phnumerical/webtoolkit.md5.js');

        $mform->insertElementBefore($mform->createElement('text', 'tbmathexp', 'Math Expression: ', array (
																																																				'size' => 400,
																																																				'style' => 'width:470px'
																																																				)),
																																																				'questiontextformat');
        $htmleditor='editor_'.md5('questiontext');
        $mform->insertElementBefore($mform->createElement('button', 'btnaddmathexp', 'Insert',
									array ('onclick' => "insertMathExpression($htmleditor,document.getElementsByName('tbmathexp')[0])" )),
									'questiontextformat');

        $this->qtypeobj = & $QTYPES [$this->qtype()];
        $mform->addElement('hidden', 'initialcategory', 1);
        $addfieldsname = 'updatecategory';
        $addstring = get_string("updatecategory", "qtype_phnumerical");
        $mform->registerNoSubmitButton($addfieldsname);

        $repeated = array ( );
        $repeated [] = & $mform->createElement('header', 'answerhdr', get_string('answerhdr', 'qtype_phnumerical', '{no}'));
        //---------------------------------------------------------------------------------------------
      //  the controls needed by the formula editor
        $repeated [] = & $mform->createElement('text', 'answer', get_string('correctanswerformula', 'quiz') . '=', array (
																																																									'size' => 50 ));

        //---------------------------------------------------------------------------------------------


        $creategrades = get_grade_options();
        $gradeoptions = $creategrades->gradeoptions;
        $repeated [] = & $mform->createElement('select', 'fraction', get_string('grade'), $gradeoptions);
        $repeatedoptions ['fraction'] ['default'] = 0;

        $repeated [] = & $mform->createElement('text', 'tolerance', get_string('tolerance', 'qtype_phnumerical'));
        $repeatedoptions ['tolerance'] ['type'] = PARAM_NUMBER;
        $repeatedoptions ['tolerance'] ['default'] = 0.01;

        $repeated [] = &  $mform->createElement('select', 'correctanswerlength', get_string('correctanswershows',
																																													'qtype_phnumerical'), range(0, 9));

        $repeatedoptions ['correctanswerlength'] ['default'] = 2;

        $answerlengthformats = array (
																			'1' => get_string('decimalformat', 'quiz'),
																			'2' => get_string('significantfiguresformat', 'quiz') );

        $repeated [] = &  $mform->createElement('htmleditor', 'feedback', get_string('feedback', 'quiz'));
        $repeatedoptions ['feedback'] ['type'] = PARAM_RAW;

        if (isset($this->question->options)) {
            $count = count($this->question->options->answers);
        } else {
            $count = 0;
        }
        $repeatsatstart = $count + 1;
        $this->repeat_elements($repeated, $repeatsatstart, $repeatedoptions, 'noanswers',
												'addanswers', 1, get_string('addmoreanswerblanks', 'qtype_phnumerical'));

        //------------------------------------------------------------------------------------------
        $mform->addElement('hidden', 'wizard', 'params');
        $mform->setType('wizard', PARAM_ALPHA);


		$mform->addElement('hidden', 'fatherid',optional_param('fatherid', 0, PARAM_INT));
        $mform->setType('fatherid', PARAM_INT);

		//the defalt name of 'son' question is father (part #)
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
                    $answer->answer = str_replace("##", "", $answer->answer);
                    $default_values ['answer[' . $key . ']'] = $answer->answer;
                    $default_values ['answer[' . $key . ']'] = strip_tags($answer->answer);  
                    $default_values ['fraction[' . $key . ']'] = $answer->fraction;
                    $default_values ['tolerance[' . $key . ']'] = $answer->tolerance;
                    $default_values ['tolerancetype[' . $key . ']'] = $answer->tolerancetype;
                    $default_values ['correctanswerlength[' . $key . ']'] = $answer->correctanswerlength;
                    $default_values ['correctanswerformat[' . $key . ']'] = $answer->correctanswerformat;
                    $default_values ['feedback[' . $key . ']'] = $answer->feedback;
                    $key ++;
		    
                }
            }

	
			if (((empty($this->question->id)) && (($this->question->formoptions->canedit || ! $this->question->formoptions->cansaveasnew)))) {
				$permission = 1;
			}
			else {
				$permission = 0;
			}
			//if has perrmission and father exist - allow creare son
			if (! $this->question->formoptions->movecontext || $permission) {
				//count num of parts-'sons' in this question
				$partnum = count_records('ph_parent', 'parentid', $question->id);
                //start from part 2
				$partnum += 2;
				$this->_form->insertElementBefore($this->_form->createElement('button', 'addsubn', 'Sub Question(n)',
										array ('onclick' => "window.location='" . basename($_SERVER ['PHP_SELF']) . "?fname=".
										$question->name." (part $partnum)"."&fatherid=" .$question->id. "&qtype=phnumerical&category=".
										$question->category . "&courseid=" . $question->courseid . "'" )), 'questiontext');

                $this->_form->insertElementBefore($this->_form->createElement('button', 'addsubm', 'Sub Question(m)',
										array ('onclick' => "window.location='" . basename($_SERVER ['PHP_SELF']) . "?fname=".
										$question->name." (part $partnum)"."&fatherid=" .$question->id. "&qtype=phmultichoice&category=" .
										$question->category . "&courseid=" . $question->courseid . "'" )), 'questiontext');
             
            }

            $question = ( object ) (( array ) $question + $default_values);
        }

        parent::set_data($question);
    }
    function validation($data, $files) {
        $errors = parent::validation($data, $files);


        //start QUESTIONTEXT formula validation
        $code = array ( ); //array wich will contain the matched string
        $formula = $data ['questiontext'];
		//all var must be writen in ##{v}## pormat
        while (preg_match("/##(.*?)##/", $formula, $code)) {
            //every rotation check only one
			if (! empty($code [1])) {
                $questiontexterrors = '';
                $formulaerrors = qtype_phnumerical_find_formula_errors($code [1]);
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
                    $questiontexterrors .= qtype_phnumerical_find_formula_eval_errors($code [1]);
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
        $maxgrade = false;
        $answers = $data ['answer'];
		//check that there is 100% grade
        foreach ($answers as $key => $answer) {
            $trimmedanswer = trim($answer);
            $answercount ++;
            if ($trimmedanswer != '') {
                if ($data ['fraction'] [$key] == 1) {
                    $maxgrade = true;
                }
            }

            //start ANSWERS formula validation


            if (($trimmedanswer != '') || $answercount == 0) {
                $answererrors = '';
                $eqerror = qtype_phnumerical_find_formula_errors($trimmedanswer);
                if (FALSE !== $eqerror) {
                    $answererrors .= $eqerror;
                } else {
                    $trimmedanswer = preg_replace('/\s+/', '', $trimmedanswer);
                    if (preg_match('/([\-\+\*\/])([\-\+\*\/])/', $trimmedanswer)) {
                        $answererrors .= 'Operator (+-*/) cannot folow another operator';
                    }
                }
                if (strlen($answererrors) == 0) {
                    $answererrors .= qtype_phnumerical_find_formula_eval_errors($trimmedanswer);
                }
                if ($answererrors != '') {
                    $errors ['answer[' . $key . ']'] = $answererrors;
                }

            }
        }

        //end ANSWERS formula validation


        if ($answercount == 0) {
            $errors ['answer[0]'] = get_string('notenoughanswers', 'qtype_phnumerical');
        }
        if ($maxgrade == false) {
            $errors ['fraction[0]'] = get_string('fractionsnomax', 'question');
        }

        return $errors;
    }
    function qtype() {
        return 'phnumerical';
    }

}
?>

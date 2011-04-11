<?php
/*
 * @author: atarplpl.co.il  based on Evgeny Orsky code by Technion Physics Faculty
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package questions
 */
class question_ph_params_form extends moodleform {
    /**
	 * in this form we set value for variables in question
	 *
	 *
     * Question object with options and answers already loaded by get_question_options
     * Be careful how you use this it is needed sometimes to set up the structure of the
     * form in definition_inner but data is always loaded into the form with set_defaults.
     *
     * @var object
     */
    var $question;
    /**
     * Reference to question type object
     *
     * @var question_dataset_dependent_questiontype
     */
    var $qtypeobj;

    var $datasetdefs;

    /**
     * Add question-type specific form fields.
     *
     * @param MoodleQuickForm $mform the form being built.
     */
    /**
     * The class constructor, selects from db the question params
    */
    

	function question_ph_params_form($submiturl, $question) {
		function definition_inner(&$mform) {
			global $CFG, $QTYPES;
			$mform->addElement ( 'htmleditor', 'qtype' );
		}

        global $QTYPES, $CFG;
       
        $this->question = $question;
        $this->qtypeobj =& $QTYPES[$this->question->qtype];

	//get the dataset defintions for this question
       $this->datasetdefs = get_records_select('ph_params', 'question='.$question->id, 'decorder');

       parent::moodleform($submiturl);
    }


    /**
     * Defines the visual form. it builds params table based on the data from db
     * this form is the second page in the wizard in qtypes- ph_multichoice, ph_numerical
    */
	function definition() {
		$mform =& $this->_form;
		//every var can be either constant value or random betwwen specify size
		$html='<script type="text/javascript">function checkrb(rb_obj,row_id){rb_obj[row_id].checked=true;} </script>';
		$html.='<table align="center" style="border-width:1px;border-style:solid;border-color:gray;" cellpadding=5 cellspacing=5>';
		$j=0;

        foreach ($this->datasetdefs as $defkey => $datasetdef) {
			$isrand = '';
			$isvalue = ' Checked ';
			$varvalue = $datasetdef->value;
			$randfrom = "''";
			$randto = "''";
			$randstep = "''";
			$varorder = $datasetdef->decorder;
			//if rand checked
			if(strstr($datasetdef->value, 'rand')) {
				$isvalue = '';
				$randfrom = substr($varvalue, 8, strpos($varvalue, '/')-9);
				$randto = substr($randfrom, 0, strpos($randfrom, '-', 1));
				$randfrom = '"'.substr($randfrom, strlen($randto)+1, strlen($randfrom)-1).'"';
				$randto = '"'.$randto.'"';
				$randstep = substr($varvalue, strpos($varvalue, '*')+1, strlen($varvalue)-strpos($varvalue, '*')+1);
				$randstep = '"'.substr($randstep, 0, strpos($randstep, "+")).'"';
				$varvalue = '';
				$isrand = ' Checked ';
			}
			$html.= '<tr><td valign="top" align="right">
							<input type="text" name="varorder'.$j.'" value="'.$varorder.'" size="2" onclick="checkrb(vr'.$j.',0)"/>'.
							'</td><td valign="top"><input type="hidden" name="varid'.$j.'" value="'.$datasetdef->id.'"/>
							<input type="hidden" name="v'.$j.'" value="'.$datasetdef->name.'"/>'.str_ireplace('$','{',$datasetdef->name).'}'.
							'</td><td><input type="radio" name="vr'.$j.'" id="vr'.$j.'" value="0" '.$isvalue.'/> Value:&nbsp;&nbsp;
							<input type="text" name="value'.$j.'" id="value'.$j.'" value="'.$varvalue.'" size="40" onclick="checkrb(vr'.$j.',0)"/>
							<br/>'.'<input type="radio" name="vr'.$j.'" id="vr'.$j.'" value="1"'.$isrand.'/>
							Range: from - <input type="text" name="from'.$j.'" id="from'.$j.'" size="5" value='.$randfrom.
							' onclick="checkrb(vr'.$j.',1)"/> to - <input type="text" name="to'.$j.'" id="to'.$j.'" size="5" value='.$randto.
							' onclick="checkrb(vr'.$j.',1)"/> step - <input type="text" name="step'.$j.'" id="step'.$j.
							'" size="5" value='.$randstep.' onclick="checkrb(vr'.$j.
							',1)"/></td></tr>';
			$j++;

        }
			

		$html.='</table>';
		$mform->addElement('html', $html);
		$mform->addElement('submit', 'backtoquiz', get_string('savechanges'));
	
        //hidden elements
        $mform->addElement('hidden', 'qtype');
        $mform->addElement('hidden', 'category');
        $mform->setType('category', PARAM_INT);
        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'wizard', 'params');
        $mform->setType('wizard', PARAM_ALPHA);
        $mform->addElement('hidden', 'movecontext');
        $mform->setType('movecontext', PARAM_BOOL);
        $mform->addElement('hidden', 'cmid');
        $mform->setType('cmid', PARAM_INT);
        $mform->setDefault('cmid', 0);
        $mform->addElement('hidden', 'courseid');
        $mform->setType('courseid', PARAM_INT);
        $mform->setDefault('courseid', 0);
        $mform->addElement('hidden', 'returnurl');
        $mform->setType('returnurl', PARAM_LOCALURL);
        $mform->setDefault('returnurl', 0);
	
    }

}

?>

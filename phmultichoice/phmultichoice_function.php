<?php
/**
 *
 * @author: atarplpl.co.il  based on Evgeny Orsky code by Technion Physics Faculty
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package questions
 
 * all functions for phmultichioce
 *
 *  save_attempt_vars - save specifiec declaration string for specifiec attempt
 *   for example: $b=3;$c=2;$e=5;
 *   to review the attemp after it was finished, we need to save the values of variables
 *  used in this attempt.
 *  $question -> question id
 *  $attemptid-> attempt id
 *  $vars     -> declaration string
 */
/** */

class qtype_phmultichoice_functions {
	 //save for every attempt the values of vars that was in it
	function save_attempt_vars($question, $attemptid, &$vars) {
		global $CFG;
		$attempvars = new stdClass();
		$attempvars->attemptid = $attemptid;
		$attempvars->question = $question;
		$attempvars->vars = $vars;
		if (!insert_record('ph_stored', $attempvars)) {
			echo 'failed to insert variables';
		}
	}

/**
 *   get_attempt_vars - get specifiec declaration string for specifiec attempt
 *   for example: $b=3;$c=2;$e=5;
 *   to review the attemp after it was finished, we need to get the values of variables
 *  used in this attempt.
 *   $question -> question object
 *   $attemptid-> attempt id
 *   returns    -> declaration string
 */
	//get the values of var in this attemp
	function get_attempt_vars(&$question, $attemptid) {
		global $CFG;
		$storedvars = get_records_select('ph_stored', 'attemptid = '.$attemptid .' AND question = '.$question->id);
		if (! $storedvars) {
			echo 'failed to get variables';
		} else {
			//take one - does not matter which
			foreach ($storedvars as $last) {
			}
			return $last->vars;
		}
	}

		

/**
 * generate_vars - generate specifiec declaration string for specifiec question
 *  for example: $b=3;$c=2;$e=5;
 *  to initialize  attemp for the first time, we need to generate new values for variables
 *  used in the question.
 *  $id -> question id
 *  return the newly generated declaration string
 */
	function generate_vars($question) {
		global $CFG;
		$id = $question->id;
		////get all the variables of the question order by decorder, for declaration order
		$variables = get_records('ph_params', 'question', $id, 'decorder');
		$vars = '';
		if ($variables) {
			foreach ($variables as $variable) {
				ob_start();
			    //evaluate declaration string that
				//was built until now, because other variables values,
				//maybe based on previous variables values.
				$vars .= $variable->name . '=';
				//the next to line are for correcting syntax,
				//php will be confused if it sees ++, or --,
				//it will try to add 1, or to sub 1.
				$variable->value = str_replace('++', '+', $variable->value);
				$variable->value = str_replace('--', '+', $variable->value);
				ob_start();
				eval('$vars.=' . $variable->value . ';');
				//Check if the value of $a_row['value'] is not null, to avoid the parsing errors in the question preview
				$vars .= ';';
				ob_end_clean();
			}
		}
    return $vars;
	}
/**
 *strip_math_html, is for preparing the question information wich contains
 *variables, for proper view. this formulas are in tables or span tags from class Code.
 *I used RegExp for searching this specifiec pattern, and replacing it with the
 *evaluated string.
 *$formula - it can be questiontext,question answers
 *$floats - number of floats when evaluating mathematical expression.
 *$vars - the variables declaration string
 *return evaluated formula, all mathematical expression replaced with a value.
 */
	function strip_math_html($formula, $floats, $vars, $questionid, $attemptid) {
		global $CFG;
		$variables = explode(';', $vars);

		foreach ($variables as $var) {
			if (stristr($var, '=') != '=') {
				eval($var.';');
			}
		}
		$father = get_record('ph_parent', 'questionid', $questionid);
		//evaluate params of parent question
		 while ($father->parentid > 0) {
			$params = get_records_select('ph_stored', 'attemptid = '.$attemptid.' AND question = '.$father->parentid);
			if (! $params) {
				$father->parentid = 0;
			} else {
				foreach ($params as $param) {
					eval($param->vars);
				}
			}
			$father = get_record('ph_parent', 'questionid', $father->parentid);
			if (! $father) {
				$father->parentid = 0;
			}
		}
 
		$code = array ( ); //array wich will contain the matched string
		//replace line breaks that can damage the match
		$formula = preg_replace("/[\n\r]/", '', $formula);
		//replace all span class = Code
		while (preg_match('/##(.*?)##/', $formula, $code)) {
			$code [1] = str_replace('{', '$', $code [1]);
			$code [1] = str_replace('}', '', $code [1]);
			$code[1] = preg_replace('/withsign/', 'self::withsign', $code[1]);
			eval("\$stripstr = " . $code [1] . ';');
			if (strstr($stripstr, '+') === FALSE) {
				$stripstr = round($stripstr, $floats);
			} else {
				$stripstr = self::withsign(round($stripstr, $floats));
			}
			$formula = preg_replace('/##(.*?)##/', $stripstr, $formula, 1);
		}

		//replace all span class = mathExpession

		while (preg_match("/<span class=\"mathExpression\" dir=\"ltr\">(.*?)<\/span>/", $formula, $code)) {
			$formula = preg_replace("/<span class=\"mathExpression\" dir=\"ltr\">(.*?)<\/span>/", $code [1], $formula, 1);
		}

		while (preg_match("/<span dir=\"ltr\" class=\"mathExpression\">(.*?)<\/span>/", $formula, $code)) {
			$formula = preg_replace("/<span dir=\"ltr\" class=\"mathExpression\">(.*?)<\/span>/", $code [1], $formula, 1);
		}
		self::strip_html($formula);
		return $formula;

	}
	function strip_html(&$formula) {
        //replace all span class = mathExpession
        $count=array(1,1);
        while ($count[1] || $count[2]) {
                $formula = preg_replace("/(<span[^>]+)style=\"[^\"]+\"([^>]+class=\"mathExpression\"[^>]*>(.*)<\/span>)/U",
														"$1$2", $formula, -1, $count[1]);

                $formula = preg_replace("/(<span[^>]+class=\"mathExpression\"[^>]+)style=\"[^\"]+\"([^>]*>(.*)<\/span>)/U",
														"$1$2", $formula, -1, $count[2]);

        }
	}

	function withsign($numb) {
		return $numb >= 0 ? '+' . $numb : $numb;
	}

/**
 *convert_relative_src_to_absolute_src - convert all relative sources in $text
 *to absolute by adding $wwwroot/question/ to it.
 *we want to store in db relative src, but it is used in multiple pages,
 *that aren't in the same directory, so we need to convert it to absolute src,
 *for it to avaliable from every page.
 */
	function convert_relative_src_to_absolute_src($text, $wwwroot) {
		return preg_replace("/(src)=\"(?!http|ftp|https|\/)([^\"]*)\"/", "$1=\"" . $wwwroot . "/question/" . "\$2\"", $text);
	}
/**
 *save_question_params - this is used for saving and updating question variables
 *$question -> the question object
 *on error returns the error
 */


	function update_question_params(&$question) {
		//get currently stored in db params
		$oldparams = get_records_select('ph_params', 'question=' . $question->id, 'decorder');
		$i = 0;
		while ($v = optional_param('v' . $i)) //Get the posted params
		{
			$param = new stdClass(); //create param object
			$param->question = $question->id;
			$param->name = $v;
			$param->id = optional_param('varid' . $i); //Get posted param id
			$isrange = optional_param('vr' . $i); //Get seleceted choise value or random
			$value = '';
			if ($isrange) { //if random, build rand evaluation  string
				$min = optional_param('from' . $i);
				$max = optional_param('to' . $i);
				$step = optional_param('step' . $i);
				$value = "rand(0,($max-$min)/$step)*$step+$min";
			} else {
				$value = optional_param('value' . $i);
			}
			$param->value = $value;
			$param->decorder = optional_param('varorder' . $i);
			 //if already in db update
			if (isset($oldparams [$param->id])) {
				if (! update_record('ph_params', $param)) {
					$result->error = 'Could not update quiz params!';
					return $result;
				}
			}
			$i ++;
		}

	}
	//for new vars
	function save_question_params(&$question) {
		$code = array ( ); //array wich will contain the matched string
		$paramsfromeditform = array ( ); //array contains parameters names from the edit question form
		$paramsfromdb = array ( ); //array contains parameters names from db
		$ismakecopy = false;

		if (optional_param('id')) {
			if (optional_param('id') != $question->id) {
				$ismakecopy = true;
			}
			$questionid = optional_param('id');
		} else {
			$questionid = $question->id;
		}
		 //array contains parameters objects from db
		$oldparams = get_records_select('ph_params', 'question=' . $questionid, 'decorder');
		$formula = $question->questiontext;
		foreach ($question->answer as $answer) {
			$formula .= $answer;
		}
		while (preg_match('/##(.*?)##/', $formula, $code)) {
			if (! empty($code [1])) {
				preg_match_all("/{(.*?)}/", $code [1], $paramarr);
				$paramsfromeditform = array_merge($paramsfromeditform, $paramarr [1]);
			}
			$formula = preg_replace('/##(.*?)##/', '', $formula, 1);
		}
		$paramsfromeditform = array_unique($paramsfromeditform);
		if ($oldparams) {
			foreach ($oldparams as $key => $param) {
				$par = str_replace('$', '', $param->name);
				if (! in_array($par, $paramsfromeditform)) {
					delete_records('ph_params', 'id', $key);
				} elseif ($ismakecopy) {
					$oldparams [$key]->question = $question->id;
					insert_record('ph_params', $param);
				}
				array_push($paramsfromdb, $par);
			}
			$newparams = array_diff($paramsfromeditform, $paramsfromdb);

		} else {
			$newparams = $paramsfromeditform;
		}
		foreach ($newparams as $parameter) {
			$param = new stdClass();
			$param->question = $question->id;
			$param->name = '$' . $parameter;
			$param->value = '';
			$param->decorder = '';
			insert_record('ph_params', $param);
		}
	}

	//start export-import
	function save_question_params_from_import(&$question) {
		if (isset($question->options->params)) {
			foreach ($question->options->params as $parameter) {
				$param = new stdClass();
				$param->question = $question->id;
				$param->name = $parameter->name;
				$param->value = $parameter->value;
				$param->decorder = $parameter->decorder;
				insert_record('ph_params', $param);
			}
		}
	}
	//end export-import
	function save_question_parent_from_import(&$question) {
		global $CFG;
	   if (isset($question->old_parent)) {
			$parent = new stdClass;
			$parent->old_id = $question->old_id;
			$parent->old_parent = $question->old_parent;
			$parent->questionid = $question->id;
			insert_record('ph_parent', $parent);
		}

		if (isset($question->options->parent)) {
			foreach ($question->options->parent as $theparent) {
				$father = new stdClass();
				$father->old_id = $theparent->questionid;
				$father->questionid = $question->id;
				$father->old_parent = $theparent->parentid;
				insert_record('ph_parent', $father);
			}
		}
	}



}
?>

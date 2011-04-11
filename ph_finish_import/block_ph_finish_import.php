<?php 
// Author: Atar + Plus
//* @copyright	Copyright (C) Open Source Matters. All rights reserved.
//* @license		GNU/GPL

class block_ph_finish_import extends block_base {
	
    function init() {
            $this->title = get_string('ph_finish_import','block_ph_finish_import');
            $this->version = 20090616;
    }
    
    function get_content() {
      global $CFG;
        $output = '';
        if ($this->content !== NULL) {
          
            return $this->content;
        }
        
        $this->content = new stdClass;
        //check if form was submitted to fix questions
        if (isset($_POST['doit']) && isset($_POST['submit'])) {
            
            $query = "SELECT questionid ,old_parent FROM {$CFG->prefix}ph_parent";
            $result = mysql_query($query);
            
            while($a_raw = mysql_fetch_array($result)){
                   $i=0;
          
                   if (!$a_raw['old_parent']==0){

                    $query1 = "SELECT questionid FROM {$CFG->prefix}ph_parent"." WHERE old_id='$a_raw[old_parent]'";
                    $result1 = mysql_query($query1);
                    if(!  $a_raw1 = mysql_fetch_array($result1)){
                     $output .="Warning ! There are questions that their parent does not exist . ";
                  }
                    $parentid = $a_raw1[0];
            $old_parent = 0;
            $old_id = 0 ;
            $questionid = $a_raw['questionid'];
            $update = "UPDATE {$CFG->prefix}ph_parent " . " SET old_parent = '0', old_id = '0' ,parentid ='$parentid' WHERE questionid='$questionid'";
            mysql_query($update);
            
            }
         }
            $query2 = "SELECT questionid FROM {$CFG->prefix}ph_parent";
            $result2 = mysql_query($query2);
            while($a_raw = mysql_fetch_array($result2)){
              $update1 = "UPDATE {$CFG->prefix}ph_parent " . " SET old_id = '0'  WHERE questionid='$a_raw[questionid]'";
              mysql_query($update1);
                            }
            $output .= 'the questions are ready to use';

        } else {
        // check that there is work to do
        // means that old_id and old_parent on parent table are sometime non zero
            $qNonZero="SELECT count(*) as howMuch FROM {$CFG->prefix}ph_parent WHERE old_id != 0 OR old_parent != 0";
            $myResult=mysql_query($qNonZero) or die(mysql_error()." query was ".$qNonZero);
            $myrow=mysql_fetch_assoc($myResult);
            if ($myrow['howMuch'] > 0) {
                $output="<form method=post><input type=checkbox name=doit>Fix Questions<br><input type=submit name=submit></form>";
            } else
                $output.="Nothing to do";
        }

        $this->content->text = '<center>'.$output.'</center>';
        $this->content->footer = 'Physics Question Block';
             
        return $this->content;
    }
   


    function applicable_formats() {
        return array('site-index' => true,
                     'course-view' => true, 'course-view-social' => true,
                     'mod' => false, 'mod-quiz' => false);
    }

}

?>

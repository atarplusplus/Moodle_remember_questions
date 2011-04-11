<?php  //$Id: upgrade.php,v 1.3.2.2 2007/03/14 21:10:49 tjhunt Exp $

// This file keeps track of upgrades to 
// the numerical qtype plugin
//
// Sometimes, changes between versions involve
// alterations to database structures and other
// major things that may break installations.
//
// The upgrade function in this file will attempt
// to perform all the necessary actions to upgrade
// your older installtion to the current version.
//
// If there's something it cannot do itself, it
// will tell you what you need to do.
//
// The commands in here will all be database-neutral,
// using the functions defined in lib/ddllib.php

function xmldb_qtype_phnumerical_upgrade($oldversion=0) {

    global $CFG, $THEME, $db;

    $result = true;

    return $result;
}

?>

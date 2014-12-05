<?php

/* Copyright   2014 fdp1
 * 
 * This work is free. You can redistribute it and/or modify it under the
 * terms of the Do What The Fuck You Want To Public License, Version 2,
 * as published by Sam Hocevar. See the COPYING file for more details.
 * 
 * This program is free software. It comes without any warranty, to
 * the extent permitted by applicable law. You can redistribute it
 * and/or modify it under the terms of the Do What The Fuck You Want
 * To Public License, Version 2, as published by Sam Hocevar. See
 * http://www.wtfpl.net/ for more details.
 */

/* * ***************************Includes********************************* */
require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';

class protexiomDevKit extends eqLogic {
    /*     * *************************Attributs****************************** */

    protected $_version = '';
    

    /*     * ***********************Static methods*************************** */
    /*     * ****accessible without needing an instantiation of the class**** */
   


    /*     * **********************Instance methods************************** */

    
    /**
     * Called before setting-up or updating a plugin device
     * Standard Jeedom function
     * @author Fdp1
     */
    public function preUpdate() {
    	return;
    	
    }
    
    /**
     * Called before inserting a plugin device when creating it, before the first configuration
     * Standard Jeedom function
     * @author Fdp1
     *
     */
    public function preInsert() {
    	$this->setCategory('programming', 1);
    }

    /**
     * Called after inserting a plugin device when creating it, before the first configuration
     * Standard Jeedom function
     * @author Fdp1
     *
     */
    public function postInsert() {
		return;
    }

    /**
     * Called after a plugin device configuration setup or update
     * Standard Jeedom function
     * @author Fdp1
     *
     */
    public function postSave() {
    	return;
    }
    
    /**
     * Called before removing a protexiom eqLogic
     * Standard Jeedom function
     * @author Fdp1
     *
     */
    public function preRemove(){
    	return;
    }
    

}

class protexiomDevKitCmd extends cmd {
    /*     * *************************Attributs****************************** */


    /*     * ***********************Static methods*************************** */
    /*     * ****accessible without needing an instantiation of the class**** */


    /*     * **********************Instance methods************************** */

    
    /**
     * Tells Jeedom if it should remove existing commands during update in case they no longer exists in the POSTed form
     * Usefull, for exemple, in case you command list is static and created during postInsert
     * and you don't want to bother putting them in the desktop/plugin.php form.
     * Default to False (if you don't create the function), meaning missing commands ARE removed
     * Standard Jeedom function
     *
     * @return bool
     */
    public function dontRemoveCmd() {
        return true;
    }
    
    /**
     * Execute CMD
     * Standard Jeedom function
     * @param array $_options
     * @author Fdp1
     */
    public function execute($_options = array()) {
    	return;	
    }

    /*     * **********************Getteur Setteur*************************** */
}

?>
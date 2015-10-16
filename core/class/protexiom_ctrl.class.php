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

class protexiom_ctrl extends eqLogic {
    /*     * *************************Attributs****************************** */
	
	protected $_eqParent = '';

    /*     * ***********************Static methods*************************** */
    /*     * ****accessible without needing an instantiation of the class**** */


    /*     * **********************Instance methods************************** */
	
	/**
	 * instantiate $this->_eqParent as the parent Protexiom eqLogic
	 * Only if not done already
	 *
	 * @author Fdp1
	 * @return
	 */
	public function initParent()
	{
		if ( ! is_object($this->_eqParent) ) {
		$this->_eqParent = protexiom::byId(substr($this->getLogicalId(), 0, strpos($this->getLogicalId(),"_")));
			if ( ! is_object($this->_eqParent) ) {
				throw new Exception(__('Objet parent introuvable (id='.substr($this->getLogicalId(), 0, strpos($this->getLogicalId(),"_")).' / '.$this->getLogicalId().')', __FILE__));
			}
		}
		return;
	}//End initParent func
	
	/**
	 * Add a message to the protexiom Jeedom log.
	 * Prepend message with parent eqLogic Name, parent eqLogic ID and PID
	 *
	 * @author Fdp1
	 * @param string $type log type (error, info, event, debug).
	 * @param string $message message to add in the log.
	 */
	public function log($_type = 'INFO', $_message)
	{
		$this->initParent();
		$this->_eqParent->log($_type, $_message);
	}//End log func

	/**
	 * Called before setting-up or updating a plugin device
	 * Standard Jeedom function
	 * @author Fdp1
	 */
	public function preUpdate() {
		if ($this->getIsEnable()) {
			//Trying to activate the eqLogic. Let's check if parent is activated aswell
			$this->initParent();
			if (!$this->_eqParent->getIsEnable()){
				throw new Exception(__('Impossible d\'activer l\'équipement, car l\'équipement parent ('.$this->_eqParent->getName().') est désactivé', __FILE__));
			}
		} 
	}//End preUpdate func
	
	/**
	 * Called after inserting a plugin device when creating it, before the first configuration
	 * Standard Jeedom function
	 * @author Fdp1
	 *
	 */
	public function postInsert() {
		 
		//Action CMD
		
		//Action list depends of th device Type. Let's check
		$_deviceType=substr($this->getLogicalId(), strpos($this->getLogicalId(),"-")+1);
		if($_deviceType=='lights'){

			$ctrlCmd = new protexiom_ctrlCmd();
			$ctrlCmd->setName(__('Lumières On', __FILE__));
			$ctrlCmd->setLogicalId('light_on');
			$ctrlCmd->setEqLogic_id($this->id);
			$ctrlCmd->setConfiguration('somfyCmd', 'LIGHT_ON');
			$ctrlCmd->setType('action');
			$ctrlCmd->setSubType('other');
			$ctrlCmd->setDisplay('icon', '<i class="icon jeedom-lumiere-on"></i>');
			$ctrlCmd->setIsVisible(1);
			$ctrlCmd->setOrder(1);
			$ctrlCmd->save();
				
			$ctrlCmd = new protexiom_ctrlCmd();
			$ctrlCmd->setName(__('Lumières Off', __FILE__));
			$ctrlCmd->setLogicalId('light_off');
			$ctrlCmd->setEqLogic_id($this->id);
			$ctrlCmd->setConfiguration('somfyCmd', 'LIGHT_OFF');
			$ctrlCmd->setType('action');
			$ctrlCmd->setSubType('other');
			$ctrlCmd->setDisplay('icon', '<i class="icon jeedom-lumiere-off"></i>');
			$ctrlCmd->setIsVisible(1);
			$ctrlCmd->setOrder(2);
			$ctrlCmd->save();
			
		}elseif($_deviceType=='shutters'){

			$ctrlCmd = new protexiom_ctrlCmd();
			$ctrlCmd->setName(__('Volets montée', __FILE__));
			$ctrlCmd->setLogicalId('shutter_up');
			$ctrlCmd->setEqLogic_id($this->id);
			$ctrlCmd->setConfiguration('somfyCmd', 'SHUTTER_UP');
			$ctrlCmd->setType('action');
			$ctrlCmd->setSubType('other');
			$ctrlCmd->setDisplay('icon', '<i class="fa fa-chevron-up"></i>');
			$ctrlCmd->setIsVisible(1);
			$ctrlCmd->setOrder(1);
			$ctrlCmd->save();
			
			$ctrlCmd = new protexiom_ctrlCmd();
			$ctrlCmd->setName(__('Volets stop', __FILE__));
			$ctrlCmd->setLogicalId('shutter_stop');
			$ctrlCmd->setEqLogic_id($this->id);
			$ctrlCmd->setConfiguration('somfyCmd', 'SHUTTER_STOP');
			$ctrlCmd->setType('action');
			$ctrlCmd->setSubType('other');
			$ctrlCmd->setDisplay('icon', '<i class="fa fa-stop"></i>');
			$ctrlCmd->setIsVisible(1);
			$ctrlCmd->setOrder(2);
			$ctrlCmd->save();
			
			$ctrlCmd = new protexiom_ctrlCmd();
			$ctrlCmd->setName(__('Volets descente', __FILE__));
			$ctrlCmd->setLogicalId('shutter_down');
			$ctrlCmd->setEqLogic_id($this->id);
			$ctrlCmd->setConfiguration('somfyCmd', 'SHUTTER_DOWN');
			$ctrlCmd->setType('action');
			$ctrlCmd->setSubType('other');
			$ctrlCmd->setDisplay('icon', '<i class="fa fa-chevron-down"></i>');
			$ctrlCmd->setIsVisible(1);
			$ctrlCmd->setOrder(3);
			$ctrlCmd->save();
			
		}else{
			$this->log('error', $_deviceType.' is an unknow type for device '.$this->getName());
		}

	}//End postInsert func
	
	/**
	 * Return link to the eqLogicConfiguration page.
	 * Optional for standard eqLogic, but needed to subEqlogic to get to the parent eqLogic config page
	 * Standard Jeedom function
	 *
	 * @author Fdp1
	 */
	public function getLinkToConfiguration() {
		return 'index.php?v=d&p=protexiom&m=protexiom&id=' . $this->getId();
	}//End getLinkToConfiguration func

    /*     * **********************Getteur Setteur*************************** */
}

class protexiom_ctrlCmd extends cmd 
{
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
		$subdevice=$this->getEqLogic();
		$protexiom = protexiom::byId(substr($subdevice->getLogicalId(), 0, strpos($subdevice->getLogicalId(),"_")));
		
		$myError="";
		$subdevice->log('debug', "Running ".$this->name." CMD");
		$infoValue="";
	
		$protexiom->initSpBrowser();
		$subdevice->log('debug', "Sending web request for ".$this->name." CMD");
		if($myError=$protexiom->_spBrowser->doAction($this->getConfiguration('somfyCmd'))){
			//an error occured. May be the somfy session timeout bug
			$subdevice->log('debug', "The folowing error happened while running ".$this->name." CMD: ".$myError.". Let's workaroundSomfySessionTimeoutBug");
			if(!$protexiom->workaroundSomfySessionTimeoutBug()){
				$myError=$protexiom->_spBrowser->doAction($this->getConfiguration('somfyCmd'));
			}
		}
		if($myError){
			$subdevice->log('error', "An error occured while running $this->name action: $myError");
			throw new Exception(__("An error occured while running $this->name action: $myError",__FILE__));
		}else{
			//Command successfull
			$protexiom->setStatusFromSpBrowser();
			$subdevice->log('debug', $this->name." CMD run OK");
			return;
		}
	}


}
?>

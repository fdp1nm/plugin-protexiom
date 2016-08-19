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

require_once dirname(__FILE__) . '/../../../core/php/core.inc.php';

function protexiom_install() {
	//Loading default configuration value
	$_config = config::getDefaultConfiguration('protexiom')['protexiom'];
	
	$cron = cron::byClassAndFunction('protexiom', 'pull');
	if (!is_object($cron)) {
		$cron=new cron();
		$cron->setClass('protexiom');
		$cron->setFunction('pull');
		$cron->setEnable(1);
		$cron->setDeamon(1);
		$cron->setDeamonSleepTime($_config['pollInt']);
		$cron->setSchedule('* * * * *');
		$cron->setTimeout($_config['daemonTimeout']);//60 is the default. It's not a good odea to restart every daemons at once.
		$cron->save();
		log::add('protexiom', 'info', '[*-*] '.getmypid().' Protexiom daemon created', 'Protexiom');
	}
}

function protexiom_update() {
	log::add('protexiom', 'info', '[*-*] '.getmypid().' Running protexiom post-update script', 'Protexiom');
	//Loading default configuration value
	$_config = config::getDefaultConfiguration('protexiom')['protexiom'];
	
	//Variable to be used in 1.2.0 upgrade
	$pollint_1_2_0 = $_config['pollInt'];
	
	
	//eqLogic scope upgrade
	foreach (eqLogic::byType('protexiom') as $eqLogic) {
		/*
		 * Upgrade to v0.0.9
		 */
		//Let's convert info CMD to eventOnly
		if(filter_var($eqLogic->getConfiguration('PollInt'), FILTER_VALIDATE_INT, array('options' => array('min_range' => 1)))){
			// If polling is on, we can set every info CMD to setEventOnly
			// this way, cmd cache TTL is not taken into account, and polling is the only way to update an info cmd
			foreach ($eqLogic->getCmd('info') as $cmd) {
				$cmd->setEventOnly(1);
				$cmd->save();
			}
		}
		
		/*
		 * Upgrade to v0.0.10
		*/
		
		$cmd=$eqLogic->getCmd('info', 'alarm');
		if($cmd->getSubType()=='binary'){
			$cmd->setSubType('string');
			message::add('protexiom', 'Somfy alarme: La commande d\'info "'.$cmd->getName().'" a été modifiée. Son type change, ainsi que sa valeur. Si cette commande est utilisée dans des scenarios, vous devez les modifier.', '', 'Protexiom');
			$cmd->save();
		}
		
		$templateList = [
		'abc_off' => 'protexiomOff',
		'zonea_on' => 'protexiomOn',
		'zoneb_on' => 'protexiomOn',
		'zonec_on' => 'protexiomOn',
		'zoneabc_on' => 'protexiomOn',
		'reset_alarm_err' => 'protexiomClr',
		'reset_link_err' => 'protexiomClr',
		'reset_battery_err' => 'protexiomClr',
		'zone_a' => 'protexiomZone',
		'zone_b' => 'protexiomZone',
		'zone_c' => 'protexiomZone',
		'link' => 'protexiomLink',
		'door' => 'protexiomDoor',
		'gsm_operator' => 'protexiomDefault',
		'gsm_link' => 'protexiomDefault',
		'alarm' => 'protexiomAlarm',
		'tampered' => 'protexiomTampered',
		'gsm_signal' => 'protexiomGsmSignal',
		'needs_reboot' => 'protexiomNeedsReboot',
		'camera' => 'protexiomCamera'
				];
		
		foreach ($templateList as $key => $value){
			$cmd=$eqLogic->getCmd(null, $key);
            if (is_object($cmd)) {
			    if(!$cmd->getTemplate('dashboard', '')){
				    log::add('protexiom', 'info', '[*-*] '.getmypid().' Setting template for '.$cmd->getName(), 'Protexiom');
				    $cmd->setTemplate('dashboard', $value);
				    $cmd->save();
			    }
			    if(!$cmd->getTemplate('mobile', '')){
				    log::add('protexiom', 'info', '[*-*] '.getmypid().' Setting template for '.$cmd->getName(), 'Protexiom');
				    $cmd->setTemplate('mobile', $value);
				    $cmd->save();
			    }
            }
		}
        
		$mobileTagList = [
		'zoneabc_on' => 'On  A+B+C',
		'zonea_on' => 'On A',
		'zoneb_on' => 'On B',
		'zonec_on' => 'On C',
		'abc_off' => 'Off A+B+C',
		'reset_alarm_err' => 'CLR alarm',
		'reset_battery_err' => 'CLR bat',
		'reset_link_err' => 'CLR link',
		'zone_a' => 'Zone A',
		'zone_b' => 'Zone B',
		'zone_c' => 'Zone C',
		'battery' => 'Piles',
		'link' => 'Liaison',
		'door' => 'Portes',
		'alarm' => 'Alarme',
		'tampered' => 'Sabotage',
		'gsm_link' => 'Liaison GSM',
		'gsm_signal' => 'Récéption GSM',
		'gsm_operator' => 'Opérateur GSM',
		'needs_reboot' => 'Reboot requis',
		'camera' => 'Camera'
				];
		foreach ($eqLogic->getCmd() as $cmd) {
		    if(!$cmd->getConfiguration('mobileLabel')){
			    $cmd->setConfiguration('mobileLabel', $mobileTagList[$cmd->getLogicalId()]);
			    $cmd->save();
		    }
		}
		/*
		 * Upgrade to v0.0.12
		*/
		
		//Let's remove battery cmd, as this is now handled with Jeedom standard
		$cmd=$eqLogic->getCmd('info', 'battery');
		if (is_object($cmd)) {
			message::add('protexiom', 'Somfy alarme: La commande d\'info "'.$cmd->getName().'" a �t� supprim�e. Le niveau de batterie est maintenant g�r� au standard Jeedom (getConfiguration(batteryStatus)).', '', 'Protexiom');
			$cmd->remove();
		}
		/*
		 * Upgrade to v0.0.16
		*/
		
		//Let's add back battery cmd which was removed in v0.0.12, but with a changed logicalId
		$cmd=$eqLogic->getCmd('info', 'battery_status');
		if (!is_object($cmd)) {
			$cmd = new protexiomCmd();
			$cmd->setName(__('Piles', __FILE__));
			$cmd->setLogicalId('battery_status');
			$cmd->setEqLogic_id($eqLogic->getId());
			$cmd->setConfiguration('somfyCmd', 'BATTERY');
			$cmd->setConfiguration('mobileLabel', 'Piles');
			$cmd->setUnite('');
			$cmd->setType('info');
			$cmd->setSubType('binary');
			$cmd->setIsVisible(0);
			$cmd->setTemplate('dashboard', 'protexiomBattery');
			$cmd->setTemplate('mobile', 'protexiomBattery');
			$cmd->save();
		}
		/*
		 * Upgrade to v1.0.0
		*/
		foreach ($eqLogic->getCmd('action') as $cmd) {
			switch($cmd->getLogicalId())
			{
				case 'abc_off';
					$cmd->setLogicalId('zoneabc_off');
					if($cmd->getDisplay('icon')==''){
						$cmd->setDisplay('icon', '<i class="fa fa-unlock"></i>');
					}
					if($cmd->getTemplate('dashboard', '')=='protexiomOff'){
						$cmd->setTemplate('dashboard', 'protexiomDefault');
					}
					if($cmd->getTemplate('mobile', '')=='protexiomOff'){
						$cmd->setTemplate('mobile', 'protexiomDefault');
					}
					$cmd->save();
				break;
				case 'zoneabc_on';
				case 'zonea_on';
				case 'zoneb_on';
				case 'zonec_on';
					$i=0;
					if($cmd->getDisplay('icon')==''){
						$cmd->setDisplay('icon', '<i class="fa fa-lock"></i>');
						$i++;
					}
					if($cmd->getTemplate('dashboard', '')=='protexiomOn'){
						$cmd->setTemplate('dashboard', 'protexiomDefault');
						$i++;
					}
					if($cmd->getTemplate('mobile', '')=='protexiomOn'){
						$cmd->setTemplate('mobile', 'protexiomDefault');
						$i++;
					}
					if($i){
						$cmd->save();
					}
				break;
				case 'reset_alarm_err';
				case 'reset_battery_err';
				case 'reset_link_err';
					$i=0;
					if($cmd->getDisplay('icon')==''){
						$cmd->setDisplay('icon', '<i class="fa fa-trash-o"></i>');
						$i++;
					}
					if($cmd->getTemplate('dashboard', '')=='protexiomClr'){
						$cmd->setTemplate('dashboard', 'protexiomDefault');
						$i++;
					}
					if($cmd->getTemplate('mobile', '')=='protexiomClr'){
						$cmd->setTemplate('mobile', 'protexiomDefault');
						$i++;
					}
					if($i){
						$cmd->save();
					}
				break;
			}
		}
        //We reach enough stability. the needs_reboot is nolonger needed
        $cmd=$eqLogic->getCmd('info', 'needs_reboot');
	    if (is_object($cmd)) {
            log::add('protexiom', 'info', '[*-*] '.getmypid().' removing needs_reboot cmd for '.$cmd->getName(), 'Protexiom');
            $cmd->remove();
            message::add('protexiom', 'Somfy alarme '.$eqLogic->getName().': La commande d\'info "'.$cmd->getName().'" a été supprimée. La stabilit� du plugin est maintenant optimale, et cette commande devenue inutile avait �t� inhib�e depuis plusieurs mois. Si cette commande est utilisée dans des scenarios, vous devez les supprimer.', '', 'Protexiom');
        }
        /*
         * Upgrade to v1.1.0 & v1.1.1
        */
        //Let's detect subEqlogic only if eqLogic is enabled.
        //If not, the detection will take place at enable time
        if($eqLogic->getIsEnable()){
        	$eqLogic->createCtrlSubDevices();
        	// Let's force elements creation / update
        	$eqLogic->pullStatus(true);
        }
        
        /*
         * Upgrade to v1.2.0
        */
        //If we can find an enabled protexiom with polling activated, let's get the setup polling interval for the global daemon
        if($eqLogic->getIsEnable()){
        	if(filter_var($eqLogic->getConfiguration('PollInt'), FILTER_VALIDATE_INT, array('options' => array('min_range' => 5)))){
        		$pollint_1_2_0=$eqLogic->getConfiguration('PollInt');
        	}
        }
        //We will use this when the foreach loop is over
        //Let's remove perEqlogic daemon, as they are replaced by a global one from now on
        $cron = cron::byClassAndFunction('protexiom', 'pull', array('protexiom_id' => intval($eqLogic->getId())));
        if (is_object($cron)) {
        	$cron->remove();
        }
        
        /*
         * Upgrade to v1.2.8
        */
        foreach ($eqLogic->getCmd() as $cmd) {
        	if($cmd->getDisplay('generic_type')==''){
        		switch($cmd->getLogicalId())
        		{
        			case 'zoneabc_on';
        				$cmd->setDisplay('generic_type','ALARM_ARMED');
        				$cmd->save();
        			break;
        			case 'zonea_on';
        			case 'zoneb_on';
        			case 'zonec_on';
        				$cmd->setDisplay('generic_type','ALARM_SET_MODE');
        				$cmd->save();
        			break;
        			case 'zoneabc_off';
        				$cmd->setDisplay('generic_type','ALARM_RELEASED');
        				$cmd->save();
        			break;
        			case 'reset_alarm_err';
        			case 'reset_battery_err';
        			case 'reset_link_err';
        			break;
        			case 'zonea';
        			case 'zoneb';
        			case 'zonec';
        				$cmd->setDisplay('generic_type','ALARM_ENABLE_STATE');
        				$cmd->save();
        			break;
        			case 'battery_status';
        				$cmd->setDisplay('generic_type','BATTERY');
        				$cmd->save();
        			break;
        			case 'link';
        				$cmd->setDisplay('generic_type','DONT');
        				$cmd->save();
        			break;
        			case 'door';
        				$cmd->setDisplay('generic_type','OPENING');
        				$cmd->save();
        			break;
        			case 'alarm';
        			break;
        			case 'tampered';
        				$cmd->setDisplay('generic_type','SABOTAGE');
        				$cmd->save();
        			break;
        			case 'gsm_link';
        				$cmd->setDisplay('generic_type','GENERIC_INFO');
        				$cmd->save();
        			break;
        			case 'gsm_signal';
        				$cmd->setDisplay('generic_type','DONT');
        				$cmd->save();
        			break;
        			case 'gsm_operator';
        				$cmd->setDisplay('generic_type','GENERIC_INFO');
        				$cmd->save();
        			break;
        			case 'camera';
        				$cmd->setDisplay('generic_type','DONT');
        				$cmd->save();
        			break;
        		}
        	}
        }
        
		/*
		 * End of version spécific upgrade actions. Let's run standard actions
		 */
		//Nothing to do in the eqLogic scope
		
	}//End foreach eqLogic
	
	//plugin scope upgrade
	
	/*
	 * Upgrade to v1.2.0
	*/
	//Let's create a global cron task if does not exists
	$cron = cron::byClassAndFunction('protexiom', 'pull');
	if (!is_object($cron)) {
		$cron=new cron();
		$cron->setClass('protexiom');
		$cron->setFunction('pull');
		$cron->setEnable(1);
		$cron->setDeamon(1);
		$cron->setDeamonSleepTime(intval($pollint_1_2_0));
		$cron->setSchedule('* * * * *');
		$cron->setTimeout($_config['daemonTimeout']);//60 is the default. It's not a good odea to restart every daemons at once.
		$cron->save();
		log::add('protexiom', 'info', '[*-*] '.getmypid().' Protexiom daemon created', 'Protexiom');
		config::save('pollInt', $pollint_1_2_0, 'protexiom');
		log::add('protexiom', 'debug', '[*-*] '.getmypid().' Polling interval set to '.$pollint_1_2_0, 'Protexiom');
	}
	/*
	 * Upgrade to v1.2.8
	*/
	//Let's now take care of subEqLogics
	foreach (eqLogic::byType('protexiom_ctrl') as $eqLogic) {
		foreach ($eqLogic->getCmd() as $cmd) {
			if($cmd->getDisplay('generic_type')==''){
				switch($cmd->getLogicalId())
				{
					case 'light_on';
					$cmd->setDisplay('generic_type','LIGHT_ON');
					$cmd->save();
					break;
					case 'light_off';
					$cmd->setDisplay('generic_type','LIGHT_OFF');
					$cmd->save();
					break;
					case 'shutter_up';
					$cmd->setDisplay('generic_type','FLAP_UP');
					$cmd->save();
					break;
					case 'shutter_stop';
					$cmd->setDisplay('generic_type','FLAP_STOP');
					$cmd->save();
					break;
					case 'shutter_down';
					$cmd->setDisplay('generic_type','FLAP_DOWN');
					$cmd->save();
					break;
				}
			}
		}
	}
	foreach (eqLogic::byType('protexiom_elmt') as $eqLogic) {
		foreach ($eqLogic->getCmd() as $cmd) {
			if($cmd->getDisplay('generic_type')==''){
				switch($cmd->getLogicalId())
				{
					case "battery":
						$cmd->setDisplay('generic_type','BATTERY');
						$cmd->save();
						break;
					case "tampered":
						$cmd->setDisplay('generic_type','SABOTAGE');
						$cmd->setDisplay('invertBinary','1');
						$cmd->save();
						message::add('protexiom', 'Plugin Somfy alarme: Pour des raisons de compatibilité avec l\'appli mobile, l\'affichage des commandes tampered des détécteurs (sabotage) est maintenant inversé. Si vous utilisez des widgets personnalisés, vous devez les modifier en conséquence. Les valeurs récupérées dans les scénarios restent inchangées.', '', 'Protexiom');
						break;
					case "alarm":
						if($eqLogic->getConfiguration('item_type')=='typedm'){
							$cmd->setDisplay('generic_type','PRESENCE');
						/*}else{
							$cmd->setDisplay('generic_type','ALARM_STATE');*/
						}
						$cmd->save();
						break;
					case "door":
						$cmd->setDisplay('generic_type','OPENING');
						$cmd->save();
						break;
				}
			}
		}
	}
	//Adding the protexiom subClasses to configKey, to allow core or 3rd party plugin to list them
	if(config::byKey('subClass', 'protexiom', '')==''){
		config::save('subClass', config::getDefaultConfiguration('protexiom')['protexiom']['subClass'], 'protexiom');
	}
	
	/*
	 * End of version spécific upgrade actions. Let's run standard actions
	*/
	//As the protexiom::pull task is schedulded as a daemon, we should restart it so that it uses functions from the new plugin version.
	protexiom::deamon_start();
	
	
	log::add('protexiom', 'info', '[*-*] '.getmypid().' End of protexiom post-update script', 'Protexiom');
}


function protexiom_remove(){
	$cron = cron::byClassAndFunction('protexiom', 'pull');
	if (is_object($cron)) {
		$cron->remove();
	}
}//End function protexiom_remove()

?>
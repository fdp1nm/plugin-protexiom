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

/*function protexiom_install() {
}*/

function protexiom_update() {
	log::add('protexiom', 'info', '[*-*] '.getmypid().' Running protexiom post-update script', 'Protexiom');
	
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
		'zone_a' => 'protexiomZone',
		'zone_b' => 'protexiomZone',
		'zone_c' => 'protexiomZone',
		'battery' => 'protexiomBattery',
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
			$cmd=$eqLogic->getCmd('info', $key);
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
		 * Upgrade to v0.0.11
		*/
		
        //Let's remove battery cmd, as this is now handled with Jeedom standard
		$cmd=$eqLogic->getCmd('info', 'battery');
        if (is_object($cmd)) {
            message::add('protexiom', 'Somfy alarme: La commande d\'info "'.$cmd->getName().'" a été supprimée. Le niveau de batterie est maintenant géré au standard Jeedom (getConfiguration(batteryStatus)).', '', 'Protexiom');
			$cmd->remove();
        }

		/*
		 * End of version spécific upgrade actions. Let's run standard actions
		 */
		//As the protexiom::pull task is schedulded as a daemon, we should restart it so that it uses functions from the new plugin version.
		//Let's stop it, it will then automatically restart
		$cron = cron::byClassAndFunction('protexiom', 'pull', array('protexiom_id' => intval($eqLogic->getId())));
		if (is_object($cron)) {
			log::add('protexiom', 'info', '['.$eqLogic->getName().'-'.$eqLogic->getId().'] '.getmypid().' Stopping pull daemon', $eqLogic->getName());
			// TODO The cron->stop allows a restrt of the task with an up to date script.
			// However, stopping the task in the middle of the executioncan lead to authcookie lost
			// Need to find a workaround
			$cron->stop();
		}
	}
	
	log::add('protexiom', 'info', '[*-*] '.getmypid().' End of protexiom post-update script', 'Protexiom');
}


function protexiom_remove(){
	foreach (eqLogic::byType('protexiom') as $eqLogic) {
		$eqLogic->unSchedulePull();
		$eqLogic->unScheduleIsRebooted();
	}
}//End function protexiom_remove()

?>
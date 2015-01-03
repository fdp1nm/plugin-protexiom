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
	log::add('protexiom', 'info', 'Running protexiom post-update script', 'Protexiom');
	
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
		 * End of version spécific upgrade actions. Let's run standard actions
		 */
		//As the protexiom::pull task is schedulded as a daemon, we should restart it so that it uses functions from the new plugin version.
		//Let's stop it, it will then automatically restart
		$cron = cron::byClassAndFunction('protexiom', 'pull', array('protexiom_id' => intval($eqLogic->getId())));
		if (is_object($cron)) {
			log::add('protexiom', 'info', '['.$eqLogic->getName().'-'.$eqLogic->getId().'] '.'Stopping pull daemon', $eqLogic->getName());
			$cron->stop();
		}
	}
}


function protexiom_remove(){
	foreach (eqLogic::byType('protexiom') as $eqLogic) {
		$eqLogic->unSchedulePull();
		$eqLogic->unScheduleIsRebooted();
	}
}//End function protexiom_remove()

?>
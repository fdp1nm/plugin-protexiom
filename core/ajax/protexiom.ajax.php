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

try {
    require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';
    include_file('core', 'authentification', 'php');

    if (!isConnect('admin')) {
        throw new Exception(__('401 - Accès non autorisé', __FILE__));
    }

    if (init('action') == 'postSave') {
    	//Called after a plugin configuration save
    	//Restart daemon with new polling interval
    	$cron = cron::byClassAndFunction('protexiom', 'pull');
    	if (!is_object($cron)) {
    		$cron=new cron();
    		$cron->setClass('protexiom');
    		$cron->setFunction('pull');
    		$cron->setEnable(1);
    		$cron->setDeamon(1);
    		$cron->setDeamonSleepTime(intval(config::byKey('pollInt', 'protexiom')));
    		$cron->setSchedule('* * * * *');
    		$cron->setTimeout(config::byKey('daemonTimeout', 'protexiom'));
    		$cron->save();
    		log::add('protexiom', 'debug', '[*-*] '.getmypid().' Daemon re-created with upto date polling interval', 'Protexiom');
    	}else{
    		$cron->setDeamonSleepTime(intval(config::byKey('pollInt', 'protexiom')));
    		$cron->setTimeout(config::byKey('daemonTimeout', 'protexiom'));
    		$cron->save();
    		log::add('protexiom', 'debug', '[*-*] '.getmypid().' Polling interval updated', 'Protexiom');
    	}
    	protexiom::deamon_start();
        ajax::success();
    }

    throw new Exception(__('Aucune methode correspondante à : ', __FILE__) . init('action'));
    /*     * *********Catch exeption*************** */
} catch (Exception $e) {
    ajax::error(displayExeption($e), $e->getCode());
}
?>

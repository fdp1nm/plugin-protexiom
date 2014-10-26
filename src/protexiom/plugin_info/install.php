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

require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';

/*function protexiom_install() {
}*/

/*function protexiom_update() {
    $cron = cron::byClassAndFunction('protexiom', 'pull');
    if (!is_object($cron)) {
        $cron = new cron();
        $cron->setClass('protexiom');
        $cron->setFunction('pull');
        $cron->setEnable(1);
        //$cron->setDeamon(1);
        $cron->setSchedule('* * * * *');
        $cron->save();
    }
    $cron->stop();
}*/


function protexiom_remove(){
	foreach (eqLogic::byType('protexiom') as $eqLogic) {
		$eqLogic->unSchedule();
	}
}//End function protexiom_remove()

?>
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
require_once dirname(__FILE__) . '/../../3rdparty/phpProtexiom/phpProtexiom.class.php';

class protexiom extends eqLogic {
    /*     * *************************Attributs****************************** */

    private $_UpdateDate = '';
    private $_HwVersion = '';
    private $_SomfyAuthCookie = '';
    private $_SomfyHost = '';
    private $_SomfyPort = '';
    private $_WebProxyHost = '';
    private $_WebProxyPort = '';

    /*     * ***********************Methode static*************************** */
    /**
     * Check wether the parameter is a valid port number.
     *
     * @author Fdp1
     * @param string $port port number.
     * @return bool True if the string is valid, false otherwise
     * @usage isValid = isValidPort("80")
     */
    private static function isValidPort($port = '')
    {
    	$error=false;
    	if($port){//A port number was specified. Is it a int
    		if(ctype_digit($port)){
    			if(intval($port,10)<1 or intval($port,10)>65534){
    				$error="Invalid port range";
    			}
    
    		}else{//It's not a int. Then, is it a service name?
    			$port = getservbyname($port, 'tcp');
    			if(!$port){//$port was not a valid SvcName, so the port is definetly not valid
    				$error="Invalid service name";
    			}//Else: $port was a valid service name, so obviously, it's a valid port.
    		}
    	}else{//Not port number specified.
    		$error="No port specified";
    	}
    
    	if($error){
    		return false;
    	}else{
    		return true;
    	}
    }//End isValidPort func
    
    /**
     * Check wether the hostname is valid. IPV6 ready.
     *
     * @author Fdp1
     * @param string $host IP address or hostname. Should NOT contain a port number
     * @return bool True if the string is valid, false otherwise
     * @usage isValid = isValidHost("192.168.1.111")
     */
    private static function isValidHost($host = '')
    {
    	$error=false;
    
    	if(!filter_var($host, FILTER_VALIDATE_IP)){//$host is neither an ipv4, nore an ipv6. Let's see if it's a hostname
    		if (!(preg_match("/^([a-z\d](-*[a-z\d])*)(\.([a-z\d](-*[a-z\d])*))*$/i", $host) //valid chars check
    				&& preg_match("/^.{1,253}$/", $host) //overall length check
    				&& preg_match("/^[^\.]{1,63}(\.[^\.]{1,63})*$/", $host)   )) //length of each label
    		{
    			$error="Invalid domain";
    		}//Not a valid domain name
    	}//Else: $host is an IP
    
    	if($error){
    		return false;
    	}else{
    		return true;
    	}
    }//End isValidHost func
    
    /**
     * Check wether the hostname[:port] is valid. IPV6 ready.
     *
     * @author Fdp1
     * @param string $hostPort IP address or hostname. May contain a port number, separated by a colon.
     * @return bool True if the string is valid, false otherwise
     * @usage isValid = isValidHostPort("192.168.1.111:80")
     */
    private function isValidHostPort($hostPort = '')
    {
    	$error=false;
    	$host = strtok($hostPort, ":");
    	$port = strtok(":");
    	if(strtok(":")) {
    		$error="More than one : was present in the string";
    	}else{
    		//First, let's check the port number
    		if($port){//A port number was specified. Let's check it
    			if(!$this->isValidPort($port)){
    				$error="Invalid port";
    			}
    		}//else, not port number specified. So obviously, port number is OK, as we will use default protocol port to connect...
    
    		//Now the port number is checked (valid or not), let's take care of the hostname
    		if(!$this->isValidHost($host)){
    			$error="Invalid host";
    		}
    	}
    		
    	if($error){
    		//echo($error);
    		return false;
    	}else{
    		return true;
    	}
    }//End isValidHostPort func
    
    /**
     * Return the somfy authCard at once from the configuration authLines
     *
     * @author Fdp1
     * @return array authcard
     * @usage authCard = getAuthCar()
     */
    function getAuthCard()
    {
    	$authCard=array();
    	$authCode='';
    	
    	$lineNum = 1;
    	do {
    		list($authCard["A$lineNum"], $authCard["B$lineNum"], $authCard["C$lineNum"], $authCard["D$lineNum"], $authCard["E$lineNum"], $authCard["F$lineNum"])=preg_split("/[^0-9]/", $this->getConfiguration("AuthCardL$lineNum"));
    		$lineNum++;
    	} while ($lineNum < 6);
    	return $authCard;
    }//End getAuthCard func
    
    public static function pull($_options) {
        /*$protexiom = protexiom::byId($_options['protexiom_id']);
        if (is_object($weather)) {
            $weather_xml = $weather->getWeatherFromYahooXml();
            $sunrise = $weather_xml['astronomy']['sunrise'];
            $sunset = $weather_xml['astronomy']['sunset'];
            if ((date('Hi') + 100) >= $sunrise && (date('Hi') + 100 ) < $sunset) {
                $search = 'sunrise';
            } else {
                $search = 'sunset';
            }
            foreach ($weather->getCmd() as $cmd) {
                if ($cmd->getConfiguration('data') == $search) {
                    $cmd->event(date('Hi'));
                }
            }
            $weather->reschedule();
        } else {
            $cron = cron::byClassAndFunction('weather', 'pull', $_options);
            if (is_object($cron)) {
                $cron->remove();
            }
            throw new Exception('Weather ID non trouvÃ© : ' . $_options['weather_id'] . '. Tache supprimÃ©');
        }*/
    }
    
    /*
     * Refresh every info Cmd at once
     * @return 0 in case of sucess, 1 otherwise
     */
    public function refreshStatus() {
    	
    	$myError="";
    	
    	$mySP=new phpProtexiom($this->getConfiguration('SomfyHostPort'), $this->getConfiguration('SSLEnabled'));
    	$mySP->userPwd=$this->getConfiguration('UserPwd');
    	$mySP->authCard=$this->getAuthCard();
    	$mySP->setHwVersion($this->getConfiguration('HwVersion'));
    	if($myError=$mySP->updateStatus()){
    		//An error occured. Let's Log the error
    		log::add('protexiom', 'error', "An error occured during $this->name status update: ".$myError, $this->name);
    		return 1;
    	}else{
    		//Status pulled. Let's now refreh CMD
    		log::add('protexiom', 'info', 'Status refreshed', $this->name);
    		$status=$mySP->getStatus();
    		foreach ($this->getCmd() as $cmd) {
    			if ($cmd->getType() == "info") {
    				if($cmd->getValue() != $status[$cmd->getConfiguration('somfyCmd')])
    					$cmd->event($status[$cmd->getConfiguration('somfyCmd')]);
    			}
    		}
    		return 0;
    	}
    }

    /*public static function cronHourly() {
        foreach (self::byType('weather') as $weather) {
            if ($weather->getIsEnable() == 1) {
                $cron = cron::byClassAndFunction('weather', 'pull', array('weather_id' => intval($weather->getId())));
                if (!is_object($cron)) {
                    $weather->reschedule();
                } else {
                    $c = new Cron\CronExpression($cron->getSchedule(), new Cron\FieldFactory);
                    try {
                        $c->getNextRunDate();
                    } catch (Exception $ex) {
                        $weather->reschedule();
                    }
                }
            }
        }
    }*/

    /*     * *********************Methode d'instance************************* */

    /*
     * Called before setting-up or updating a plugin device
     * Standard Jeedom function
     */
    public function preUpdate() {
    	//Let's check the config parameters. Beginning with hostPort
        if (!$this->isValidHostPort($this->getConfiguration('SomfyHostPort'))) {
            throw new Exception(__('Adresse IP ou nom d\'hôte invalide', __FILE__));
        }
        // Now, cheking userPwd
        if(!preg_match ( "/^[0-9]{4}$/" , $this->getConfiguration('UserPwd') )){
        	throw new Exception(__('Le format du mot de passe utilisateur est invalide.', __FILE__));
        }
        // Now, cheking authCard:
        //	Line 1
        if(!preg_match ( "/^([0-9]{4}[^0-9]){5}[0-9]{4}$/" , $this->getConfiguration('AuthCardL1') )){
        	throw new Exception(__('Le format de la carte d\'authentification (ligne 1) est invalide.', __FILE__));
        }
        //	Line 2
        if(!preg_match ( "/^([0-9]{4}[^0-9]){5}[0-9]{4}$/" , $this->getConfiguration('AuthCardL2') )){
        	throw new Exception(__('Le format de la carte d\'authentification (ligne 2) est invalide.', __FILE__));
        }
        //	Line 3
        if(!preg_match ( "/^([0-9]{4}[^0-9]){5}[0-9]{4}$/" , $this->getConfiguration('AuthCardL3') )){
        	throw new Exception(__('Le format de la carte d\'authentification (ligne 3) est invalide.', __FILE__));
        }
        //	Line 4
        if(!preg_match ( "/^([0-9]{4}[^0-9]){5}[0-9]{4}$/" , $this->getConfiguration('AuthCardL4') )){
        	throw new Exception(__('Le format de la carte d\'authentification (ligne 4) est invalide.', __FILE__));
        }
        //	Line 5
        if(!preg_match ( "/^([0-9]{4}[^0-9]){5}[0-9]{4}$/" , $this->getConfiguration('AuthCardL5') )){
        	throw new Exception(__('Le format de la carte d\'authentification (ligne 5) est invalide.', __FILE__));
        }
        //Finally, if a proxy is specified, let's check it's valid
        if($this->getConfiguration('WebProxyHostPort')){
        	if (!$this->isValidHostPort($this->getConfiguration('WebProxyHostPort'))) {
        		throw new Exception(__('Proxy web invalide', __FILE__));
        	}
        }
        
        //OK. Every parameters is checked and is OK
        
        //SSL not supported yet.
        if($this->getConfiguration('SSLEnabled')){
        	throw new Exception(__('SSL pas encore supporté. Veuillez désactiver l\'option', __FILE__));
        }
    	
    }
    
    /**
     * Called before inserting a plugin device when creating it, before the first configuration
     * Standard Jeedom function
     *
     */
    public function preInsert() {
    	$this->setCategory('security', 1);
    }

    /**
     * Called after inserting a plugin device when creating it, before the first configuration
     * Standard Jeedom function
     *
     */
    public function postInsert() {
    	
    	//Action CMD
    	
    	$protexiomCmd = new protexiomCmd();
        $protexiomCmd->setName(__('Marche A+B+C', __FILE__));
        $protexiomCmd->setEqLogic_id($this->id);
        $protexiomCmd->setConfiguration('somfyCmd', 'ZONEABC_ON');
        $protexiomCmd->setType('action');
        $protexiomCmd->setSubType('other');
        $protexiomCmd->save();

        $protexiomCmd = new protexiomCmd();
        $protexiomCmd->setName(__('Marche A', __FILE__));
        $protexiomCmd->setEqLogic_id($this->id);
        $protexiomCmd->setConfiguration('somfyCmd', 'ZONEA_ON');
        $protexiomCmd->setType('action');
        $protexiomCmd->setSubType('other');
        $protexiomCmd->save();

        $protexiomCmd = new protexiomCmd();
        $protexiomCmd->setName(__('Marche B', __FILE__));
        $protexiomCmd->setEqLogic_id($this->id);
        $protexiomCmd->setConfiguration('somfyCmd', 'ZONEB_ON');
        $protexiomCmd->setType('action');
        $protexiomCmd->setSubType('other');
        $protexiomCmd->save();

        $protexiomCmd = new protexiomCmd();
        $protexiomCmd->setName(__('Marche C', __FILE__));
        $protexiomCmd->setEqLogic_id($this->id);
        $protexiomCmd->setConfiguration('somfyCmd', 'ZONEC_ON');
        $protexiomCmd->setType('action');
        $protexiomCmd->setSubType('other');
        $protexiomCmd->save();

        $protexiomCmd = new protexiomCmd();
        $protexiomCmd->setName(__('Arret A+B+C', __FILE__));
        $protexiomCmd->setEqLogic_id($this->id);
        $protexiomCmd->setConfiguration('somfyCmd', 'ALARME_OFF');
        $protexiomCmd->setType('action');
        $protexiomCmd->setSubType('other');
        $protexiomCmd->save();
        
        $protexiomCmd = new protexiomCmd();
        $protexiomCmd->setName(__('Marche lumières', __FILE__));
        $protexiomCmd->setEqLogic_id($this->id);
        $protexiomCmd->setConfiguration('somfyCmd', 'LIGHT_ON');
        $protexiomCmd->setType('action');
        $protexiomCmd->setSubType('other');
        $protexiomCmd->setIsVisible(0);
        $protexiomCmd->save();
        
        $protexiomCmd = new protexiomCmd();
        $protexiomCmd->setName(__('Arrêt lumières', __FILE__));
        $protexiomCmd->setEqLogic_id($this->id);
        $protexiomCmd->setConfiguration('somfyCmd', 'LIGHT_OFF');
        $protexiomCmd->setType('action');
        $protexiomCmd->setSubType('other');
        $protexiomCmd->setIsVisible(0);
        $protexiomCmd->save();
        
        $protexiomCmd = new protexiomCmd();
        $protexiomCmd->setName(__('Ouverture volets', __FILE__));
        $protexiomCmd->setEqLogic_id($this->id);
        $protexiomCmd->setConfiguration('somfyCmd', 'SHUTTER_UP');
        $protexiomCmd->setType('action');
        $protexiomCmd->setSubType('other');
        $protexiomCmd->setIsVisible(0);
        $protexiomCmd->save();
        
        $protexiomCmd = new protexiomCmd();
        $protexiomCmd->setName(__('Fermeture volets', __FILE__));
        $protexiomCmd->setEqLogic_id($this->id);
        $protexiomCmd->setConfiguration('somfyCmd', 'SHUTTER_DOWN');
        $protexiomCmd->setType('action');
        $protexiomCmd->setSubType('other');
        $protexiomCmd->setIsVisible(0);
        $protexiomCmd->save();
        
        $protexiomCmd = new protexiomCmd();
        $protexiomCmd->setName(__('Arrêt volets', __FILE__));
        $protexiomCmd->setEqLogic_id($this->id);
        $protexiomCmd->setConfiguration('somfyCmd', 'SHUTTER_STOP');
        $protexiomCmd->setType('action');
        $protexiomCmd->setSubType('other');
        $protexiomCmd->setIsVisible(0);
        $protexiomCmd->save();
        
        $protexiomCmd = new protexiomCmd();
        $protexiomCmd->setName(__('Effacement defaut alarme', __FILE__));
        $protexiomCmd->setEqLogic_id($this->id);
        $protexiomCmd->setConfiguration('somfyCmd', 'RESET_ALARM_ERR');
        $protexiomCmd->setType('action');
        $protexiomCmd->setSubType('other');
        $protexiomCmd->save();
        
        $protexiomCmd = new protexiomCmd();
        $protexiomCmd->setName(__('Effacement defaut piles', __FILE__));
        $protexiomCmd->setEqLogic_id($this->id);
        $protexiomCmd->setConfiguration('somfyCmd', 'RESET_BATTERY_ERR');
        $protexiomCmd->setType('action');
        $protexiomCmd->setSubType('other');
        $protexiomCmd->save();
        
        $protexiomCmd = new protexiomCmd();
        $protexiomCmd->setName(__('Effacement defaut liaison', __FILE__));
        $protexiomCmd->setEqLogic_id($this->id);
        $protexiomCmd->setConfiguration('somfyCmd', 'RESET_LINK_ERR');
        $protexiomCmd->setType('action');
        $protexiomCmd->setSubType('other');
        $protexiomCmd->save();
        
        // Info CMD
        
        $protexiomCmd = new protexiomCmd();
        $protexiomCmd->setName(__('Zone A', __FILE__));
        $protexiomCmd->setEqLogic_id($this->id);
        $protexiomCmd->setConfiguration('somfyCmd', 'ZONE_A');
        $protexiomCmd->setUnite('');
        $protexiomCmd->setType('info');
        $protexiomCmd->setSubType('string');
        $protexiomCmd->save();
         
        $protexiomCmd = new protexiomCmd();
        $protexiomCmd->setName(__('Zone B', __FILE__));
        $protexiomCmd->setEqLogic_id($this->id);
        $protexiomCmd->setConfiguration('somfyCmd', 'ZONE_B');
        $protexiomCmd->setUnite('');
        $protexiomCmd->setType('info');
        $protexiomCmd->setSubType('string');
        $protexiomCmd->save();
         
        $protexiomCmd = new protexiomCmd();
        $protexiomCmd->setName(__('Zone C', __FILE__));
        $protexiomCmd->setEqLogic_id($this->id);
        $protexiomCmd->setConfiguration('somfyCmd', 'ZONE_C');
        $protexiomCmd->setUnite('');
        $protexiomCmd->setType('info');
        $protexiomCmd->setSubType('string');
        $protexiomCmd->save();
        
        $protexiomCmd = new protexiomCmd();
        $protexiomCmd->setName(__('Piles', __FILE__));
        $protexiomCmd->setEqLogic_id($this->id);
        $protexiomCmd->setConfiguration('somfyCmd', 'BATTERY');
        $protexiomCmd->setUnite('');
        $protexiomCmd->setType('info');
        $protexiomCmd->setSubType('string');
        $protexiomCmd->save();
        
        $protexiomCmd = new protexiomCmd();
        $protexiomCmd->setName(__('Liaison', __FILE__));
        $protexiomCmd->setEqLogic_id($this->id);
        $protexiomCmd->setConfiguration('somfyCmd', 'LINK');
        $protexiomCmd->setUnite('');
        $protexiomCmd->setType('info');
        $protexiomCmd->setSubType('string');
        $protexiomCmd->save();
        
        $protexiomCmd = new protexiomCmd();
        $protexiomCmd->setName(__('Portes', __FILE__));
        $protexiomCmd->setEqLogic_id($this->id);
        $protexiomCmd->setConfiguration('somfyCmd', 'DOOR');
        $protexiomCmd->setUnite('');
        $protexiomCmd->setType('info');
        $protexiomCmd->setSubType('string');
        $protexiomCmd->save();
        
        $protexiomCmd = new protexiomCmd();
        $protexiomCmd->setName(__('Alarme', __FILE__));
        $protexiomCmd->setEqLogic_id($this->id);
        $protexiomCmd->setConfiguration('somfyCmd', 'ALARM');
        $protexiomCmd->setUnite('');
        $protexiomCmd->setType('info');
        $protexiomCmd->setSubType('string');
        $protexiomCmd->save();
        
        $protexiomCmd = new protexiomCmd();
        $protexiomCmd->setName(__('Sabotage', __FILE__));
        $protexiomCmd->setEqLogic_id($this->id);
        $protexiomCmd->setConfiguration('somfyCmd', 'TAMPERED');
        $protexiomCmd->setUnite('');
        $protexiomCmd->setType('info');
        $protexiomCmd->setSubType('string');
        $protexiomCmd->save();
        
        $protexiomCmd = new protexiomCmd();
        $protexiomCmd->setName(__('Liaison GSM', __FILE__));
        $protexiomCmd->setEqLogic_id($this->id);
        $protexiomCmd->setConfiguration('somfyCmd', 'GSM_LINK');
        $protexiomCmd->setUnite('');
        $protexiomCmd->setType('info');
        $protexiomCmd->setSubType('string');
        $protexiomCmd->save();
        
        $protexiomCmd = new protexiomCmd();
        $protexiomCmd->setName(__('Récéption GSM', __FILE__));
        $protexiomCmd->setEqLogic_id($this->id);
        $protexiomCmd->setConfiguration('somfyCmd', 'GSM_SIGNAL');
        $protexiomCmd->setUnite('');
        $protexiomCmd->setType('info');
        $protexiomCmd->setSubType('numeric');
        $protexiomCmd->save();
        
        $protexiomCmd = new protexiomCmd();
        $protexiomCmd->setName(__('Opérateur GSM', __FILE__));
        $protexiomCmd->setEqLogic_id($this->id);
        $protexiomCmd->setConfiguration('somfyCmd', 'GSM_OPERATOR');
        $protexiomCmd->setUnite('');
        $protexiomCmd->setType('info');
        $protexiomCmd->setSubType('string');
        $protexiomCmd->save();
        
        $protexiomCmd = new protexiomCmd();
        $protexiomCmd->setName(__('Camera', __FILE__));
        $protexiomCmd->setEqLogic_id($this->id);
        $protexiomCmd->setConfiguration('somfyCmd', 'CAMERA');
        $protexiomCmd->setUnite('');
        $protexiomCmd->setType('info');
        $protexiomCmd->setSubType('string');
        $protexiomCmd->save();
  
    }

    /**
     * Called after a plugin device configuration setup or update
     * Standard Jeedom function
     *
     */
    public function postSave() {
    	if($this->getIsEnable()=='1'){
    		//Let's detect hardware version only if the device isEnabled.
    		//This will avoid infinite loop, as in case of error, we'll deactivate the device and save it again, meaning this function will run again
    		$myError="";
    		 
    		$myProtexiom = new phpProtexiom($this->getConfiguration('SomfyHostPort'));

    		$myError=$myProtexiom->detectHwVersion(); 
    		if($myError){//Hardware detection failed. it means protexiom was unreachable, or uncompatible
    			$myError.="Deactivating $this->name";
    			// Let's log the error in jeedom's log
    			log::add('protexiom', 'error', $myError, $this->name);
    		
    			// then reset hardware version
    			$this->setConfiguration('HwVersion', '');
    			// then deactivate the Device
    			$this->setIsEnable('0');
    			// and finally save our config modifications
    			$this->save();
    			// Let's raise an exception
    			throw new Exception(__('La version de votre protexiom n\'est pas compatible. Plus d\'info dans les logs de Jeedom.<br>Désactivation du device.', __FILE__));
    		}else{//Hardware detected with sucess
    			// Let's save hw Version to the device config, only if it has changes,to avoid infinite loop
    			if($this->getConfiguration('HwVersion')!=$myProtexiom->getHwVersion()){
    				$this->setConfiguration('HwVersion', $myProtexiom->getHwVersion());
    				$this->save();
    				log::add('protexiom', 'info', 'HwVersion changed to '.$myProtexiom->getHwVersion(), $this->name);
    			}
    			 
    		}
    	}
    	
        // TODO Schedule crontab
        //$this->reschedule();
    }

    /*public function preRemove() {
        $cron = cron::byClassAndFunction('weather', 'pull', array('weather_id' => intval($this->getId())));
        if (is_object($cron)) {
            $cron->remove();
        }
    }*/

    /*public function toHtml($_version = 'dashboard') {
        if ($this->getIsEnable() != 1) {
            return '';
        }
        $_version = jeedom::versionAlias($_version);
        $weather = $this->getWeatherFromYahooXml();
        if (!is_array($weather)) {
            $replace = array(
                '#icone#' => '',
                '#id#' => $this->getId(),
                '#city#' => '',
                '#condition#' => '{{Impossible de rÃ©cupÃ©rer la mÃ©tÃ©o.Pas d\'internet ?}}',
                '#temperature#' => '',
                '#windspeed#' => '',
                '#humidity#' => '',
                '#pressure#' => '',
                '#sunrise#' => '',
                '#sunset#' => '',
                '#collectDate#' => '',
                '#background_color#' => $this->getBackgroundColor($_version),
                '#eqLink#' => $this->getLinkToConfiguration(),
                '#forecast#' => '',
            );
            return template_replace($replace, getTemplate('core', $_version, 'current', 'weather'));
        }
        $html_forecast = '';
        $forcast_template = getTemplate('core', $_version, 'forecast', 'weather');
        foreach ($weather['forecast'] as $forecast) {
            $replace = array(
                '#day#' => $forecast['day'],
                '#icone#' => $forecast['icone'],
                '#low_temperature#' => $forecast['low_temperature'],
                '#hight_temperature#' => $forecast['high_temperature'],
            );
            $html_forecast .= template_replace($replace, $forcast_template);
        }
        $replace = array(
            '#id#' => $this->getId(),
            '#icone#' => $weather['condition']['icone'],
            '#city#' => $weather['location']['city'],
            '#condition#' => $weather['condition']['text'],
            '#temperature#' => $weather['condition']['temperature'],
            '#windspeed#' => $weather['wind']['speed'],
            '#humidity#' => $weather['atmosphere']['humidity'],
            '#pressure#' => $weather['atmosphere']['pressure'],
            '#sunrise#' => $weather['astronomy']['sunrise'],
            '#sunset#' => $weather['astronomy']['sunset'],
            '#collectDate#' => $this->getCollectDate(),
            '#background_color#' => $this->getBackgroundColor($_version),
            '#eqLink#' => $this->getLinkToConfiguration(),
            '#forecast#' => $html_forecast,
        );
        return template_replace($replace, getTemplate('core', $_version, 'current', 'weather'));
    }*/

    /*public function getShowOnChild() {
        return true;
    }*/

    /*     * **********************Getteur Setteur*************************** */

    /*public function getCollectDate() {
        return $this->_collectDate;
    }*/

    /*public function setCollectDate($_collectDate) {
        $this->_collectDate = $_collectDate;
    }*/

}

class protexiomCmd extends cmd {
    /*     * *************************Attributs****************************** */


    /*     * ***********************Methode static*************************** */


    /*     * *********************Methode d'instance************************* */

    
    /**
     * Tells Jeedom if it should remove existing commands during update in case they no longer exists in the POSTed form
     * Usefull, for exemple, in case you command list is static and created during postInsert
     * and you don't want to bother putting them in the desktop/plugin.php form.
     * Default to False (if you don't create the function), meaning missing commands ARE removed
     * Standard 
     *
     * @return bool
     */
    public function dontRemoveCmd() {
        return true;
    }
    

    public function execute($_options = array()) {
    	$protexiom=$this->getEqLogic();
    	$myError="";
  
    	$mySP=new phpProtexiom($protexiom->getConfiguration('SomfyHostPort'), $protexiom->getConfiguration('SSLEnabled'));
    	$mySP->userPwd=$protexiom->getConfiguration('UserPwd');
    	$mySP->authCard=$protexiom->getAuthCard();
    	$mySP->setHwVersion($protexiom->getConfiguration('HwVersion'));
    	if ($this->getType() == 'info') {
    	// TODO : implementer la commande de type info
        return "Not implemented yet";
      }elseif ($this->getType() == 'action') {
        /* if($myError=$mySP->doAction($this->getConfiguration('somfyCmd'))){
    			//an error occured
    			log::add('protexiom', 'error', "An error occured while running $this->name action: $myError", $protexiom->getName());
				throw new Exception(__($myError,__FILE__));
        }else{
    			//Command successfull
    			// TODO let's refresh status and return success
        		$protexiom->refreshStatus();
        		return;
        } */
        $protexiom->refreshStatus();
      }else{
        //unknown cmd type
      	log::add('protexiom', 'error', "$this->getType(): Unknown command type for $this->name", $protexiom->getName());
        throw new Exception(__("$this->getType(): Unknown command type for $this->name",__FILE__));
      }
    	
    	
    	
    	
        /*
        $eqLogic_weather = $this->getEqLogic();
        $weather = $eqLogic_weather->getWeatherFromYahooXml();

        if (!is_array($weather)) {
            sleep(1);
            $weather = $eqLogic_weather->getWeatherFromYahooXml();
            if (!is_array($weather)) {
                return false;
            }
        }

        if ($this->getConfiguration('day') == -1) {
            if ($this->getConfiguration('data') == 'condition') {
                return $weather['condition']['text'];
            }
            if ($this->getConfiguration('data') == 'temp') {
                return $weather['condition']['temperature'];
            }
            if ($this->getConfiguration('data') == 'humidity') {
                return $weather['atmosphere']['humidity'];
            }
            if ($this->getConfiguration('data') == 'pressure') {
                return $weather['atmosphere']['pressure'];
            }
            if ($this->getConfiguration('data') == 'wind_speed') {
                return $weather['wind']['speed'];
            }
            if ($this->getConfiguration('data') == 'wind_direction') {
                return $weather['wind']['direction'];
            }
            if ($this->getConfiguration('data') == 'sunrise') {
                return $weather['astronomy']['sunrise'];
            }
            if ($this->getConfiguration('data') == 'sunset') {
                return $weather['astronomy']['sunset'];
            }
        }

        if ($this->getConfiguration('data') == 'condition') {
            return $weather['forecast'][$this->getConfiguration('day')]['condition'];
        }
        if ($this->getConfiguration('data') == 'low') {
            return $weather['forecast'][$this->getConfiguration('day')]['low_temperature'];
        }
        if ($this->getConfiguration('data') == 'high') {
            return $weather['forecast'][$this->getConfiguration('day')]['high_temperature'];
        }
        return false;
        */
    }

    /*     * **********************Getteur Setteur*************************** */
}

?>
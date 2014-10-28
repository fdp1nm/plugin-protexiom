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
    private $_SomfySessionTimeout=5400;
    
    public $_spBrowser;
    

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
    private function getAuthCard()
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
    
    /**
     * initSpBrowser instanciate and initialise $this->_spBrowser phpProtexiom object
     *
     * @author Fdp1
     * @return 
     */
    public function initSpBrowser()
    {
    	$this->_spBrowser=new phpProtexiom($this->getConfiguration('SomfyHostPort'), $this->getConfiguration('SSLEnabled'));
    	$this->_spBrowser->userPwd=$this->getConfiguration('UserPwd');
    	$this->_spBrowser->authCard=$this->getAuthCard();
    	$this->_spBrowser->setHwVersion($this->getConfiguration('HwVersion'));
    	//Let's set the authCookie if cached
    	$cache=cache::byKey('somfyAuthCookie::'.$this->getId());
    	$cachedCookie=$cache->getValue();
    	if(!($cachedCookie==='' || $cachedCookie===null || $cachedCookie=='false')){
    		log::add('protexiom', 'debug', 'Cached protexiom cookie found during initSpBrowser.', $this->name);
    		$this->_spBrowser->authCookie=$cachedCookie;
    		$cache->setLifetime($this->_SomfySessionTimeout);
    		$cache->save();
    	}
    	return;
    }//End initSpBrowser func
    
    public static function pull($_options) {
    	log::add('protexiom', 'debug', 'Running protexiom PULL '.date("Y-m-d H:i:s"), $protexiom->name);
        $protexiom = protexiom::byId($_options['protexiom_id']);
        if (is_object($protexiom)) {
        	$protexiom->initSpBrowser();
        	if (!($protexiom->_spBrowser->authCookie)){//Empty authCookie mean not logged in
        		if($myError=$protexiom->_spBrowser->doLogin()){
        			log::add('protexiom', 'error', 'Login failed during scheduled pull for the protexiom device '.$protexiom->name.'. Pull aborted.', $protexiom->name);
        			throw new Exception('Login failed during scheduled pull for the protexiom device '.$protexiom->name.'. Pull aborted.');
        		}else{//Login OK
        			cache::set('somfyAuthCookie::'.$protexiom->getId(), $protexiom->_spBrowser->authCookie, $protexiom->_SomfySessionTimeout);
        			log::add('protexiom', 'debug', 'Sucessfull login during scheduled pull. authCookie cached.', $protexiom->name);
        		}
        	}
        	$protexiom->pullStatus();	
        } else {
            $protexiom>unSchedule();
            log::add('protexiom', 'error', 'Protexiom ID non trouvÃ© : ' . $_options['protexiom_id'] . '. Tache supprimÃ©', $protexiom->name);
            throw new Exception('Protexiom ID non trouvÃ© : ' . $_options['protexiom_id'] . '. Tache supprimÃ©');
        }
    	return;
    }  
    
    /*
     * Refresh every info Cmd at once
     * @return 0 in case of sucess, 1 otherwise
     */
    public function pullStatus() {
    	
    	$myError="";
    	
    	if($myError=$this->_spBrowser->pullStatus()){
    		//An error occured while pulling status. If polling is on, this may be a session timeout issue.
    		//Let's check if polling is on and if yes, try to start a new session
    		$cache=cache::byKey('somfyAuthCookie::'.$this->getId());
    		$cachedCookie=$cache->getValue();
    		if(!($cachedCookie==='' || $cachedCookie===null || $cachedCookie=='false')){
    			//The session was cached, so the timeout issue is possible. Starting a new session
    			$this->_spBrowser->doLogout();
    			$cache->setValue('');
    			log::add('protexiom', 'debug', 'Logout to workaround somfy session timeout bug on device '.$this->name.'.', $this->name);
    			if($this->_spBrowser->doLogin()){
    				log::add('protexiom', 'debug', 'Login failed while trying to workaround somfy session timeout bug on device '.$protexiom->name.'.', $protexiom->name);
    			}else{//Login OK
    				$cache->setValue($this->_spBrowser->authCookie);
    				$myError=$this->_spBrowser->pullStatus();
    			}
    			$cache->save();
    		}
    	}
    	if($myError){
    		//An error occured.
    		log::add('protexiom', 'error', "An error occured during $this->name status update: ".$myError, $this->name);
    		return 1;
    	}else{
    		//Status pulled. Let's now refreh CMD
    		log::add('protexiom', 'info', 'Status refreshed', $this->name);
    		$status=$this->setStatusFromSpBrowser();
    		return 0;
    	}
    }//End function pullStatus()
    
    /*
     * Update status from spBrowser
    * @return 0 in case of sucess, 1 otherwise
    */
    public function setStatusFromSpBrowser() {
    
    	$myError="";
    
    	$status=$this->_spBrowser->getStatus();
    	foreach ($this->getCmd() as $cmd) {
    		if ($cmd->getType() == "info") {
    			if($cmd->getValue() != $status[$cmd->getConfiguration('somfyCmd')])
    				$cmd->event($status[$cmd->getConfiguration('somfyCmd')]);
    		}
    	}
    	return;
    }//End function setStatusFromSpBrowser

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
        //Checking polling interval
        if(!($this->getConfiguration('PollInt')=="" || $this->getConfiguration('PollInt')=="0")){
        	if(!filter_var($this->getConfiguration('PollInt'), FILTER_VALIDATE_INT, array('options' => array('min_range' => 5)))){
        		throw new Exception(__('La frequence de mise à jour (polling) est invalide. Elle doit vide ou égale a zero si vous souhaitez désactiver le polling. Elle doit contenir un nombre (entier) de seconde superieur a 5 sinon.', __FILE__));
        	}
        }//else, PollInt empty or 0, means polling is off.
			
        /* //Finally, if a proxy is specified, let's check it's valid
        if($this->getConfiguration('WebProxyHostPort')){
        	if (!$this->isValidHostPort($this->getConfiguration('WebProxyHostPort'))) {
        		throw new Exception(__('Proxy web invalide', __FILE__));
        	}
        } */
        
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
    	//Let's unschedule protexiom pull
    	//If getIsenable == 1, we will reschedule (with an up to date polling interval)
    	$this->unSchedule();
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
    			log::add('protexiom', 'info', 'HwVersion changed to '.$myProtexiom->getHwVersion(), $this->name);
    			if(filter_var($this->getConfiguration('PollInt'), FILTER_VALIDATE_INT, array('options' => array('min_range' => 1)))){
    				$this->schedule();
    			}
    		}
    	}//else{//eqLogic disabled
    }
    
    public function preRemove(){
    	$this->unSchedule();
    }
    
    public function schedule(){
    	$cron = cron::byClassAndFunction('protexiom', 'pull', array('protexiom_id' => intval($this->getId())));
    	if (!is_object($cron)) {
    		$cron=new cron();
    		$cron->setClass('protexiom');
    		$cron->setFunction('pull');
    		$cron->setOption(array('protexiom_id' => intval($this->getId())));
    		$cron->setEnable(1);
    		$cron->setDeamon(1);(1);
    		$cron->setDeamonSleepTime(intval($this->getConfiguration('PollInt')));
    		$cron->setSchedule('* * * * *');
    		$cron->save();
    		log::add('protexiom', 'info', 'Scheduling protexiom pull for equipement '.$this->name.'.', $this->name);
    	}
    }//end schedule function
    
    public function unSchedule(){
    	//Before stopping polling, let's logoff en clear authCookie
    	$cache=cache::byKey('somfyAuthCookie::'.$this->getId());
    	$cachedCookie=$cache->getValue();
    	if(!($cachedCookie==='' || $cachedCookie===null || $cachedCookie=='false')){
    		$this->initSpBrowser();
    		$this->_spBrowser->doLogout();
    		//$cache->flush();
    		$cache->setValue('');
    		$cache->save();
    		log::add('protexiom', 'info', 'Removing cached cookie while unscheduling '.$this->name.'.', $this->name);
    	}
    	
		$cron = cron::byClassAndFunction('protexiom', 'pull', array('protexiom_id' => intval($this->getId())));
    	if (is_object($cron)) {
    		$cron->remove();
    		log::add('protexiom', 'info', 'Removing protexiom pull schedule for equipement '.$this->name.'.', $this->name);
    	}
		$cron = cron::byClassAndFunction('protexiom', 'pull', array('protexiom_id' => intval($this->getId())));
    	if (is_object($cron)) {
			echo '*!*!*!*!*!*!*IMPORTANT : unable to remove protexiom pull daemon for device '.$this->name.'. You may have to manually remove it. *!*!*!*!*!*!*!*';
    		log::add('protexiom', 'error', 'Unable to remove protexiom pull daemon for device '.$this->name.'. You may have to manually remove it.', $this->name);
    	}
    }//end unSchedule function

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
  
    	if ($this->getType() == 'info') {
    		if(!filter_var($protexiom->getConfiguration('PollInt'), FILTER_VALIDATE_INT, array('options' => array('min_range' => 1)))){
    			//Polling is off. Let's pull status before getting the value
    			$protexiom->pullStatus();
    			$protexiom->setStatusFromSpBrowser();
    		}
        	return $this->getValue();
      	}elseif ($this->getType() == 'action') {
      		$protexiom->initSpBrowser();
        	if($myError=$protexiom->_spBrowser->doAction($this->getConfiguration('somfyCmd'))){
    			//an error occured
    			// TODO Let's workaround the somfy session timeout bug
    			log::add('protexiom', 'error', "An error occured while running $this->name action: $myError", $protexiom->getName());
				throw new Exception(__("An error occured while running $this->name action: $myError",__FILE__));
        	}else{
    			//Command successfull
        		$protexiom->setStatusFromSpBrowser();
        		return;
        	}
      	}else{
        	//unknown cmd type
      		log::add('protexiom', 'error', "$this->getType(): Unknown command type for $this->name", $protexiom->getName());
        	throw new Exception(__("$this->getType(): Unknown command type for $this->name",__FILE__));
      	}
    		
    }

    /*     * **********************Getteur Setteur*************************** */
}

?>
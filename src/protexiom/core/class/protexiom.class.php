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

    protected $_UpdateDate = '';
    protected $_HwVersion = '';
    protected $_SomfyAuthCookie = '';
    protected $_SomfyHost = '';
    protected $_SomfyPort = '';
    protected $_WebProxyHost = '';
    protected $_WebProxyPort = '';
    protected $_SomfySessionTimeout=5400;
    protected $_SomfyStatusCacheLifetime=30;
    
    private static $_templateArray = array();
    
    public $_spBrowser;
    

    /*     * ***********************Static methods*************************** */
    /*     * ****accessible without needing an instantiation of the class**** */
   
     /**
     * Instanciate protexiom eqLogic and pull status
     *
     * @author Fdp1
     * @param array $_options['protexiom_id']
     * @return 
     */
    public static function pull($_options) {
    	log::add('protexiom', 'debug', 'Running protexiom pull '.date("Y-m-d H:i:s"), $protexiom->name);
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
            $protexiom->unSchedulePull();
            log::add('protexiom', 'error', 'Protexiom ID non trouvÃ© : ' . $_options['protexiom_id'] . '. Tache pull supprimÃ©', $protexiom->name);
            throw new Exception('Protexiom ID non trouvÃ© : ' . $_options['protexiom_id'] . '. Tache pull supprimÃ©');
        }
    	return;
    } //end pull function 
    
    /**
     * Instanciate protexiom eqLogic and tries to login. If login OK, set needs_reboot cmd to 0
     *
     * @author Fdp1
     * @param array $_options['protexiom_id']
     * @return
     */
    public static function isRebooted($_options) {
    	log::add('protexiom', 'debug', 'Trying to login to check reboot '.date("Y-m-d H:i:s"), $protexiom->name);
    	$protexiom = protexiom::byId($_options['protexiom_id']);
    	if (is_object($protexiom)) {
    		$protexiom->initSpBrowser();
    		if(!($myError=$protexiom->_spBrowser->doLogin())){
    			//Login OK
    			cache::set('somfyAuthCookie::'.$protexiom->getId(), $protexiom->_spBrowser->authCookie, $protexiom->_SomfySessionTimeout);
    			log::add('protexiom', 'debug', 'Sucessfull login during reboot check. authCookie cached.', $protexiom->name);
    			$protexiom->pullStatus();
    			$protexiom->unScheduleIsRebooted();
    			if(filter_var($protexiom->getConfiguration('PollInt'), FILTER_VALIDATE_INT, array('options' => array('min_range' => 1)))){
    				$protexiom->schedulePull();
    			}
    			$needsRebootCmd=$protexiom->getCmd(null, 'needs_reboot');
    			if(is_object($needsRebootCmd)){
    				$needsRebootCmd->event("0");
    			}else{
    				log::add('protexiom', 'error', 'Protexiom reboot went OK, but I\'ve been unable to reset needs_reboot cmd', $protexiom->name);
    				throw new Exception('Protexiom reboot went OK, but I\'ve been unable to reset needs_reboot cmd');
    			}
    		}	
    	}else{
    		$protexiom->unScheduleIsRebooted();
    		log::add('protexiom', 'error', 'Protexiom ID non trouvÃ© : ' . $_options['protexiom_id'] . '. Tache isRebooted supprimÃ©.', $protexiom->name);
    		throw new Exception('Protexiom ID non trouvÃ© : ' . $_options['protexiom_id'] . '. Tache isRebooted supprimÃ©.');
    	}
    	return;
    } //end isRebooted function

    /*     * **********************Instance methods************************** */

    /**
     * Pull status and refresh every info Cmd at once
     * @author Fdp1
     * @return 0 in case of sucess, 1 otherwise
     */
    public function pullStatus() {
    	 
    	$myError="";
    	if (!is_object($this->_spBrowser)) {
    		$this->initSpBrowser();
    	}
    	if($myError=$this->_spBrowser->pullStatus()){
    		//An error occured while pulling status. This may be a session timeout issue.
    		if(!$this->workaroundSomfySessionTimeoutBug()){
    			$myError=$this->_spBrowser->pullStatus();
    		}
    	}else{
    		//Due to a somfy bug on some HW version, when a session is maintaned for a long time
    		//Somfy sometimes return an empty XML file. This never happends whith a fresh session, but can happen here. Let's check
    		$status=$this->_spBrowser->getStatus();
    		if($status['ALARM']==""){
    			//Empty XML file detected
    			//Let's log off and on again to workaround this somfy bug
    			log::add('protexiom', 'info', 'Log off and on again to workaround somfy empty XML bug on device '.$this->name.'.', $this->name);
    			$this->_spBrowser->doLogout();
    			if($this->_spBrowser->doLogin()){
    				log::add('protexiom', 'error', 'Login failed while trying to workaround somfy empty XML bug on device '.$protexiom->name.'.', $protexiom->name);
    				$cache=cache::byKey('somfyAuthCookie::'.$this->getId());
    				$cachedCookie=$cache->getValue();
    				if(!($cachedCookie==='' || $cachedCookie===null || $cachedCookie=='false')){
    					//The session was cached. Let's empty the cached cookie as we just logged off
    					$cache->setValue('');
    					$cache->save();
    				}
    				return 1;
    			}else{//Login OK
    				$cache=cache::byKey('somfyAuthCookie::'.$this->getId());
    				$cachedCookie=$cache->getValue();
    				if(!($cachedCookie==='' || $cachedCookie===null || $cachedCookie=='false')){
    					$cache->setValue($this->_spBrowser->authCookie);
    					$cache->save();
    				}
    				$myError=$this->_spBrowser->pullStatus();
    			}
    		}
    	}
    	if($myError){
    		//An error occured.
    		log::add('protexiom', 'error', "An error occured during $this->name status update: ".$myError, $this->name);
    		return 1;
    	}else{
    		//Status pulled. Let's now refreh CMD
    		log::add('protexiom', 'info', 'Status refreshed', $this->name);
    		$this->setStatusFromSpBrowser();
    		return 0;
    	}
    }//End function pullStatus()
    
    /**
     * Check wether the parameter is a valid port number.
     *
     * @author Fdp1
     * @param string $port port number.
     * @return bool True if the string is valid, false otherwise
     * @usage isValid = isValidPort("80")
     */
    protected function isValidPort($port = '')
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
    protected function isValidHost($host = '')
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
    protected function isValidHostPort($hostPort = '')
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
    protected function getAuthCard()
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
    	//Let's get the authCookie if cached
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
    
    /**
     * Called before setting-up or updating a plugin device
     * Standard Jeedom function
     * @author Fdp1
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
     * @author Fdp1
     *
     */
    public function preInsert() {
    	$this->setCategory('security', 1);
    }

    /**
     * Called after inserting a plugin device when creating it, before the first configuration
     * Standard Jeedom function
     * @author Fdp1
     *
     */
    public function postInsert() {
    	
    	//Action CMD
    	
    	$protexiomCmd = new protexiomCmd();
        $protexiomCmd->setName(__('Marche A+B+C', __FILE__));
        $protexiomCmd->setLogicalId('zoneabc_on');
        $protexiomCmd->setEqLogic_id($this->id);
        $protexiomCmd->setConfiguration('somfyCmd', 'ZONEABC_ON');
        $protexiomCmd->setType('action');
        $protexiomCmd->setSubType('other');
        $protexiomCmd->save();

        $protexiomCmd = new protexiomCmd();
        $protexiomCmd->setName(__('Marche A', __FILE__));
        $protexiomCmd->setLogicalId('zonea_on');
        $protexiomCmd->setEqLogic_id($this->id);
        $protexiomCmd->setConfiguration('somfyCmd', 'ZONEA_ON');
        $protexiomCmd->setType('action');
        $protexiomCmd->setSubType('other');
        $protexiomCmd->save();

        $protexiomCmd = new protexiomCmd();
        $protexiomCmd->setName(__('Marche B', __FILE__));
        $protexiomCmd->setLogicalId('zoneb_on');
        $protexiomCmd->setEqLogic_id($this->id);
        $protexiomCmd->setConfiguration('somfyCmd', 'ZONEB_ON');
        $protexiomCmd->setType('action');
        $protexiomCmd->setSubType('other');
        $protexiomCmd->save();

        $protexiomCmd = new protexiomCmd();
        $protexiomCmd->setName(__('Marche C', __FILE__));
        $protexiomCmd->setLogicalId('zonec_on');
        $protexiomCmd->setEqLogic_id($this->id);
        $protexiomCmd->setConfiguration('somfyCmd', 'ZONEC_ON');
        $protexiomCmd->setType('action');
        $protexiomCmd->setSubType('other');
        $protexiomCmd->save();

        $protexiomCmd = new protexiomCmd();
        $protexiomCmd->setName(__('Arret A+B+C', __FILE__));
        $protexiomCmd->setLogicalId('abc_off');
        $protexiomCmd->setEqLogic_id($this->id);
        $protexiomCmd->setConfiguration('somfyCmd', 'ALARME_OFF');
        $protexiomCmd->setType('action');
        $protexiomCmd->setSubType('other');
        $protexiomCmd->save();
        
        $protexiomCmd = new protexiomCmd();
        $protexiomCmd->setName(__('Lumières On', __FILE__));
        $protexiomCmd->setLogicalId('light_on');
        $protexiomCmd->setEqLogic_id($this->id);
        $protexiomCmd->setConfiguration('somfyCmd', 'LIGHT_ON');
        $protexiomCmd->setType('action');
        $protexiomCmd->setSubType('other');
        //$protexiomCmd->setIsVisible(0);
        $protexiomCmd->save();
        
        $protexiomCmd = new protexiomCmd();
        $protexiomCmd->setName(__('Lumières Off', __FILE__));
        $protexiomCmd->setLogicalId('light_off');
        $protexiomCmd->setEqLogic_id($this->id);
        $protexiomCmd->setConfiguration('somfyCmd', 'LIGHT_OFF');
        $protexiomCmd->setType('action');
        $protexiomCmd->setSubType('other');
        //$protexiomCmd->setIsVisible(0);
        $protexiomCmd->save();
        
        $protexiomCmd = new protexiomCmd();
        $protexiomCmd->setName(__('Volets montée', __FILE__));
        $protexiomCmd->setLogicalId('shutter_up');
        $protexiomCmd->setEqLogic_id($this->id);
        $protexiomCmd->setConfiguration('somfyCmd', 'SHUTTER_UP');
        $protexiomCmd->setType('action');
        $protexiomCmd->setSubType('other');
        //$protexiomCmd->setIsVisible(0);
        $protexiomCmd->save();
        
        $protexiomCmd = new protexiomCmd();
        $protexiomCmd->setName(__('Volets descente', __FILE__));
        $protexiomCmd->setLogicalId('shutter_down');
        $protexiomCmd->setEqLogic_id($this->id);
        $protexiomCmd->setConfiguration('somfyCmd', 'SHUTTER_DOWN');
        $protexiomCmd->setType('action');
        $protexiomCmd->setSubType('other');
        //$protexiomCmd->setIsVisible(0);
        $protexiomCmd->save();
        
        $protexiomCmd = new protexiomCmd();
        $protexiomCmd->setName(__('Volets stop', __FILE__));
        $protexiomCmd->setLogicalId('shutter_stop');
        $protexiomCmd->setEqLogic_id($this->id);
        $protexiomCmd->setConfiguration('somfyCmd', 'SHUTTER_STOP');
        $protexiomCmd->setType('action');
        $protexiomCmd->setSubType('other');
        //$protexiomCmd->setIsVisible(0);
        $protexiomCmd->save();
        
        $protexiomCmd = new protexiomCmd();
        $protexiomCmd->setName(__('Eff. defaut alarm', __FILE__));
        $protexiomCmd->setLogicalId('reset_alarm_err');
        $protexiomCmd->setEqLogic_id($this->id);
        $protexiomCmd->setConfiguration('somfyCmd', 'RESET_ALARM_ERR');
        $protexiomCmd->setType('action');
        $protexiomCmd->setSubType('other');
        $protexiomCmd->save();
        
        $protexiomCmd = new protexiomCmd();
        $protexiomCmd->setName(__('Eff. defaut piles', __FILE__));
        $protexiomCmd->setLogicalId('reset_battery_err');
        $protexiomCmd->setEqLogic_id($this->id);
        $protexiomCmd->setConfiguration('somfyCmd', 'RESET_BATTERY_ERR');
        $protexiomCmd->setType('action');
        $protexiomCmd->setSubType('other');
        $protexiomCmd->save();
        
        $protexiomCmd = new protexiomCmd();
        $protexiomCmd->setName(__('Eff. defaut liaison', __FILE__));
        $protexiomCmd->setLogicalId('reset_link_err');
        $protexiomCmd->setEqLogic_id($this->id);
        $protexiomCmd->setConfiguration('somfyCmd', 'RESET_LINK_ERR');
        $protexiomCmd->setType('action');
        $protexiomCmd->setSubType('other');
        $protexiomCmd->save();
        
        // Info CMD
        
        $protexiomCmd = new protexiomCmd();
        $protexiomCmd->setName(__('Zone A', __FILE__));
        $protexiomCmd->setLogicalId('zone_a');
        $protexiomCmd->setEqLogic_id($this->id);
        $protexiomCmd->setConfiguration('somfyCmd', 'ZONE_A');
        $protexiomCmd->setUnite('');
        $protexiomCmd->setType('info');
        $protexiomCmd->setSubType('binary');
        $protexiomCmd->save();
         
        $protexiomCmd = new protexiomCmd();
        $protexiomCmd->setName(__('Zone B', __FILE__));
        $protexiomCmd->setLogicalId('zone_b');
        $protexiomCmd->setEqLogic_id($this->id);
        $protexiomCmd->setConfiguration('somfyCmd', 'ZONE_B');
        $protexiomCmd->setUnite('');
        $protexiomCmd->setType('info');
        $protexiomCmd->setSubType('binary');
        $protexiomCmd->save();
         
        $protexiomCmd = new protexiomCmd();
        $protexiomCmd->setName(__('Zone C', __FILE__));
        $protexiomCmd->setLogicalId('zone_c');
        $protexiomCmd->setEqLogic_id($this->id);
        $protexiomCmd->setConfiguration('somfyCmd', 'ZONE_C');
        $protexiomCmd->setUnite('');
        $protexiomCmd->setType('info');
        $protexiomCmd->setSubType('binary');
        $protexiomCmd->save();
        
        $protexiomCmd = new protexiomCmd();
        $protexiomCmd->setName(__('Piles', __FILE__));
        $protexiomCmd->setLogicalId('battery');
        $protexiomCmd->setEqLogic_id($this->id);
        $protexiomCmd->setConfiguration('somfyCmd', 'BATTERY');
        $protexiomCmd->setUnite('');
        $protexiomCmd->setType('info');
        $protexiomCmd->setSubType('binary');
        $protexiomCmd->save();
        
        $protexiomCmd = new protexiomCmd();
        $protexiomCmd->setName(__('Liaison', __FILE__));
        $protexiomCmd->setLogicalId('link');
        $protexiomCmd->setEqLogic_id($this->id);
        $protexiomCmd->setConfiguration('somfyCmd', 'LINK');
        $protexiomCmd->setUnite('');
        $protexiomCmd->setType('info');
        $protexiomCmd->setSubType('binary');
        $protexiomCmd->save();
        
        $protexiomCmd = new protexiomCmd();
        $protexiomCmd->setName(__('Portes', __FILE__));
        $protexiomCmd->setLogicalId('door');
        $protexiomCmd->setEqLogic_id($this->id);
        $protexiomCmd->setConfiguration('somfyCmd', 'DOOR');
        $protexiomCmd->setUnite('');
        $protexiomCmd->setType('info');
        $protexiomCmd->setSubType('binary');
        $protexiomCmd->save();
        
        $protexiomCmd = new protexiomCmd();
        $protexiomCmd->setName(__('Alarme', __FILE__));
        $protexiomCmd->setLogicalId('alarm');
        $protexiomCmd->setEqLogic_id($this->id);
        $protexiomCmd->setConfiguration('somfyCmd', 'ALARM');
        $protexiomCmd->setUnite('');
        $protexiomCmd->setType('info');
        $protexiomCmd->setSubType('binary');
        $protexiomCmd->save();
        
        $protexiomCmd = new protexiomCmd();
        $protexiomCmd->setName(__('Sabotage', __FILE__));
        $protexiomCmd->setLogicalId('tampered');
        $protexiomCmd->setEqLogic_id($this->id);
        $protexiomCmd->setConfiguration('somfyCmd', 'TAMPERED');
        $protexiomCmd->setUnite('');
        $protexiomCmd->setType('info');
        $protexiomCmd->setSubType('binary');
        $protexiomCmd->save();
        
        $protexiomCmd = new protexiomCmd();
        $protexiomCmd->setName(__('Liaison GSM', __FILE__));
        $protexiomCmd->setLogicalId('gsm_link');
        $protexiomCmd->setEqLogic_id($this->id);
        $protexiomCmd->setConfiguration('somfyCmd', 'GSM_LINK');
        $protexiomCmd->setUnite('');
        $protexiomCmd->setType('info');
        $protexiomCmd->setSubType('string');
        $protexiomCmd->save();
        
        $protexiomCmd = new protexiomCmd();
        $protexiomCmd->setName(__('Récéption GSM', __FILE__));
        $protexiomCmd->setLogicalId('gsm_signal');
        $protexiomCmd->setEqLogic_id($this->id);
        $protexiomCmd->setConfiguration('somfyCmd', 'GSM_SIGNAL');
        $protexiomCmd->setUnite('');
        $protexiomCmd->setType('info');
        $protexiomCmd->setSubType('numeric');
        $protexiomCmd->save();
        
        $protexiomCmd = new protexiomCmd();
        $protexiomCmd->setName(__('Opérateur GSM', __FILE__));
        $protexiomCmd->setLogicalId('gsm_operator');
        $protexiomCmd->setEqLogic_id($this->id);
        $protexiomCmd->setConfiguration('somfyCmd', 'GSM_OPERATOR');
        $protexiomCmd->setUnite('');
        $protexiomCmd->setType('info');
        $protexiomCmd->setSubType('string');
        $protexiomCmd->save();
        
        $protexiomCmd = new protexiomCmd();
        $protexiomCmd->setName(__('Camera', __FILE__));
        $protexiomCmd->setLogicalId('camera');
        $protexiomCmd->setEqLogic_id($this->id);
        $protexiomCmd->setConfiguration('somfyCmd', 'CAMERA');
        $protexiomCmd->setUnite('');
        $protexiomCmd->setType('info');
        $protexiomCmd->setSubType('binary');
        $protexiomCmd->save();
        
        $protexiomCmd = new protexiomCmd();
        $protexiomCmd->setName(__('Redémarrage requis', __FILE__));
        $protexiomCmd->setLogicalId('needs_reboot');
        $protexiomCmd->setEqLogic_id($this->id);
        //$protexiomCmd->setConfiguration('somfyCmd', 'needs_reboot');
        $protexiomCmd->setUnite('');
        $protexiomCmd->setType('info');
        $protexiomCmd->setSubType('binary');
        $protexiomCmd->setIsVisible(0);
        $protexiomCmd->save();
  
    }

    /**
     * Called after a plugin device configuration setup or update
     * Standard Jeedom function
     * @author Fdp1
     *
     */
    public function postSave() {
    	//Let's unschedule protexiom pull
    	//If getIsenable == 1, we will reschedule (with an up to date polling interval)
    	$this->unSchedulePull();
    	cache::deleteBySearch('somfyStatus::'.$this->getId());
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
    				log::add('protexiom', 'info', 'HwVersion set to '.$myProtexiom->getHwVersion(), $this->name);
    			}
    			// Let's initialise the needs_reboot cmd to 0
    			$needsRebootCmd=$this->getCmd(null, 'needs_reboot');
    			if(is_object($needsRebootCmd)){
    				$needsRebootCmd->event("0");
    			}else{
    				log::add('protexiom', 'error', 'Unable to reset needs_reboot cmd while saving '.$this->name.' protexiom eqLogic', $this->name);
    			}
    			// Let's schedul pull if polling is on
    			if(filter_var($this->getConfiguration('PollInt'), FILTER_VALIDATE_INT, array('options' => array('min_range' => 1)))){
    				$this->schedulePull();
    			}//else{// Polling is off
    			
    			// And finally, let's initialize status
    			$this->pullStatus();
    		}
    	}//else{//eqLogic disabled
    	
    	//Let's set specific CMD configuration
    	foreach ($this->getCmd('info') as $cmd) {
    		if($cmd->getLogicalId() == 'gsm_signal'){
    			$cmd->setConfiguration('maxValue', 5);
    			$cmd->save();
    		}elseif($cmd->getLogicalId() == 'zoneabc_on'){
    			$protexiomCmd->setConfiguration('mobileTag', 'On A+B+C');
    		}elseif($cmd->getLogicalId() == 'zonea_on'){
    			$protexiomCmd->setConfiguration('mobileTag', 'On A');
    		}elseif($cmd->getLogicalId() == 'zoneb_on'){
    			$protexiomCmd->setConfiguration('mobileTag', 'On B');
    		}elseif($cmd->getLogicalId() == 'zonec_on'){
    			$protexiomCmd->setConfiguration('mobileTag', 'On C');
    		}elseif($cmd->getLogicalId() == 'abc_off'){
    			$protexiomCmd->setConfiguration('mobileTag', 'Off A+B+ C');
    		}elseif($cmd->getLogicalId() == 'light_on'){
    			$protexiomCmd->setConfiguration('mobileTag', 'Lum. On');
    		}elseif($cmd->getLogicalId() == 'light_off'){
    			$protexiomCmd->setConfiguration('mobileTag', 'Lum. Off');
    		}elseif($cmd->getLogicalId() == 'shutter_up'){
    			$protexiomCmd->setConfiguration('mobileTag', 'Volets Ouv');
    		}elseif($cmd->getLogicalId() == 'shutter_down'){
    			$protexiomCmd->setConfiguration('mobileTag', 'Volets Ferm');
    		}elseif($cmd->getLogicalId() == 'shutter_stop'){
    			$protexiomCmd->setConfiguration('mobileTag', 'Volets stop');
    		}elseif($cmd->getLogicalId() == 'reset_alarm_err'){
    			$protexiomCmd->setConfiguration('mobileTag', 'Eff. alarm');
    		}elseif($cmd->getLogicalId() == 'reset_battery_err'){
    			$protexiomCmd->setConfiguration('mobileTag', 'Eff. piles');
    		}elseif($cmd->getLogicalId() == 'reset_link_err'){
    			$protexiomCmd->setConfiguration('mobileTag', 'Eff. comm');
    		}
    		
    	}
    }
    
    /**
     * Called before removing a protexiom eqLogic
     * Standard Jeedom function
     * @author Fdp1
     *
     */
    public function preRemove(){
    	$this->unSchedulePull();
    	cache::deleteBySearch('somfyStatus::'.$this->getId());
    }
    
    /**
     * Called to display the widget
     * Standard Jeedom function
     * @param string $_version Widget version to display (mobile, dashboard or scenario)
     * @return string widget HTML code
     * @author Fdp1
     *
     */
    public function toHtml($_version = 'dashboard') {
    	if ($_version == '') {
    		throw new Exception(__('La version demandé ne peut être vide (mobile, dashboard ou scenario)', __FILE__));
    	}
    	$cmdDisplayOrder=array(
    			"alarm",
    			"link",
    			"battery",
    			"door",
    			"tampered",
    			"camera",
    			"zone_a",
    			"zone_b",
    			"zone_c",
    			"zoneabc_on",
    			"zonea_on",
    			"zoneb_on",
    			"zonec_on",
    			"abc_off",
    			"gsm_link",
    			"gsm_signal",
    			"gsm_operator",
    			"light_on",
    			"light_off",
    			"shutter_upp",
    			"shutter_stop",
    			"shutter_down",
    			"reset_alarm_err",
    			"reset_battery_err",
    			"reset_link_err",
    			"needs_reboot",
    	);
    	$info = '';
    	$version = jeedom::versionAlias($_version);
    	$vcolor = 'cmdColor';
    	if ($version == 'mobile') {
    		$vcolor = 'mcmdColor';
    	}
    	if ($this->getPrimaryCategory() == '') {
    		$cmdColor = '';
    	} else {
    		$cmdColor = jeedom::getConfiguration('eqLogic:category:' . $this->getPrimaryCategory() . ':' . $vcolor);
    	}
    	if ($this->getIsEnable()) {
    		foreach ($cmdDisplayOrder as $cmdLogicId) {
    			$cmd=$this->getCmd(null, $cmdLogicId, true);
    			if(is_object($cmd) && is_numeric($cmd->getId()) && $cmd->getIsVisible()){
    					$info.=$cmd->toHtml($_version, '', $cmdColor);
    			}	
    		}
    		//Let's check if some CMDs are missing in $cmdDisplayOrder
    		foreach ($this->getCmd(null, null, true) as $cmd) {
    			if (!in_array($cmd->getLogicalId(), $cmdDisplayOrder)) {
    				$info.=$cmd->toHtml($_version, '', $cmdColor);
    			}
    		}
    		
    		
    	}
    	$replace = array(
    			'#id#' => $this->getId(),
    			'#name#' => ($this->getIsEnable()) ? $this->getName() : '<del>' . $this->getName() . '</del>',
    			'#eqLink#' => $this->getLinkToConfiguration(),
    			'#category#' => $this->getPrimaryCategory(),
    			'#background_color#' => $this->getBackgroundColor($version),
    			'#info#' => $info,
    			'#style#' => '',
    	);
    	if ($_version == 'dview' || $_version == 'mview') {
    		$object = $this->getObject();
    		$replace['#object_name#'] = (is_object($object)) ? '(' . $object->getName() . ')' : '';
    	} else {
    		$replace['#object_name#'] = '';
    	}
    	if (($_version == 'dview' || $_version == 'mview') && $this->getDisplay('doNotShowNameOnView') == 1) {
    		$replace['#name#'] = '';
    		$replace['#object_name#'] = (is_object($object)) ? $object->getName() : '';
    	}
    	if (($_version == 'mobile' || $_version == 'dashboard') && $this->getDisplay('doNotShowNameOnDashboard') == 1) {
    		$replace['#name#'] = '<br/>';
    		$replace['#object_name#'] = (is_object($object)) ? $object->getName() : '';
    	}
    	$parameters = $this->getDisplay('parameters');
    	if (is_array($parameters)) {
    		foreach ($parameters as $key => $value) {
    			$replace['#' . $key . '#'] = $value;
    		}
    	}
    
    	if (!isset(self::$_templateArray[$version])) {
    		self::$_templateArray[$version] = getTemplate('core', $version, 'eqLogic');
    	}
    	return template_replace($replace, self::$_templateArray[$version]);
    }  
    
    /**
     * Schedule status update of polling is turned on
     * @author Fdp1
     *
     */
    public function schedulePull(){
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
    }//end schedulePull function
    
    /**
     * Unchedule periodic status update
     * @author Fdp1
     *
     */
    public function unSchedulePull(){
    	//Before stopping polling, let's logoff en clear authCookie
    	$cache=cache::byKey('somfyAuthCookie::'.$this->getId());
    	$cachedCookie=$cache->getValue();
    	if(!($cachedCookie==='' || $cachedCookie===null || $cachedCookie=='false')){
    		$this->initSpBrowser();
    		$this->_spBrowser->doLogout();
    		cache::deleteBySearch('somfyAuthCookie::'.$this->getId());
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
    }//end unSchedulePull function

    /**
     * Schedule login test to check connection to the device
     * @author Fdp1
     *
     */
    public function scheduleIsRebooted(){
    	$cron = cron::byClassAndFunction('protexiom', 'isRebooted', array('protexiom_id' => intval($this->getId())));
    	if (!is_object($cron)) {
    		$cron=new cron();
    		$cron->setClass('protexiom');
    		$cron->setFunction('isRebooted');
    		$cron->setOption(array('protexiom_id' => intval($this->getId())));
    		$cron->setEnable(1);
    		//$cron->setDeamon(1);(1);
    		//$cron->setDeamonSleepTime(intval($this->getConfiguration('PollInt')));
    		$cron->setSchedule('* * * * *');
    		$cron->save();
    		log::add('protexiom', 'info', 'Scheduling protexiom isRebooted for equipement '.$this->name.'.', $this->name);
    	}
    }//end scheduleIsRebooted function

    /**
     * Unchedule login test
     * @author Fdp1
     *
     */
    public function unScheduleIsRebooted(){	 
    	$cron = cron::byClassAndFunction('protexiom', 'isRebooted', array('protexiom_id' => intval($this->getId())));
    	if (is_object($cron)) {
    		$cron->remove();
    		log::add('protexiom', 'info', 'Removing protexiom isRebooted schedule for equipement '.$this->name.'.', $this->name);
    	}
    	$cron = cron::byClassAndFunction('protexiom', 'isRebooted', array('protexiom_id' => intval($this->getId())));
    	if (is_object($cron)) {
    		echo '*!*!*!*!*!*!*IMPORTANT : unable to remove protexiom isRebooted scheduled task for device '.$this->name.'. You may have to manually remove it. *!*!*!*!*!*!*!*';
    		log::add('protexiom', 'error', 'Unable to remove protexiom isRebooted scheduled task for device '.$this->name.'. You may have to manually remove it.', $this->name);
    	}
    }//end unScheduleIsRebooted function
        
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

    /**
     * Workaround somfy session timeout bug
     * In case of unexpected error during web request, try to log off and on again, as somfy session timeout is not reset
     * at each request but is an absolute max session duration. They may call it a feature, I would call it a bug...
     * 
     * @author Fdp1
     * @return 0 in case of sucess, 1 otherwise
     */
    public function workaroundSomfySessionTimeoutBug() {
    	//Session timeout is only possible if a long session is maintained, meaning polling is on.
    	//Let's check if polling is on and if yes, try to start a new session
    	$cache=cache::byKey('somfyAuthCookie::'.$this->getId());
    	$cachedCookie=$cache->getValue();
    	if(!($cachedCookie==='' || $cachedCookie===null || $cachedCookie=='false')){
    		//The session was cached, so the timeout issue is possible. Starting a new session
    		$this->_spBrowser->doLogout();
    		cache::deleteBySearch('somfyAuthCookie::'.$this->getId());
    		log::add('protexiom', 'debug', 'Logout to workaround somfy session timeout bug on device '.$this->name.'.', $this->name);
    		if($this->_spBrowser->doLogin()){
    			//Login failed again. This may be due to the somfy needs_reboot bug
    			//Some hardware versions, freeze once or twice a day under heavy polling
    			//If this is the case, the somfy IP module needs et reboot (power off and on) before a new try
    			//Let's set the needs_reboot cmd to 1 so that the reboot can be launched from an external scenario
    			$needsRebootCmd=$this->getCmd(null, 'needs_reboot');
    			if (is_object($needsRebootCmd)){
    				log::add('protexiom', 'debug', 'Login failed while trying to workaround somfy session timeout bug on device '.$this->name.'. The protexiom may need a reboot', $this->name);
    				$needsRebootCmd->event("1");
    				$this->unSchedulePull();
    				$this->scheduleIsRebooted();
    			}else{
    				log::add('protexiom', 'error', 'It would appear that the protexiom may need a reboot, but I\'ve been unable to find needs_reboot cmd', $this->name);
    			}
    			return 1;
    	
    		}else{//Login OK
    			cache::set('somfyAuthCookie::'.$this->getId(), $this->_spBrowser->authCookie, $this->_SomfySessionTimeout);
    			return 0;
    		}
    	}else{
    		// Session timeout bug is not likely as polling seems to be turned off
    		return 1; 
    	}
    }//End function workaroundSomfySessionTimeoutBug


    /*     * **********************Getteur Setteur*************************** */

    /**
     * Update status from spBrowser
     * @author Fdp1
     */
    public function setStatusFromSpBrowser() {
    
    	$myError="";
    
    	if (!is_object($this->_spBrowser)) {
    		throw new Exception(__('Fatal error: setStatusFromSpBrowser called but $_spBrowser is not initialised.', __FILE__));
    	}
    	$status=$this->_spBrowser->getStatus();
    	foreach ($this->getCmd('info') as $cmd) {
    		if($cmd->getLogicalId() == 'needs_reboot'){
    			//Go to the next cmd, as needs_reboot is not retrieved from spBrowser
    			continue;
    		}else{
    			if($cmd->getSubType()=='binary'){
    				$newValue=(string)preg_match("/^o[k n]$/i", $status[$cmd->getConfiguration('somfyCmd')]);
    			}else{
    				$newValue=$status[$cmd->getConfiguration('somfyCmd')];
    			}
    			
    			if(!($cmd->execCmd(null, 2)==$newValue)){//Changed value
    				$cmd->event($newValue);
    			}// else, unchanged value. Let's keep the cached one
    		}
    	}
    	return;
    }//End function setStatusFromSpBrowser
    
    /**
     * get protexiom status from cache. If not availlable in cache, puul and cache it
     * @author Fdp1
     * @return array status
     */
    public function getStatusFromCache() {
    	$cache=cache::byKey('somfyStatus::'.$this->getId());
    	$status=$cache->getValue();
    	if(!($status==='' || $status===null || $status=='false')){
    		if(!$cache->hasExpired()){
    			log::add('protexiom', 'debug', 'Cached protexiom status found.', $this->name);
    			return json_decode($status, true); 
    		}
    	}
    	log::add('protexiom', 'debug', 'No (unexpired) cached protexiom status found. Let\'s pull status.', $this->name);
    	if ($this->pullStatus()){
    		//An error occured while pulling status
    		throw new Exception(__("An error occured while running $this->name action: $myError",__FILE__));
    	}else{
    		cache::set('somfyStatus::'.$this->getId(), json_encode($this->_spBrowser->getStatus()), $this->_SomfyStatusCacheLifetime);
    		log::add('protexiom', 'debug', 'Somfy protexiom status successfully pulled and cache', $this->name);
    		return $this->_spBrowser->getStatus();
    	}
    }//End function getStatusFromCache

}

class protexiomCmd extends cmd {
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
    	$protexiom=$this->getEqLogic();
    	$myError="";
    	log::add('protexiom', 'debug', "Running ".$this->name." CMD", $protexiom->getName());
  
    	if ($this->getType() == 'info') {
    		if($this->getLogicalId() == 'needs_reboot'){
    			//needs_reboot is only set in case of error, and does not need to be retrieved.
    			// Let's get it from the cache (if no cached, will return false anyway)
    			$mc = cache::byKey('cmd' . $this->getId());
    			return $mc->getValue();
    		}else{
    			if($this->getSubType()=='binary'){
    				return (string)preg_match("/^o[k n]$/i", $protexiom->getStatusFromCache()[$this->getConfiguration('somfyCmd')]);
    			}else{
    				return $protexiom->getStatusFromCache()[$this->getConfiguration('somfyCmd')];
    			}
    		}
      	}elseif ($this->getType() == 'action') {
      		$protexiom->initSpBrowser();
        	if($myError=$protexiom->_spBrowser->doAction($this->getConfiguration('somfyCmd'))){
    			//an error occured. May be the somfy session timeout bug
        		if(!$protexiom->workaroundSomfySessionTimeoutBug()){
        			$myError=$protexiom->_spBrowser->doAction($this->getConfiguration('somfyCmd'));
        		}
        	}
        	if($myError){
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
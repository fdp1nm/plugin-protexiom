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
    protected $_SomfySessionTimeout=5940;
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
    	log::add('protexiom', 'debug', '[*-'.$_options['protexiom_id'].'] '.getmypid().' Running protexiom pull '.date("Y-m-d H:i:s"), $_options['protexiom_id']);
        $protexiom = protexiom::byId($_options['protexiom_id']);
        if (is_object($protexiom)) {
        	$protexiom->initSpBrowser();
        	if (!($protexiom->_spBrowser->authCookie)){//Empty authCookie mean not logged in
        		if($myError=$protexiom->_spBrowser->doLogin()){
        			$protexiom->log('error', 'Login failed during scheduled pull. Pull aborted. Returned error was: '.$myError);
        			throw new Exception('Login failed during scheduled pull for the protexiom device '.$protexiom->name.'. Pull aborted. Returned error was: '.$myError);
        		}else{//Login OK
        			cache::set('somfyAuthCookie::'.$protexiom->getId(), $protexiom->_spBrowser->authCookie, $protexiom->_SomfySessionTimeout);
        			$protexiom->log('debug', 'Sucessfull login during scheduled pull. authCookie cached.');
        		}
        	}
        	$protexiom->pullStatus();
        } else {
            $protexiom->unSchedulePull();
            log::add('protexiom', 'error', '[*-'.$_options['protexiom_id'].'] '.getmypid().' Protexiom ID non trouvÃ© : ' . $_options['protexiom_id'] . '. Tache pull supprimÃ©', $_options['protexiom_id']);
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
    	log::add('protexiom', 'debug', '[*-'.$_options['protexiom_id'].'] '.getmypid().' Trying to login to check reboot', $_options['protexiom_id']);
    	$protexiom = protexiom::byId($_options['protexiom_id']);
    	if (is_object($protexiom)) {
    		$protexiom->initSpBrowser();
    		//At some points, some failed connection could happen and lead to erronous isRebooted schedule.
    		//In this case, a protexiom session could still be maintained elsewhere, and get our login test to fail.
    		//To avoid this, let's check if a session cookie is cached.
    		if($protexiom->_spBrowser->authCookie){
    			//A Session cookie has been found during initSpBrowser
    			//This is not supposed to happen, as cached cookie is removed during unSchedulePull before scheduleIsRebooted
    			//Let's force logoff before going on, to avoid a failed login test
    			$protexiom->log('error', 'Authcookie found during reboot check. This is not supposed to happen. Let\'s force logout to avoid false negative test.');
    			if($myError=$protexiom->_spBrowser->doLogout()){
    				$protexiom->log('error', 'Force logout failed during reboot check with error: '.$myError);
    			}
    		}
    		
    		if(!($myError=$protexiom->_spBrowser->doLogin())){
    			//Login OK
    			cache::set('somfyAuthCookie::'.$protexiom->getId(), $protexiom->_spBrowser->authCookie, $protexiom->_SomfySessionTimeout);
    			$protexiom->log('debug', 'Sucessfull login during reboot check. authCookie cached.');
    			$protexiom->pullStatus();
    			$protexiom->unScheduleIsRebooted();
    			if(filter_var($protexiom->getConfiguration('PollInt'), FILTER_VALIDATE_INT, array('options' => array('min_range' => 1)))){
    				$protexiom->schedulePull();
    			}
    			$needsRebootCmd=$protexiom->getCmd(null, 'needs_reboot');
    			if(is_object($needsRebootCmd)){
    				$needsRebootCmd->event("0");
    			}else{
    				$protexiom->log('error', 'Protexiom reboot went OK, but I\'ve been unable to reset needs_reboot cmd');
    				throw new Exception('Protexiom reboot went OK, but I\'ve been unable to reset needs_reboot cmd');
    			}
    		}	
    	}else{
    		$protexiom->unScheduleIsRebooted();
    		log::add('protexiom', 'error', '[*-'.$_options['protexiom_id'].'] '.getmypid().'Protexiom ID non trouvÃ© : ' . $_options['protexiom_id'] . '. Tache isRebooted supprimÃ©.', $_options['protexiom_id']);
    		throw new Exception('Protexiom ID non trouvÃ© : ' . $_options['protexiom_id'] . '. Tache isRebooted supprimÃ©.');
    	}
    	return;
    } //end isRebooted function

    /*     * **********************Instance methods************************** */

    /**
     * Add a message to the protexiom Jeedom log.
     * Prepend message with eqLogic Name, eqLogic ID and PID
     *
     * @author Fdp1
     * @param string $type log type (error, info, event, debug).
     * @param string $message message to add in the log.
     */
    public function log($_type = 'INFO', $_message)
    {
    	log::add('protexiom', $_type, '['.$this->name.'-'.$this->getId().'] '.getmypid().' '.$_message, $this->name);
    }//End log func
    
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
    		$this->log('debug', 'The folowing error occured while pulling status: '.$myError.'. This may be a session timeout issue. Let\'s workaround it');
    		if(!$myError=$this->workaroundSomfySessionTimeoutBug()){
    			$myError=$this->_spBrowser->pullStatus();
    		}
    	}else{
    		//Due to a somfy bug on some HW version, when a session is maintaned for a long time
    		//Somfy sometimes return an empty XML file. This never happends whith a fresh session, but can happen here. Let's check
    		$status=$this->_spBrowser->getStatus();
    		if($status['ALARM']==""){
    			//Empty XML file detected
    			//Let's log off and on again to workaround this somfy bug
    			$this->log('info', 'Log off and on again to workaround somfy empty XML bug');
    			
    			// Starting Jeewawa debug
    			if(!$myError=$this->_spBrowser->doLogout()){
    				$this->log('debug', 'Successfull logout while trying to workaround Empty XML file.');
    			}else{
    				$this->log('debug', 'Logout failed while trying to workaround Empty XML file. Returned error: '.$myError);
    			}
    			// Ending Jeewawa debug
    			
    			//$this->_spBrowser->doLogout();
    			if($myError=$this->_spBrowser->doLogin()){
    				$this->log('error', 'Login failed while trying to workaround somfy empty XML bug. Returned error: '.$myError);
    				//The session was cached. Let's delete the cached cookie as we just logged off
    				cache::deleteBySearch('somfyAuthCookie::'.$this->getId());
    				return 1;
    			}else{//Login OK
    				$myError=$this->_spBrowser->pullStatus();
    				if(!($this->getConfiguration('PollInt')=="" || $this->getConfiguration('PollInt')=="0")){
    					//Polling is on. Let's cache session cookie
    					cache::set('somfyAuthCookie::'.$this->getId(), $this->_spBrowser->authCookie, $this->_SomfySessionTimeout);
    				}else{//Polling is off
    					if($myError.=$this->_spBrowser->doLogout()){
    						$this->log('error', 'Logout failed after empty XML workaround, with polling off. Returned error: '.$myError);
    					}
    				}
    			}
    		}
    	}
    	if($myError){
    		//An error occured.
    		$this->log('error', " An error occured during status update: ".$myError);
    		return 1;
    	}else{
    		//Status pulled. Let's now refreh CMD
    		$this->log('info', 'Status refreshed');
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
    		$this->log('debug', 'Cached protexiom cookie found during initSpBrowser.');
    		$this->_spBrowser->authCookie=$cachedCookie;
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
	$protexiomCmd->setConfiguration('mobileLabel', 'On  A+B+C');
        $protexiomCmd->setType('action');
        $protexiomCmd->setSubType('other');
        $protexiomCmd->save();

        $protexiomCmd = new protexiomCmd();
        $protexiomCmd->setName(__('Marche A', __FILE__));
        $protexiomCmd->setLogicalId('zonea_on');
        $protexiomCmd->setEqLogic_id($this->id);
        $protexiomCmd->setConfiguration('somfyCmd', 'ZONEA_ON');
	$protexiomCmd->setConfiguration('mobileLabel', 'On A');
        $protexiomCmd->setType('action');
        $protexiomCmd->setSubType('other');
        $protexiomCmd->save();

        $protexiomCmd = new protexiomCmd();
        $protexiomCmd->setName(__('Marche B', __FILE__));
        $protexiomCmd->setLogicalId('zoneb_on');
        $protexiomCmd->setEqLogic_id($this->id);
        $protexiomCmd->setConfiguration('somfyCmd', 'ZONEB_ON');
	$protexiomCmd->setConfiguration('mobileLabel', 'On B');
        $protexiomCmd->setType('action');
        $protexiomCmd->setSubType('other');
        $protexiomCmd->save();

        $protexiomCmd = new protexiomCmd();
        $protexiomCmd->setName(__('Marche C', __FILE__));
        $protexiomCmd->setLogicalId('zonec_on');
        $protexiomCmd->setEqLogic_id($this->id);
        $protexiomCmd->setConfiguration('somfyCmd', 'ZONEC_ON');
	$protexiomCmd->setConfiguration('mobileLabel', 'On C');
        $protexiomCmd->setType('action');
        $protexiomCmd->setSubType('other');
        $protexiomCmd->save();

        $protexiomCmd = new protexiomCmd();
        $protexiomCmd->setName(__('Arret A+B+C', __FILE__));
        $protexiomCmd->setLogicalId('abc_off');
        $protexiomCmd->setEqLogic_id($this->id);
        $protexiomCmd->setConfiguration('somfyCmd', 'ALARME_OFF');
	$protexiomCmd->setConfiguration('mobileLabel', 'Off A+B+C');
        $protexiomCmd->setType('action');
        $protexiomCmd->setSubType('other');
        $protexiomCmd->save();
        
        // TODO Move light and shutters to a subdevice, and remove this comment block
        /* $protexiomCmd = new protexiomCmd();
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
        $protexiomCmd->save(); */
        
        $protexiomCmd = new protexiomCmd();
        $protexiomCmd->setName(__('Eff. defaut alarm', __FILE__));
        $protexiomCmd->setLogicalId('reset_alarm_err');
        $protexiomCmd->setEqLogic_id($this->id);
        $protexiomCmd->setConfiguration('somfyCmd', 'RESET_ALARM_ERR');
	$protexiomCmd->setConfiguration('mobileLabel', 'CLR alarm');
        $protexiomCmd->setType('action');
        $protexiomCmd->setSubType('other');
        $protexiomCmd->save();
        
        $protexiomCmd = new protexiomCmd();
        $protexiomCmd->setName(__('Eff. defaut piles', __FILE__));
        $protexiomCmd->setLogicalId('reset_battery_err');
        $protexiomCmd->setEqLogic_id($this->id);
        $protexiomCmd->setConfiguration('somfyCmd', 'RESET_BATTERY_ERR');
	$protexiomCmd->setConfiguration('mobileLabel', 'CLR bat');
        $protexiomCmd->setType('action');
        $protexiomCmd->setSubType('other');
        $protexiomCmd->save();
        
        $protexiomCmd = new protexiomCmd();
        $protexiomCmd->setName(__('Eff. defaut liaison', __FILE__));
        $protexiomCmd->setLogicalId('reset_link_err');
        $protexiomCmd->setEqLogic_id($this->id);
        $protexiomCmd->setConfiguration('somfyCmd', 'RESET_LINK_ERR');
	$protexiomCmd->setConfiguration('mobileLabel', 'CLR link');
        $protexiomCmd->setType('action');
        $protexiomCmd->setSubType('other');
        $protexiomCmd->save();
        
        // Info CMD
        
        $protexiomCmd = new protexiomCmd();
        $protexiomCmd->setName(__('Zone A', __FILE__));
        $protexiomCmd->setLogicalId('zone_a');
        $protexiomCmd->setEqLogic_id($this->id);
        $protexiomCmd->setConfiguration('somfyCmd', 'ZONE_A');
	$protexiomCmd->setConfiguration('mobileLabel', 'Zone A');
        $protexiomCmd->setUnite('');
        $protexiomCmd->setType('info');
        $protexiomCmd->setSubType('binary');
        $protexiomCmd->setTemplate('dashboard', 'protexiomZone');
        $protexiomCmd->setTemplate('mobile', 'protexiomZone');
        $protexiomCmd->save();
         
        $protexiomCmd = new protexiomCmd();
        $protexiomCmd->setName(__('Zone B', __FILE__));
        $protexiomCmd->setLogicalId('zone_b');
        $protexiomCmd->setEqLogic_id($this->id);
        $protexiomCmd->setConfiguration('somfyCmd', 'ZONE_B');
	$protexiomCmd->setConfiguration('mobileLabel', 'Zone B');
        $protexiomCmd->setUnite('');
        $protexiomCmd->setType('info');
        $protexiomCmd->setSubType('binary');
        $protexiomCmd->setTemplate('dashboard', 'protexiomZone');
        $protexiomCmd->setTemplate('mobile', 'protexiomZone');
        $protexiomCmd->save();
         
        $protexiomCmd = new protexiomCmd();
        $protexiomCmd->setName(__('Zone C', __FILE__));
        $protexiomCmd->setLogicalId('zone_c');
        $protexiomCmd->setEqLogic_id($this->id);
        $protexiomCmd->setConfiguration('somfyCmd', 'ZONE_C');
	$protexiomCmd->setConfiguration('mobileLabel', 'Zone C');
        $protexiomCmd->setUnite('');
        $protexiomCmd->setType('info');
        $protexiomCmd->setSubType('binary');
        $protexiomCmd->setTemplate('dashboard', 'protexiomZone');
        $protexiomCmd->setTemplate('mobile', 'protexiomZone');
        $protexiomCmd->save();
        
        $protexiomCmd = new protexiomCmd();
        $protexiomCmd->setName(__('Liaison', __FILE__));
        $protexiomCmd->setLogicalId('link');
        $protexiomCmd->setEqLogic_id($this->id);
        $protexiomCmd->setConfiguration('somfyCmd', 'LINK');
	$protexiomCmd->setConfiguration('mobileLabel', 'Liaison');
        $protexiomCmd->setUnite('');
        $protexiomCmd->setType('info');
        $protexiomCmd->setSubType('binary');
        $protexiomCmd->setTemplate('dashboard', 'protexiomLink');
        $protexiomCmd->setTemplate('mobile', 'protexiomLink');
        $protexiomCmd->save();
        
        $protexiomCmd = new protexiomCmd();
        $protexiomCmd->setName(__('Portes', __FILE__));
        $protexiomCmd->setLogicalId('door');
        $protexiomCmd->setEqLogic_id($this->id);
        $protexiomCmd->setConfiguration('somfyCmd', 'DOOR');
	$protexiomCmd->setConfiguration('mobileLabel', 'Portes');
        $protexiomCmd->setUnite('');
        $protexiomCmd->setType('info');
        $protexiomCmd->setSubType('binary');
        $protexiomCmd->setTemplate('dashboard', 'protexiomDoor');
        $protexiomCmd->setTemplate('mobile', 'protexiomDoor');
        $protexiomCmd->save();
        
        $protexiomCmd = new protexiomCmd();
        $protexiomCmd->setName(__('Alarme', __FILE__));
        $protexiomCmd->setLogicalId('alarm');
        $protexiomCmd->setEqLogic_id($this->id);
        $protexiomCmd->setConfiguration('somfyCmd', 'ALARM');
	$protexiomCmd->setConfiguration('mobileLabel', 'Alarme');
        $protexiomCmd->setUnite('');
        $protexiomCmd->setType('info');
        $protexiomCmd->setSubType('string');
        $protexiomCmd->setTemplate('dashboard', 'protexiomAlarm');
        $protexiomCmd->setTemplate('mobile', 'protexiomAlarm');
        $protexiomCmd->save();
        
        $protexiomCmd = new protexiomCmd();
        $protexiomCmd->setName(__('Sabotage', __FILE__));
        $protexiomCmd->setLogicalId('tampered');
        $protexiomCmd->setEqLogic_id($this->id);
        $protexiomCmd->setConfiguration('somfyCmd', 'TAMPERED');
	$protexiomCmd->setConfiguration('mobileLabel', 'Sabotage');
        $protexiomCmd->setUnite('');
        $protexiomCmd->setType('info');
        $protexiomCmd->setSubType('binary');
        $protexiomCmd->setTemplate('dashboard', 'protexiomTampered');
        $protexiomCmd->setTemplate('mobile', 'protexiomTampered');
        $protexiomCmd->save();
        
        $protexiomCmd = new protexiomCmd();
        $protexiomCmd->setName(__('Liaison GSM', __FILE__));
        $protexiomCmd->setLogicalId('gsm_link');
        $protexiomCmd->setEqLogic_id($this->id);
        $protexiomCmd->setConfiguration('somfyCmd', 'GSM_LINK');
	$protexiomCmd->setConfiguration('mobileLabel', 'Liaison GSM');
        $protexiomCmd->setUnite('');
        $protexiomCmd->setType('info');
        $protexiomCmd->setSubType('string');
        $protexiomCmd->setTemplate('dashboard', 'protexiomDefault');
        $protexiomCmd->setTemplate('mobile', 'protexiomDefault');
        $protexiomCmd->save();
        
        $protexiomCmd = new protexiomCmd();
        $protexiomCmd->setName(__('Récéption GSM', __FILE__));
        $protexiomCmd->setLogicalId('gsm_signal');
        $protexiomCmd->setEqLogic_id($this->id);
        $protexiomCmd->setConfiguration('somfyCmd', 'GSM_SIGNAL');
	$protexiomCmd->setConfiguration('mobileLabel', 'Récéption GSM');
        $protexiomCmd->setUnite('');
        $protexiomCmd->setType('info');
        $protexiomCmd->setSubType('numeric');
        $protexiomCmd->setTemplate('dashboard', 'protexiomGsmSignal');
        $protexiomCmd->setTemplate('mobile', 'protexiomGsmSignal');
        $protexiomCmd->save();
        
        $protexiomCmd = new protexiomCmd();
        $protexiomCmd->setName(__('Opérateur GSM', __FILE__));
        $protexiomCmd->setLogicalId('gsm_operator');
        $protexiomCmd->setEqLogic_id($this->id);
        $protexiomCmd->setConfiguration('somfyCmd', 'GSM_OPERATOR');
	$protexiomCmd->setConfiguration('mobileLabel', 'Opérateur GSM');
        $protexiomCmd->setUnite('');
        $protexiomCmd->setType('info');
        $protexiomCmd->setSubType('string');
        $protexiomCmd->setTemplate('dashboard', 'protexiomDefault');
        $protexiomCmd->setTemplate('mobile', 'protexiomDefault');
        $protexiomCmd->save();
        
        $protexiomCmd = new protexiomCmd();
        $protexiomCmd->setName(__('Camera', __FILE__));
        $protexiomCmd->setLogicalId('camera');
        $protexiomCmd->setEqLogic_id($this->id);
        $protexiomCmd->setConfiguration('somfyCmd', 'CAMERA');
	$protexiomCmd->setConfiguration('mobileLabel', 'Camera');
        $protexiomCmd->setUnite('');
        $protexiomCmd->setType('info');
        $protexiomCmd->setSubType('binary');
        $protexiomCmd->setTemplate('dashboard', 'protexiomCamera');
        $protexiomCmd->setTemplate('mobile', 'protexiomCamera');
        $protexiomCmd->save();
        
        $protexiomCmd = new protexiomCmd();
        $protexiomCmd->setName(__('Redémarrage requis', __FILE__));
        $protexiomCmd->setLogicalId('needs_reboot');
        $protexiomCmd->setEqLogic_id($this->id);
        //$protexiomCmd->setConfiguration('somfyCmd', 'needs_reboot');
	$protexiomCmd->setConfiguration('mobileLabel', 'Redémarrage requis');
        $protexiomCmd->setUnite('');
        $protexiomCmd->setType('info');
        $protexiomCmd->setSubType('binary');
        $protexiomCmd->setIsVisible(0);
        $protexiomCmd->setTemplate('dashboard', 'protexiomNeedsReboot');
        $protexiomCmd->setTemplate('mobile', 'protexiomNeedsReboot');
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
    	$this->unScheduleIsRebooted();
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
    			$this->log('error', $myError);
    		
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
    				$this->log('info', 'HwVersion set to '.$myProtexiom->getHwVersion());
    			}
    			// Let's initialise the needs_reboot cmd to 0
    			$needsRebootCmd=$this->getCmd(null, 'needs_reboot');
    			if(is_object($needsRebootCmd)){
    				$needsRebootCmd->event("0");
    			}else{
    				$this->log('error', 'Unable to reset needs_reboot cmd while saving protexiom eqLogic');
    			}
    			
    			// Let's initialize status
    			$this->pullStatus();
    			
    			//And finally, Let's schedule pull if polling is on
    			if(filter_var($this->getConfiguration('PollInt'), FILTER_VALIDATE_INT, array('options' => array('min_range' => 1)))){
    				$this->schedulePull();
    				// If polling is on, we can set every info CMD to setEventOnly
    				// this way, cmd cache TTL is not taken into account, and polling is the only way to update an info cmd
    				foreach ($this->getCmd('info') as $cmd) {
    						$cmd->setEventOnly(1);
    						$cmd->save();
    				}
    			}else{// Polling is off
    				// As no event will be thrown by polling, let's let jeedom refresh collect info CMD when cache is expired
    				foreach ($this->getCmd('info') as $cmd) {
    					if($cmd->getLogicalId() == 'needs_reboot'){
    						//needs_reboot state is only changed by event wether polling is on or of
    						$cmd->setEventOnly(1);
    					}else{
    						$cmd->setEventOnly(0);
    					}
    					$cmd->save();
    				}
    			}
    			
    		}
    	}//else{//eqLogic disabled
    	
    	//Let's set specific CMD configuration
    	foreach ($this->getCmd('info') as $cmd) {
    		if($cmd->getLogicalId() == 'gsm_signal'){
    			$cmd->setConfiguration('maxValue', 5);
    			$cmd->save();
    		}
    		// TODO Move light and shutters to a subdevice, and remove this comment block
    		/* }elseif($cmd->getLogicalId() == 'light_on'){
    			$protexiomCmd->setConfiguration('mobileTag', 'Lum. On');
    		}elseif($cmd->getLogicalId() == 'light_off'){
    			$protexiomCmd->setConfiguration('mobileTag', 'Lum. Off');
    		}elseif($cmd->getLogicalId() == 'shutter_up'){
    			$protexiomCmd->setConfiguration('mobileTag', 'Volets Ouv');
    		}elseif($cmd->getLogicalId() == 'shutter_down'){
    			$protexiomCmd->setConfiguration('mobileTag', 'Volets Ferm');
    		}elseif($cmd->getLogicalId() == 'shutter_stop'){
    			$protexiomCmd->setConfiguration('mobileTag', 'Volets stop');
    		}*/
    		
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
    	$this->unScheduleIsRebooted();
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
    		throw new Exception(__('La version demandée ne peut pas être vide (mobile, dashboard ou scénario)', __FILE__));
    	}
    	if (!$this->hasRight('r')) {
    		return '';
    	}
    	$hasOnlyEventOnly = $this->hasOnlyEventOnlyCmd();
    	if($hasOnlyEventOnly){
    		$sql = 'SELECT `value` FROM cache
           WHERE `key`="widgetHtml' . $_version . $this->getId().'"';
    		$result = DB::Prepare($sql, array(), DB::FETCH_TYPE_ROW);
    		if ($result['value'] != '') {
    			return $result['value'];
    		}
    	}
    
    	$version = jeedom::versionAlias($_version);

    	$replace = array(
    			'#id#' => $this->getId(),
    			'#name#' => $this->getName(),
    			'#eqLink#' => $this->getLinkToConfiguration(),
    			'#category#' => $this->getPrimaryCategory(),
    			'#background_color#' => $this->getBackgroundColor($version),
    			'#style#' => '',
    			'#max_width#' => '650px',
    			'#logicalId#' => $this->getLogicalId(),
                '#battery#' => $this->getConfiguration('batteryStatus', -2),
                '#batteryDatetime#' => $this->getConfiguration('batteryStatusDatetime', __('inconnue', __FILE__)),
    	);
    	
    	if ($this->getIsEnable()) {
    		foreach ($this->getCmd() as $cmd) {
    			if($cmd->getIsVisible()){
    				$replace['#'.$cmd->getLogicalId().'#'] = $cmd->toHtml($_version);
    			}else{
    				$replace['#'.$cmd->getLogicalId().'#'] = "";
    			}
    		}
    	}
    	
    	if ($_version == 'dview' || $_version == 'mview') {
    		$object = $this->getObject();
    		$replace['#object_name#'] = (is_object($object)) ? '(' . $object->getName() . ')' : '';
    	} else {
    		$replace['#object_name#'] = '';
    	}
    	if (($_version == 'dview' || $_version == 'mview') && $this->getDisplay('doNotShowNameOnView') == 1) {
    		$replace['#name#'] = '';
    	}
    	if (($_version == 'mobile' || $_version == 'dashboard') && $this->getDisplay('doNotShowNameOnDashboard') == 1) {
    		$replace['#name#'] = '';
    	}
    	$parameters = $this->getDisplay('parameters');
    	if (is_array($parameters)) {
    		foreach ($parameters as $key => $value) {
    			$replace['#' . $key . '#'] = $value;
    		}
    	}
    	
        self::$_templateArray[$version] = getTemplate('core', $version, 'eqLogic','protexiom');
    	
    	$html = template_replace($replace, self::$_templateArray[$version]);
    	if($hasOnlyEventOnly){
    		cache::set('widgetHtml' . $_version . $this->getId(), $html, 0);
    	}
    	return $html;
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
    		$cron->setDeamon(1);
    		$cron->setDeamonSleepTime(intval($this->getConfiguration('PollInt')));
    		$cron->setSchedule('* * * * *');
    		$cron->setTimeout(2);
    		$cron->save();
    		$this->log('info', 'Scheduling protexiom pull.');
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
    		
    		// Starting Jeewawa debug
    		$this->log('debug', 'Cached cookie found  while unscheduling. Trying to logoff');
    		$this->initSpBrowser();
    		if(!$myError=$this->_spBrowser->doLogout()){
    			$this->log('debug', 'Successfull logout while unscheduling.');
    		}else{
    			$this->log('debug', 'Logout failed while unscheduling. Returned error: '.$myError);
    		}
    		// Ending Jeewawa debug
    		
    		//$this->initSpBrowser();
    		//$this->_spBrowser->doLogout();
    		cache::deleteBySearch('somfyAuthCookie::'.$this->getId());
    		$this->log('info', 'Removing cached cookie while unscheduling.');
    	}
    	
		$cron = cron::byClassAndFunction('protexiom', 'pull', array('protexiom_id' => intval($this->getId())));
    	if (is_object($cron)) {
    		$cron->remove();
    		$this->log('info', 'Removing protexiom pull schedule.');
    	}
		$cron = cron::byClassAndFunction('protexiom', 'pull', array('protexiom_id' => intval($this->getId())));
    	if (is_object($cron)) {
			echo '*!*!*!*!*!*!*IMPORTANT : unable to remove protexiom pull daemon for device '.$this->name.'. You may have to manually remove it. *!*!*!*!*!*!*!*';
    		$this->log('error', 'Unable to remove protexiom pull daemon. You may have to manually remove it.');
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
    		$cron->setSchedule('* * * * *');
    		$cron->save();
    		$this->log('info', 'Scheduling protexiom isRebooted.');
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
    		$cron->remove(false);
    		$this->log('info', 'Removing protexiom isRebooted schedule.');
    	}
    	$cron = cron::byClassAndFunction('protexiom', 'isRebooted', array('protexiom_id' => intval($this->getId())));
    	if (is_object($cron)) {
    		echo '*!*!*!*!*!*!*IMPORTANT : unable to remove protexiom isRebooted scheduled task for device '.$this->name.'. You may have to manually remove it. *!*!*!*!*!*!*!*';
    		$this->log('error', 'Unable to remove protexiom isRebooted scheduled task. You may have to manually remove it.');
    	}
    }//end unScheduleIsRebooted function

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
    		
    		// Starting Jeewawa debug
    		if(!$myError=$this->_spBrowser->doLogout()){
    			$this->log('debug', 'Successfull logout for workaroundSomfySessionTimeoutBug.');
    		}else{
    			$this->log('debug', 'Logout failed for workaroundSomfySessionTimeoutBug. Returned error: '.$myError);
    		}
    		// Ending Jeewawa debug
    		//$this->_spBrowser->doLogout();
    		cache::deleteBySearch('somfyAuthCookie::'.$this->getId());
    		$this->log('debug', 'Logged out to workaround somfy session timeout bug.');
    		if($myError=$this->_spBrowser->doLogin()){
    			//Login failed again. This may be due to the somfy needs_reboot bug
    			//Some hardware versions, freeze once or twice a day under heavy polling
    			//If this is the case, the somfy IP module needs et reboot (power off and on) before a new try
    			//Let's set the needs_reboot cmd to 1 so that the reboot can be launched from an external scenario
    			$needsRebootCmd=$this->getCmd(null, 'needs_reboot');
    			if (is_object($needsRebootCmd)){
    				$this->log('debug', 'Login failed while trying to workaround somfy session timeout bug with error '.$myError.'. The protexiom may need a reboot');
    				$needsRebootCmd->event("1");
    				$this->unSchedulePull();
    				$this->scheduleIsRebooted();
    			}else{
    				$this->log('error', 'It would appear that the protexiom may need a reboot, but I\'ve been unable to find needs_reboot cmd');
    			}
    			return 1;
    	
    		}else{//Login OK
    			$this->log('debug', 'Login successfull for workaroundSomfySessionTimeoutBug. Caching session cookie.');
    			cache::set('somfyAuthCookie::'.$this->getId(), $this->_spBrowser->authCookie, $this->_SomfySessionTimeout);
    			return 0;
    		}
    	}else{
    		// Session timeout bug is not likely as polling seems to be turned off
		$this->log('error', 'Session timeout bug is not likely as polling seems to be turned off (at least, no cookie as been found).');
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
    				//We just ran execCmd, wich set $_collectDate
    				//Event() will check if $_collectDate is old, and reject the event if it's the case.
    				//Let's clear it before throwing the event
    				$cmd->setCollectDate('');
    				$cmd->event($newValue);
    			}// else, unchanged value. Let's keep the cached one
    		}
    	}
        // Battery level is a specific info handle by Jeedom in a specific way.
        if($status['BATTERY']=="ok"){
            $newValue='100';
        }else{
            $newValue='10';
        }
        if(!($this->getConfiguration('batteryStatus')==$newValue)){//Changed value
    		$this->log('debug', 'Setting new battery value to '.$newValue);
            $this->batteryStatus($newValue);
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
    			$this->log('debug', 'Cached protexiom status found.');
    			return json_decode($status, true); 
    		}
    	}
    	$this->log('debug', 'No (unexpired) cached protexiom status found. Let\'s pull status.');
    	if ($myError=$this->pullStatus()){
    		//An error occured while pulling status
    		$this->log('debug', 'An error occured while pulling status: '.$myError);
    		throw new Exception(__("An error occured while pulling status: $myError",__FILE__));
    	}else{
    		cache::set('somfyStatus::'.$this->getId(), json_encode($this->_spBrowser->getStatus()), $this->_SomfyStatusCacheLifetime);
    		$this->log('debug', 'Somfy protexiom status successfully pulled and cache');
    		return $this->_spBrowser->getStatus();
    	}
    }//End function getStatusFromCache

}

class protexiomCmd extends cmd {
    /*     * *************************Attributs****************************** */
	private static $_templateArray = array();

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
    	$protexiom->log('debug', "Running ".$this->name." CMD");
  
    	if ($this->getType() == 'info') {
    		if($this->getLogicalId() == 'needs_reboot'){
    			//needs_reboot is only set in case of error, and does not need to be retrieved.
    			// Let's get it from the cache (if no cached, will return false anyway)
    			// TODO shoudn't we exec instead of getting from cache?
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
        		$protexiom->log('debug', "The folowing error happened while running ".$this->name." CMD: ".$myError.". Let's workaroundSomfySessionTimeoutBug");
        		if(!$protexiom->workaroundSomfySessionTimeoutBug()){
        			$myError=$protexiom->_spBrowser->doAction($this->getConfiguration('somfyCmd'));
        		}
        	}
        	if($myError){
    			$protexiom->log('error', "An error occured while running $this->name action: $myError");
				throw new Exception(__("An error occured while running $this->name action: $myError",__FILE__));
        	}else{
    			//Command successfull
        		$protexiom->setStatusFromSpBrowser();
        		return;
        	}
      	}else{
        	//unknown cmd type
      		$protexiom->log('error', "$this->getType(): Unknown command type for $this->name");
        	throw new Exception(__("$this->getType(): Unknown command type for $this->name",__FILE__));
      	}
    		
    }

    /**
     * Return tile HTML code for widget
     * Based on the standard jeedom toHtml function (revsion efa15cb).
     * Only modified to get a spcific device nome on mobile widget
     * @param array $_options
     * @author Fdp1
     */
    public function toHtml($_version = 'dashboard', $options = '', $_cmdColor = null, $_cache = 2) {
    	$version = jeedom::versionAlias($_version);
    	$html = '';
    	$template_name = 'cmd.' . $this->getType() . '.' . $this->getSubType() . '.' . $this->getTemplate($version, 'default');
    	$template = '';
    	if (!isset(self::$_templateArray[$version . '::' . $template_name])) {
    		if ($this->getTemplate($version, 'default') != 'default') {
    			if (config::byKey('active', 'widget') == 1) {
    				$template = getTemplate('core', $version, $template_name, 'widget');
    			}
    			if ($template == '') {
    				foreach (plugin::listPlugin(true) as $plugin) {
    					$template = getTemplate('core', $version, $template_name, $plugin->getId());
    					if ($template != '') {
    						break;
    					}
    				}
    			}
    			if ($template == '' && config::byKey('active', 'widget') == 1 && config::byKey('market::autoInstallMissingWidget') == 1) {
    				try {
    					$market = market::byLogicalId(str_replace('cmd.', '', $version . '.' . $template_name));
    					if (is_object($market)) {
    						$market->install();
    						$template = getTemplate('core', $version, $template_name, 'widget');
    					}
    				} catch (Exception $e) {
    					$this->setTemplate($version, 'default');
    					$this->save();
    				}
    			}
    			if ($template == '') {
    				$template_name = 'cmd.' . $this->getType() . '.' . $this->getSubType() . '.default';
    				$template = getTemplate('core', $version, $template_name);
    			}
    		} else {
    			$template = getTemplate('core', $version, $template_name);
    		}
    		self::$_templateArray[$version . '::' . $template_name] = $template;
    	} else {
    		$template = self::$_templateArray[$version . '::' . $template_name];
    	}
    	$replace = array(
    			'#id#' => $this->getId(),
    			//'#name#' => ($this->getDisplay('icon') != '') ? $this->getDisplay('icon') : $this->getName(),
    			'#name#' => ($this->getDisplay('icon') != '') ? $this->getDisplay('icon') : ($_version == 'mobile') ? $this->getConfiguration('mobileLabel') : $this->getName(), //modified by fdp1
    			'#history#' => '',
    			'#displayHistory#' => 'display : none;',
    			'#unite#' => $this->getUnite(),
    			'#minValue#' => $this->getConfiguration('minValue', 0),
    			'#maxValue#' => $this->getConfiguration('maxValue', 100),
    			'#logicalId#' => $this->getLogicalId(),
    	);
    	if ($_cmdColor == null && $version != 'scenario') {
    		$eqLogic = $this->getEqLogic();
    		$vcolor = ($version == 'mobile') ? 'mcmdColor' : 'cmdColor';
    		if ($eqLogic->getPrimaryCategory() == '') {
    			$replace['#cmdColor#'] = jeedom::getConfiguration('eqLogic:category:default:' . $vcolor);
    		} else {
    			$replace['#cmdColor#'] = jeedom::getConfiguration('eqLogic:category:' . $eqLogic->getPrimaryCategory() . ':' . $vcolor);
    		}
    	} else {
    		$replace['#cmdColor#'] = $_cmdColor;
    	}
    	if ($this->getDisplay('doNotShowNameOnView') == 1 && ($_version == 'dview' || $_version == 'mview')) {
    		$replace['#name#'] = '';
    	}else if ($this->getDisplay('doNotShowNameOnDashboard') == 1 && ($_version == 'mobile' || $_version == 'dashboard')) {
    		$replace['#name#'] = '';
    	}
    	if ($this->getType() == 'info') {
    		$replace['#state#'] = '';
    		$replace['#tendance#'] = '';
    		$replace['#state#'] = $this->execCmd(null, $_cache);
    		if ($this->getSubType() == 'binary' && $this->getDisplay('invertBinary') == 1) {
    			$replace['#state#'] = ($replace['#state#'] == 1) ? 0 : 1;
    		}
    		$replace['#collectDate#'] = $this->getCollectDate();
    		if ($this->getIsHistorized() == 1) {
    			$replace['#history#'] = 'history cursor';
    
    
    
    			if (config::byKey('displayStatsWidget') == 1 && strpos($template, '#displayHistory#') !== false) {
    				$showStat  = true;
    				if ($this->getDisplay('doNotShowStatOnDashboard') == 1 && $_version == 'dashboard') {
    					$showStat = false;
    				}
    				if ($this->getDisplay('doNotShowStatOnView') == 1 && ($_version == 'dview' || $_version == 'mview')) {
    					$showStat = false;
    				}
    				if ($this->getDisplay('doNotShowStatOnMobile') == 1 && $_version == 'mobile') {
    					$showStat = false;
    				}
    				if($showStat){
    					$startHist = date('Y-m-d H:i:s', strtotime(date('Y-m-d H:i:s') . ' -' . config::byKey('historyCalculPeriod') . ' hour'));
    					$replace['#displayHistory#'] = '';
    					$historyStatistique = $this->getStatistique($startHist, date('Y-m-d H:i:s'));
    					$replace['#averageHistoryValue#'] = round($historyStatistique['avg'], 1);
    					$replace['#minHistoryValue#'] = round($historyStatistique['min'], 1);
    					$replace['#maxHistoryValue#'] = round($historyStatistique['max'], 1);
    					$startHist = date('Y-m-d H:i:s', strtotime(date('Y-m-d H:i:s') . ' -' . config::byKey('historyCalculTendance') . ' hour'));
    					$tendance = $this->getTendance($startHist, date('Y-m-d H:i:s'));
    					if ($tendance > config::byKey('historyCalculTendanceThresholddMax')) {
    						$replace['#tendance#'] = 'fa fa-arrow-up';
    					} else if ($tendance < config::byKey('historyCalculTendanceThresholddMin')) {
    						$replace['#tendance#'] = 'fa fa-arrow-down';
    					} else {
    						$replace['#tendance#'] = 'fa fa-minus';
    					}
    				}
    			}
    		}
    		$parameters = $this->getDisplay('parameters');
    		if (is_array($parameters)) {
    			foreach ($parameters as $key => $value) {
    				$replace['#' . $key . '#'] = $value;
    			}
    		}
    		return template_replace($replace, $template);
    	} else {
    		$cmdValue = $this->getCmdValue();
    		if (is_object($cmdValue) && $cmdValue->getType() == 'info') {
    			$replace['#state#'] = $cmdValue->execCmd(null, 2);
    			$replace['#valueName#'] = $cmdValue->getName();
    			$replace['#unite#'] = $cmdValue->getUnite();
    		} else {
    			$replace['#state#'] = ($this->getLastValue() != null) ? $this->getLastValue() : '';
    			$replace['#valueName#'] = $this->getName();
    			$replace['#unite#'] = $this->getUnite();
    		}
    		$parameters = $this->getDisplay('parameters');
    		if (is_array($parameters)) {
    			foreach ($parameters as $key => $value) {
    				$replace['#' . $key . '#'] = $value;
    			}
    		}
    		$html .= template_replace($replace, $template);
    		if (trim($html) == '') {
    			return $html;
    		}
    		if ($options != '') {
    			$options = self::cmdToHumanReadable($options);
    			if (is_json($options)) {
    				$options = json_decode($options, true);
    			}
    			if (is_array($options)) {
    				foreach ($options as $key => $value) {
    					$replace['#' . $key . '#'] = $value;
    				}
    				$html = template_replace($replace, $html);
    			}
    		}
    		return $html;
    	}
    }

    /*     * **********************Getteur Setteur*************************** */
}

?>
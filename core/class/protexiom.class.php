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

include_file('core', 'protexiom_ctrl', 'class', 'protexiom');
include_file('core', 'protexiom_elmt', 'class', 'protexiom');

class protexiom extends eqLogic {
    /*     * *************************Attributs****************************** */

    protected $_UpdateDate = '';
    protected $_HwVersion = '';
    protected $_SomfyAuthCookie = '';
    protected $_SomfyHost = '';
    protected $_SomfyPort = '';
    protected $_WebProxyHost = '';
    protected $_WebProxyPort = '';
    public $_SomfySessionCookieTTL=0;//0 means never expires
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
        			cache::set('somfyAuthCookie::'.$protexiom->getId(), $protexiom->_spBrowser->authCookie, $protexiom->_SomfySessionCookieTTL);
        			$protexiom->log('debug', 'Sucessfull login during scheduled pull. authCookie cached.');
        		}
        	}
        	$protexiom->pullStatus();
        } else {
            log::add('protexiom', 'error', '[*-'.$_options['protexiom_id'].'] '.getmypid().' Protexiom ID non trouvÃ© : ' . $_options['protexiom_id'] . '. Tache pull supprimÃ©', $_options['protexiom_id']);
            throw new Exception('Protexiom ID non trouvÃ© : ' . $_options['protexiom_id'] . '. Tache pull supprimÃ©');
            $protexiom->unSchedulePull(false);
        }
    	return;
    } //end pull function 


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
    public function pullStatus($forceElementUpdate=false) {
    	 
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
    			
    			if(!$myError=$this->_spBrowser->doLogout()){
    				$this->log('debug', 'Successfull logout while trying to workaround Empty XML file. Deleting session cookie');
    				cache::deleteBySearch('somfyAuthCookie::'.$this->getId());
    			}else{
    				$this->log('debug', 'Logout failed while trying to workaround Empty XML file. Returned error: '.$myError);
    			}

    			if($myError=$this->_spBrowser->doLogin()){
    				$this->log('error', 'Login failed while trying to workaround somfy empty XML bug. Returned error: '.$myError);
    				return 1;
    			}else{//Login OK
    				$myError=$this->_spBrowser->pullStatus();
    				if(!($this->getConfiguration('PollInt')=="" || $this->getConfiguration('PollInt')=="0")){
    					//Polling is on. Let's cache session cookie
    					cache::set('somfyAuthCookie::'.$this->getId(), $this->_spBrowser->authCookie, $this->_SomfySessionCookieTTL);
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
    		$this->log('debug', 'Status refreshed');
    		$this->setStatusFromSpBrowser($forceElementUpdate);
    		$this->log('debug', 'Pull status... Done');
    		return 0;
    	}
    }//End function pullStatus()
    
    /**
     * Pull Elements from the protexiom
     * @author Fdp1
     * @return 0 in case of sucess, 1 otherwise
     */
    public function pullElements() {
    
    	$myError="";
    	if (!is_object($this->_spBrowser)) {
    		$this->initSpBrowser();
    	}
    	if($myError=$this->_spBrowser->pullElements()){
    		//An error occured while pulling status. This may be a session timeout issue.
    		$this->log('debug', 'The folowing error occured while pulling elements: '.$myError.'. This may be a session timeout issue. Let\'s workaround it');
    		if(!$myError=$this->workaroundSomfySessionTimeoutBug()){
    			$myError=$this->_spBrowser->pullElements();
    		}
    	}
    	if($myError){
    		//An error occured.
    		$this->log('error', " An error occured during elements update: ".$myError);
    		return 1;
    	}else{
    		//Elements pulled. Let's now refreh CMD
    		$this->log('debug', 'Elements refreshed');
    		$this->setElementsFromSpBrowser();
    		$this->log('debug', 'Pull elements... Done');
    		return 0;
    	}
    }//End function pullElements()
    
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
    	
    }//End preUpdate func
    
    /**
     * Called before inserting a plugin device when creating it, before the first configuration
     * Standard Jeedom function
     * @author Fdp1
     *
     */
    public function preInsert() {
    	$this->setCategory('security', 1);
    }//End preInsert func //End postInsert func

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
        $protexiomCmd->setDisplay('icon', '<i class="fa fa-lock"></i>');
        $protexiomCmd->setTemplate('dashboard', 'protexiomDefault');
        $protexiomCmd->setTemplate('mobile', 'protexiomDefault');
        $protexiomCmd->save();

        $protexiomCmd = new protexiomCmd();
        $protexiomCmd->setName(__('Marche A', __FILE__));
        $protexiomCmd->setLogicalId('zonea_on');
        $protexiomCmd->setEqLogic_id($this->id);
        $protexiomCmd->setConfiguration('somfyCmd', 'ZONEA_ON');
        $protexiomCmd->setConfiguration('mobileLabel', 'On A');
        $protexiomCmd->setType('action');
        $protexiomCmd->setSubType('other');
        $protexiomCmd->setDisplay('icon', '<i class="fa fa-lock"></i>');
        $protexiomCmd->setTemplate('dashboard', 'protexiomDefault');
        $protexiomCmd->setTemplate('mobile', 'protexiomDefault');
        $protexiomCmd->save();

        $protexiomCmd = new protexiomCmd();
        $protexiomCmd->setName(__('Marche B', __FILE__));
        $protexiomCmd->setLogicalId('zoneb_on');
        $protexiomCmd->setEqLogic_id($this->id);
        $protexiomCmd->setConfiguration('somfyCmd', 'ZONEB_ON');
        $protexiomCmd->setConfiguration('mobileLabel', 'On B');
        $protexiomCmd->setType('action');
        $protexiomCmd->setSubType('other');
        $protexiomCmd->setDisplay('icon', '<i class="fa fa-lock"></i>');
        $protexiomCmd->setTemplate('dashboard', 'protexiomDefault');
        $protexiomCmd->setTemplate('mobile', 'protexiomDefault');
        $protexiomCmd->save();

        $protexiomCmd = new protexiomCmd();
        $protexiomCmd->setName(__('Marche C', __FILE__));
        $protexiomCmd->setLogicalId('zonec_on');
        $protexiomCmd->setEqLogic_id($this->id);
        $protexiomCmd->setConfiguration('somfyCmd', 'ZONEC_ON');
        $protexiomCmd->setConfiguration('mobileLabel', 'On C');
        $protexiomCmd->setType('action');
        $protexiomCmd->setSubType('other');
        $protexiomCmd->setDisplay('icon', '<i class="fa fa-lock"></i>');
        $protexiomCmd->setTemplate('dashboard', 'protexiomDefault');
        $protexiomCmd->setTemplate('mobile', 'protexiomDefault');
        $protexiomCmd->save();

        $protexiomCmd = new protexiomCmd();
        $protexiomCmd->setName(__('Arret A+B+C', __FILE__));
        $protexiomCmd->setLogicalId('zoneabc_off');
        $protexiomCmd->setEqLogic_id($this->id);
        $protexiomCmd->setConfiguration('somfyCmd', 'ALARME_OFF');
        $protexiomCmd->setConfiguration('mobileLabel', 'Off A+B+C');
        $protexiomCmd->setType('action');
        $protexiomCmd->setSubType('other');
        $protexiomCmd->setDisplay('icon', '<i class="fa fa-unlock"></i>');
        $protexiomCmd->setTemplate('dashboard', 'protexiomDefault');
        $protexiomCmd->setTemplate('mobile', 'protexiomDefault');
        $protexiomCmd->save();
        
        $protexiomCmd = new protexiomCmd();
        $protexiomCmd->setName(__('Eff. defaut alarm', __FILE__));
        $protexiomCmd->setLogicalId('reset_alarm_err');
        $protexiomCmd->setEqLogic_id($this->id);
        $protexiomCmd->setConfiguration('somfyCmd', 'RESET_ALARM_ERR');
        $protexiomCmd->setConfiguration('mobileLabel', 'CLR alarm');
        $protexiomCmd->setType('action');
        $protexiomCmd->setSubType('other');
        $protexiomCmd->setDisplay('icon', '<i class="fa fa-trash-o"></i>');
        $protexiomCmd->setTemplate('dashboard', 'protexiomDefault');
        $protexiomCmd->setTemplate('mobile', 'protexiomDefault');
        $protexiomCmd->save();
        
        $protexiomCmd = new protexiomCmd();
        $protexiomCmd->setName(__('Eff. defaut piles', __FILE__));
        $protexiomCmd->setLogicalId('reset_battery_err');
        $protexiomCmd->setEqLogic_id($this->id);
        $protexiomCmd->setConfiguration('somfyCmd', 'RESET_BATTERY_ERR');
        $protexiomCmd->setConfiguration('mobileLabel', 'CLR bat');
        $protexiomCmd->setType('action');
        $protexiomCmd->setSubType('other');
        $protexiomCmd->setDisplay('icon', '<i class="fa fa-trash-o"></i>');
        $protexiomCmd->setTemplate('dashboard', 'protexiomDefault');
        $protexiomCmd->setTemplate('mobile', 'protexiomDefault');
        $protexiomCmd->save();
        
        $protexiomCmd = new protexiomCmd();
        $protexiomCmd->setName(__('Eff. defaut liaison', __FILE__));
        $protexiomCmd->setLogicalId('reset_link_err');
        $protexiomCmd->setEqLogic_id($this->id);
        $protexiomCmd->setConfiguration('somfyCmd', 'RESET_LINK_ERR');
        $protexiomCmd->setConfiguration('mobileLabel', 'CLR link');
        $protexiomCmd->setType('action');
        $protexiomCmd->setSubType('other');
        $protexiomCmd->setDisplay('icon', '<i class="fa fa-trash-o"></i>');
        $protexiomCmd->setTemplate('dashboard', 'protexiomDefault');
        $protexiomCmd->setTemplate('mobile', 'protexiomDefault');
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
        $protexiomCmd->setName(__('Piles', __FILE__));
        $protexiomCmd->setLogicalId('battery_status');
        $protexiomCmd->setEqLogic_id($this->id);
        $protexiomCmd->setConfiguration('somfyCmd', 'BATTERY');
        $protexiomCmd->setConfiguration('mobileLabel', 'Piles');
        $protexiomCmd->setUnite('');
        $protexiomCmd->setType('info');
        $protexiomCmd->setSubType('binary');
        $protexiomCmd->setIsVisible(0);
        $protexiomCmd->setTemplate('dashboard', 'protexiomBattery');
        $protexiomCmd->setTemplate('mobile', 'protexiomBattery');
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
        
        //subEqlogic will be taken car of in postSave, as detecting them require protexiom eqLogic to be enabled and connected to the protexiom box
  
    }//End postInsert func

    /**
     * Called after a plugin device configuration setup or update
     * Standard Jeedom function
     * @author Fdp1
     *
     */
    public function postSave() {
    	$this->log('debug', "Running postsave method...");
    	//Let's unschedule protexiom pull
    	//If getIsenable == 1, we will reschedule (with an up to date polling interval)
    	$this->unSchedulePull();
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
    			$this->propagateIsEnable2subDevices();
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
    			
    			//Creating / refreshing subDevices
    			$this->createSubdevices();
    			//Let's propagate isEnable status before updating CMDs
    			$this->propagateIsEnable2subDevices();
    			
    			// Let's initialize status
    			$this->pullStatus(true);
    			
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
                        $cmd->setEventOnly(0);
    					$cmd->save();
    				}
    			}
    			
    		}
    	}else{//eqLogic disabled
    		$this->propagateIsEnable2subDevices();
    	}
    	
    	//Let's set specific CMD configuration
    	foreach ($this->getCmd('info') as $cmd) {
    		if($cmd->getLogicalId() == 'gsm_signal'){
    			$cmd->setConfiguration('maxValue', 5);
    			$cmd->save();
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
    	// Let's remove subDevices
    	foreach (self::byType('protexiom_ctrl') as $eqLogic) {
    		if ( substr($eqLogic->getLogicalId(), 0, strpos($eqLogic->getLogicalId(),"_")) == $this->getId() ) {
    			$this->log('info', 'Removing master remote control '.$eqLogic->getName());
    			$eqLogic->remove();
    		}
    	}
    	foreach (self::byType('protexiom_elmt') as $eqLogic) {
    		if ( substr($eqLogic->getLogicalId(), 0, strpos($eqLogic->getLogicalId(),"_")) == $this->getId() ) {
    			$this->log('info', 'Removing element '.$eqLogic->getName());
    			$eqLogic->remove();
    		}
    	}
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
    		$cron->setTimeout(70);//60 is the default. It's not a good odea to restart every daemons at once.
    		$cron->save();
    		$this->log('info', 'Scheduling protexiom pull.');
    	}
    }//end schedulePull function
    
    /**
     * Unchedule periodic status update
     * @author Fdp1
     *
     */
    public function unSchedulePull($halt_before = true){
    	$cron = cron::byClassAndFunction('protexiom', 'pull', array('protexiom_id' => intval($this->getId())));
    	if (is_object($cron)) {
    		$cron->remove($halt_before);
    		$this->log('info', 'Protexiom pull schedule removed.');
    	}else{
    		$this->log('debug', 'Unable to find protexiom pull daemon. Removal FAILED.');
    	}
    	$cron = cron::byClassAndFunction('protexiom', 'pull', array('protexiom_id' => intval($this->getId())));
    	if (is_object($cron)) {
    		echo '*!*!*!*!*!*!*IMPORTANT : unable to remove protexiom pull daemon for device '.$this->name.'. You may have to manually remove it. *!*!*!*!*!*!*!*';
    		$this->log('error', 'Unable to remove protexiom pull daemon. You may have to manually remove it.');
    	}
    	
    	//Polling is stopped. Let's logoff en clear authCookie
    	$cache=cache::byKey('somfyAuthCookie::'.$this->getId());
    	$cachedCookie=$cache->getValue();
    	if(!($cachedCookie==='' || $cachedCookie===null || $cachedCookie=='false')){
    		
    		$this->log('debug', 'Cached cookie found  while unscheduling. Trying to logoff');
    		$this->initSpBrowser();
    		if(!$myError=$this->_spBrowser->doLogout()){
    			$this->log('debug', 'Successfull logout while unscheduling. Deleting session cookie');
                cache::deleteBySearch('somfyAuthCookie::'.$this->getId());
    		}else{
    			$this->log('debug', 'Logout failed while unscheduling. Returned error: '.$myError);
    		}
    	}
    }//end unSchedulePull function

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
    		
    		if(!$myError=$this->_spBrowser->doLogout()){
    			$this->log('debug', 'Successfull logout for workaroundSomfySessionTimeoutBug. Deleting session cookie');
                cache::deleteBySearch('somfyAuthCookie::'.$this->getId());
    		}else{
    			$this->log('debug', 'Logout failed for workaroundSomfySessionTimeoutBug. Returned error: '.$myError);
    		}
    		if($myError=$this->_spBrowser->doLogin()){//Login failed
                $this->log('error', 'Login failed while trying to workaround somfy session timeout bug with error '.$myError.'.');
    			return 1;
    	
    		}else{//Login OK
    			$this->log('debug', 'Login successfull for workaroundSomfySessionTimeoutBug. Caching session cookie.');
    			cache::set('somfyAuthCookie::'.$this->getId(), $this->_spBrowser->authCookie, $this->_SomfySessionCookieTTL);
    			return 0;
    		}
    	}else{
    		// Session timeout bug is not likely as polling seems to be turned off
		$this->log('error', 'Session timeout bug is not likely as polling seems to be turned off (at least, no cookie as been found).');
    		return 1; 
    	}
    }//End function workaroundSomfySessionTimeoutBug

    /**
     * get the subdevices from the protexiom, and create them under jeedom
     * @author Fdp1
     */
    public function createSubdevices() {
    	// First, the master remote control, which list is static
    	 
    	if ( ! is_object(self::byLogicalId($this->getId().'_ctrl-lights', 'protexiom_ctrl')) ) {
    		$this->log('debug', 'Creating protexiom_ctrl ctrl-lights');
    		$eqLogic = new protexiom_ctrl();
    		$eqLogic->setEqType_name('protexiom_ctrl');
    		$eqLogic->setName('Centralisation lumières');
    		$eqLogic->setLogicalId($this->getId().'_ctrl-lights');
    		$eqLogic->setObject_id($this->getObject_id());
    		$eqLogic->setIsVisible(1);
    		$eqLogic->setIsEnable(1);
    		$eqLogic->setConfiguration('disabledByParent', '0');
    		$eqLogic->setCategory("light", 1);
    		$eqLogic->save();
    	}
    	 
    	if ( ! is_object(self::byLogicalId($this->getId().'_ctrl-shutters', 'protexiom_ctrl')) ) {
    		$this->log('debug', 'Creating protexiom_ctrl ctrl-shutters');
    		$eqLogic = new protexiom_ctrl();
    		$eqLogic->setEqType_name('protexiom_ctrl');
    		$eqLogic->setName('Centralisation volets');
    		$eqLogic->setLogicalId($this->getId().'_ctrl-shutters');
    		$eqLogic->setObject_id($this->getObject_id());
    		$eqLogic->setIsVisible(1);
    		$eqLogic->setIsEnable(1);
    		$eqLogic->setConfiguration('disabledByParent', '0');
    		$eqLogic->setCategory("automatism", 1);
    		$eqLogic->save();
    	}
    
    }//End function createSubdevices
    
    /**
     * propagate eqLogic isEnable status to every subdevice
     * @author Fdp1
     */
    public function propagateIsEnable2subDevices() {
    	$_isEnable=$this->getIsEnable();
    	 
    	if($_isEnable){
    		$_disabledByParent='0';
    
    		foreach (self::byType('protexiom_ctrl') as $eqLogic) {
    			if ( substr($eqLogic->getLogicalId(), 0, strpos($eqLogic->getLogicalId(),"_")) == $this->getId() ) {
    				if($eqLogic->getConfiguration('disabledByParent')=='1'){
    					$this->log('info', 'Enabling '.$eqLogic->getName().' from parent.');
    					$eqLogic->setIsEnable($_isEnable);
    					$eqLogic->setConfiguration('disabledByParent', $_disabledByParent);
    					$eqLogic->save();
    				}
    			}
    		}
    		foreach (self::byType('protexiom_elmt') as $eqLogic) {
    			if ( substr($eqLogic->getLogicalId(), 0, strpos($eqLogic->getLogicalId(),"_")) == $this->getId() ) {
    				if($eqLogic->getConfiguration('disabledByParent')=='1'){
    					$this->log('info', 'Enabling '.$eqLogic->getName().' from parent.');
    					$eqLogic->setIsEnable($_isEnable);
    					$eqLogic->setConfiguration('disabledByParent', $_disabledByParent);
    					$eqLogic->save();
    				}
    			}
    		}
    	}else{
    		$_disabledByParent='1';
    
    		foreach (self::byType('protexiom_ctrl') as $eqLogic) {
    			if ( substr($eqLogic->getLogicalId(), 0, strpos($eqLogic->getLogicalId(),"_")) == $this->getId() ) {
    				if($eqLogic->getIsEnable()){
    					$this->log('info', 'Disabling '.$eqLogic->getName().' from parent.');
    					$eqLogic->setIsEnable($_isEnable);
    					$eqLogic->setConfiguration('disabledByParent', $_disabledByParent);
    					$eqLogic->save();
    				}
    			}
    		}
    		foreach (self::byType('protexiom_elmt') as $eqLogic) {
    			if ( substr($eqLogic->getLogicalId(), 0, strpos($eqLogic->getLogicalId(),"_")) == $this->getId() ) {
    				if($eqLogic->getIsEnable()){
    					$this->log('info', 'Disabling '.$eqLogic->getName().' from parent.');
    					$eqLogic->setIsEnable($_isEnable);
    					$eqLogic->setConfiguration('disabledByParent', $_disabledByParent);
    					$eqLogic->save();
    				}
    			}
    		}
    	}
    }//End function propagateIsEnable2subDevices
    

    /*     * **********************Getteur Setteur*************************** */

    /**
     * Update status from spBrowser
     * @author Fdp1
     */
    public function setStatusFromSpBrowser($forceElementUpdate=false) {
    
    	$myError="";
    	$statusUpdated=0;
    	$elementUpdateRequired=0;
    	
    	if (!is_object($this->_spBrowser)) {
    		throw new Exception(__('Fatal error: setStatusFromSpBrowser called but $_spBrowser is not initialised.', __FILE__));
    	}
    	$status=$this->_spBrowser->getStatus();
    	foreach ($this->getCmd('info') as $cmd) {
    		$currentLogicalId=$cmd->getLogicalId();
    		if($cmd->getSubType()=='binary'){
    			$newValue=(string)preg_match("/^o[k n]$/i", $status[$cmd->getConfiguration('somfyCmd')]);
    		}else{
    			$newValue=$status[$cmd->getConfiguration('somfyCmd')];
    			if($currentLogicalId=="alarm" or $currentLogicalId=="link" or $currentLogicalId=="battery_status" or $currentLogicalId=="door" or $currentLogicalId=="tampered"){
    				$elementUpdateRequired++;
    			}
    		}
    		
    		if(!($cmd->execCmd(null, 2)==$newValue)){//Changed value
    			//We just ran execCmd, wich set $_collectDate
    			//Event() will check if $_collectDate is old, and reject the event if it's the case.
    			//Let's clear it before throwing the event
    			$cmd->setCollectDate('');
    			$cmd->event($newValue);
    			$statusUpdated++;
    		}// else, unchanged value. Let's keep the cached one
    	}
        // Battery level is a specific info handle by Jeedom in a specific way.
    	// For Jeedom, 10% means low battery
        if($status['BATTERY']=="ok"){
            $newValue='100';
        }else{
            $newValue='10';
        }
        if(!($this->getConfiguration('batteryStatus')==$newValue)){//Changed value
    		$this->log('debug', 'Setting new battery value to '.$newValue);
            $this->batteryStatus($newValue);
            $statusUpdated++;
        }
        //To avoid stressing the protexiom, let's not get Elements details when we know for sure they are unchanged.
        if($statusUpdated or $forceElementUpdate or $elementUpdateRequired){
        	$this->pullElements();
        }
        
    	return;
    }//End function setStatusFromSpBrowser

    /**
     * Update elements from spBrowser
     * @author Fdp1
     */
    public function setElementsFromSpBrowser() {
    
    	$myError="";
    	if (!is_object($this->_spBrowser)) {
    		throw new Exception(__('Fatal error: setElemensFromSpBrowser called but $_spBrowser is not initialised.', __FILE__));
    	}
    	$elements=$this->_spBrowser->getElements();
    	
    	foreach ($elements as $elementId => $element) {
    		$eqLogic = self::byLogicalId($this->getId().'_elmt-'.$elementId, 'protexiom_elmt');
    		if ( ! is_object($eqLogic) ) {
    			//The subdevice does not exist. Let's create it
    			$this->log('debug', 'Creating protexiom_elmt elmt-'.$elementId);
    			$eqLogic = new protexiom_elmt();
    			$eqLogic->setEqType_name('protexiom_elmt');
    			$eqLogic->setIsEnable(1);
    			$eqLogic->setName($element['name']);
    			$eqLogic->setLogicalId($this->getId().'_elmt-'.$elementId);
    			//By default, objectId is the same as the parent device
    			$eqLogic->setObject_id($this->getObject_id());
    			//However, we can try and guess a better objectId in the device name
    			foreach (object::all() as $object) {
    				if (stristr($element['name'],$object->getName())){
    					$eqLogic->setObject_id($object->getId());
    					break;
    				}
    			}
    			//Remotes or badges subdevices are pretty useless in Jeedom. Let's disable them by default
    			if(preg_match("/(remote|badge)/i", $element['type'])){
    				$eqLogic->setIsEnable(0);
    			}else{
    				$eqLogic->setIsEnable(1);
    			}
    			//Transmitters, sirens and keybord are not really usefull as well, but at least, we can get their battery status.
    			//Let's enable them by default, but hide them
    			if(preg_match("/(trans|siren|keyb)/i", $element['type'])){
    				$eqLogic->setIsVisible(0);
    			}else{
    				$eqLogic->setIsVisible(1);
    			}
    			
    			
    			$eqLogic->setConfiguration('disabledByParent', '0');
    			$eqLogic->setCategory("security", 1);
    			$eqLogic->setConfiguration('item_type', $element['type']);
    			$eqLogic->setConfiguration('item_label', $element['label']);
    			$eqLogic->setConfiguration('item_zone', $element['zone']);
    			$eqLogic->setConfiguration('disabledByParent', '0');
    			$eqLogic->save();
    			@message::add('protexiom', 'New protexiom subdevice created: '.$this->name.'/'.$element['name'], '', $this->name);
    			//Let's clear element properties from the array and just keep element cmds
    			unset($element['name']);
    			unset($element['type']);
    			unset($element['label']);
    			unset($element['zone']);
    			
    			//Let's now create cmds for the newly created subdevice
    			foreach ($element as $cmdName => $cmdValue) {
    				$elmtCmd = new protexiom_elmtCmd();
    				$elmtCmd->setName(__($cmdName, __FILE__));
    				$elmtCmd->setLogicalId($cmdName);
    				$elmtCmd->setEqLogic_id($eqLogic->id);
    				$elmtCmd->setUnite('');
    				$elmtCmd->setType('info');
    				//It would appear that every cmd is binary. However, unknown cmd could come in a future version
    				//To keep compatible in such a case, let's try and guess if the cmd really is binay
    				if(preg_match("/^([0:1]|[0-9 a-z]*ok)$/i", $cmdValue)){//Cmd is binary
    					$this->log('debug', 'Setting '.$eqLogic->getName()."/".$cmdName." as binary.");
    					$elmtCmd->setSubType('binary');
    					
    				}else{//Cmd is not binary
    					$elmtCmd->setSubType('string');
    					$this->log('debug', 'Setting '.$eqLogic->getName()."/".$cmdName." as string.");
    				}
    				switch ($cmdName) {
    					case "pause":
    						$elmtCmd->setTemplate('dashboard', 'protexiomPause');
    						$elmtCmd->setTemplate('mobile', 'protexiomPause');
    						$elmtCmd->setIsVisible(0);
    						break;
    					case "battery":
    						$elmtCmd->setTemplate('dashboard', 'protexiomElmtBattery');
    						$elmtCmd->setTemplate('mobile', 'protexiomElmtBattery');
    						$elmtCmd->setIsVisible(0);
    						break;
    					case "tampered":
    						$elmtCmd->setTemplate('dashboard', 'protexiomElmtTampered');
    						$elmtCmd->setTemplate('mobile', 'protexiomElmtTampered');
    						$elmtCmd->setIsVisible(1);
    						break;
    					case "alarm":
    						$elmtCmd->setTemplate('dashboard', 'protexiomAlarm');
    						$elmtCmd->setTemplate('mobile', 'protexiomAlarm');
    						$elmtCmd->setIsVisible(1);
    						break;
    					case "link":
    						$elmtCmd->setTemplate('dashboard', 'protexiomElmtLink');
    						$elmtCmd->setTemplate('mobile', 'protexiomElmtLink');
    						$elmtCmd->setIsVisible(1);
    						break;
    					case "door":
    						$elmtCmd->setTemplate('dashboard', 'protexiomElmtDoor');
    						$elmtCmd->setTemplate('mobile', 'protexiomElmtDoor');
    						$elmtCmd->setIsVisible(1);
    						break;
    					default:
    						$elmtCmd->setIsVisible(1);
    				}
    				$elmtCmd->save();
    				$this->log('debug', 'Cmd '.$eqLogic->getName()."/".$cmdName." created.");
    				
    			}
    		}//Else the subdevice already exists
    		
    		//Let's update the subdevice, but only if it's enabled
    		if($eqLogic->getIsEnable()){
    			foreach ($eqLogic->getCmd('info') as $cmd) {
    				if($cmd->getSubType()=='binary'){
    					//false binary values = 0 or end with NOK
    					if(preg_match("/^([0]|[0-9 a-z]*nok)$/i", $element[$cmd->getLogicalId()])){
    						$newValue='0';
    					}else{
    						$newValue='1';
    					}
    				}else{
    					$newValue=$element[$cmd->getLogicalId()];
    				}
    					 
    				if(!($cmd->execCmd(null, 2)==$newValue)){//Changed value
    					//We just ran execCmd, wich set $_collectDate
    					//Event() will check if $_collectDate is old, and reject the event if it's the case.
    					//Let's clear it before throwing the event
    					$cmd->setCollectDate('');
    					$cmd->event($newValue);
    						
    					// Battery level is a specific info handle by Jeedom in a specific way.
    					// For Jeedom, 10% means low battery
    					if($cmd->getLogicalId()=="battery"){
    						if(preg_match("/^([0]|[0-9 a-z]*nok)$/i", $element[$cmd->getLogicalId()])){
    							$eqLogic->batteryStatus('10');
    						}else{
    							$eqLogic->batteryStatus('100');
    						}
    					}
    						
    				}// else, unchanged value. Let's keep the cached one
    			}//End of cmd iteration
    		}// else eqLogic is NOT enabled
    	}//End of elements iteration
    
    	return;
    }//End function setElementsFromSpBrowser
    
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

    /**
     * get protexiom elements from cache. If not availlable in cache, pull and cache it
     * @author Fdp1
     * @return array status
     */
    public function getElementsFromCache() {
    	$cache=cache::byKey('somfyElements::'.$this->getId());
    	$elements=$cache->getValue();
    	if(!($elements==='' || $elements===null || $elements=='false')){
    		if(!$cache->hasExpired()){
    			$this->log('debug', 'Cached protexiom elements found.');
    			return json_decode($elements, true);
    		}
    	}
    	$this->log('debug', 'No (unexpired) cached protexiom elements found. Let\'s pull elements.');
    	if ($myError=$this->pullElements()){
    		//An error occured while pulling elements
    		$this->log('debug', 'An error occured while pulling elements: '.$myError);
    		throw new Exception(__("An error occured while pulling elements: $myError",__FILE__));
    	}else{
    		cache::set('somfyElements::'.$this->getId(), json_encode($this->_spBrowser->getElements()), $this->_SomfyStatusCacheLifetime);
    		$this->log('debug', 'Somfy protexiom elements successfully pulled and cache');
    		return $this->_spBrowser->getElements();
    	}
    }//End function getElementsFromCache

}

class protexiomCmd extends cmd {
    /*     * *************************Attributs****************************** */
	private static $_templateArray = array();//Needed for toHtml()

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
    	$infoValue="";
  
    	if ($this->getType() == 'info') {
            if($this->getSubType()=='binary'){
    			$infoValue=(string)preg_match("/^o[k n]$/i", $protexiom->getStatusFromCache()[$this->getConfiguration('somfyCmd')]);
    		}elseif($this->getSubType()=='numeric'){
    			if(filter_var($protexiom->getStatusFromCache()[$this->getConfiguration('somfyCmd')], FILTER_VALIDATE_FLOAT)){
    				$infoValue=$protexiom->getStatusFromCache()[$this->getConfiguration('somfyCmd')];
    			}else{
    				// Returning a non numeric value for a numeric cmd may create bugs
    				// This happens, for exemple, on the gsm_ignal cmd, when no GSM module is enabled on the protexiom
    				// Somfy returns "" instead of 0. This is a major problem for javascript in the widget
    				// Forcing the value to 0 in case of a non numeric value seems pretty safe, and will fix the problem
    				$infoValue="0";
    			}
    		}else{
    			$infoValue=$protexiom->getStatusFromCache()[$this->getConfiguration('somfyCmd')];
    		}
		$protexiom->log('debug', $this->name." CMD run OK");
    		return $infoValue;
      	}elseif ($this->getType() == 'action') {
      		$protexiom->initSpBrowser();
		$protexiom->log('debug', "Sending web request for ".$this->name." CMD");
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
			$protexiom->log('debug', $this->name." CMD run OK");
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
     * Only modified to get a spcific device name on mobile widget
     * @param array $_options
     * @author Fdp1
     */
    	public function toHtml($_version = 'dashboard', $options = '', $_cmdColor = null, $_cache = 2) {
		$version = jeedom::versionAlias($_version);
		$html = '';
		$template_name = 'cmd.' . $this->getType() . '.' . $this->getSubType() . '.' . $this->getTemplate($version, 'default');
		$template = '';
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
			if ($template == '') {
				$template_name = 'cmd.' . $this->getType() . '.' . $this->getSubType() . '.default';
				$template = getTemplate('core', $version, $template_name);
			}
		} else {
			$template = getTemplate('core', $version, $template_name);
		}
		self::$_templateArray[$version . '::' . $template_name] = $template;
		$replace = array(
			'#id#' => $this->getId(),
            '#icon#' => $this->getDisplay('icon'),
			'#name#' => ($_version == 'mobile' || $_version == 'mview') ? $this->getConfiguration('mobileLabel') : $this->getName(),
			'#name_display#' => ($this->getDisplay('icon') != '') ? $this->getDisplay('icon') : $this->getName(),
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
			$replace['#name_display#'] = '';
			$replace['#name#'] = '';
		} else if ($this->getDisplay('doNotShowNameOnDashboard') == 1 && ($_version == 'mobile' || $_version == 'dashboard')) {
			$replace['#name_display#'] = '';
			$replace['#name#'] = '';
		} else {
			$replace['#name_display#'] .= '<br/>';
		}
		if ($this->getType() == 'info') {
			$replace['#state#'] = '';
			$replace['#tendance#'] = '';
			$replace['#state#'] = $this->execCmd(null, $_cache);
			if (strpos($replace['#state#'], 'error::') !== false) {
				$template = getTemplate('core', $version, 'cmd.error');
				$replace['#state#'] = str_replace('error::', '', $replace['#state#']);
			} else {
				if ($this->getSubType() == 'binary' && $this->getDisplay('invertBinary') == 1) {
					$replace['#state#'] = ($replace['#state#'] == 1) ? 0 : 1;
				}
			}
			if (method_exists($this, 'formatValueWidget')) {
				$replace['#state#'] = $this->formatValueWidget($replace['#state#']);
			}
			$replace['#collectDate#'] = $this->getCollectDate();
			$replace['#valueDate#'] = $this->getValueDate();
			if ($this->getIsHistorized() == 1) {
				$replace['#history#'] = 'history cursor';
				if (config::byKey('displayStatsWidget') == 1 && strpos($template, '#displayHistory#') !== false) {
					$showStat = true;
					if ($this->getDisplay('doNotShowStatOnDashboard') == 1 && $_version == 'dashboard') {
						$showStat = false;
					}
					if ($this->getDisplay('doNotShowStatOnView') == 1 && ($_version == 'dview' || $_version == 'mview')) {
						$showStat = false;
					}
					if ($this->getDisplay('doNotShowStatOnMobile') == 1 && $_version == 'mobile') {
						$showStat = false;
					}
					if ($showStat) {
						$startHist = date('Y-m-d H:i:s', strtotime(date('Y-m-d H:i:s') . ' -' . config::byKey('historyCalculPeriod') . ' hour'));
						$replace['#displayHistory#'] = '';
						$historyStatistique = $this->getStatistique($startHist, date('Y-m-d H:i:s'));
						if ($historyStatistique['avg'] == 0 && $historyStatistique['min'] == 0 && $historyStatistique['max'] == 0) {
							$replace['#averageHistoryValue#'] = round($replace['#state#'], 1);
							$replace['#minHistoryValue#'] = round($replace['#state#'], 1);
							$replace['#maxHistoryValue#'] = round($replace['#state#'], 1);
						} else {
							$replace['#averageHistoryValue#'] = round($historyStatistique['avg'], 1);
							$replace['#minHistoryValue#'] = round($historyStatistique['min'], 1);
							$replace['#maxHistoryValue#'] = round($historyStatistique['max'], 1);
						}
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
			$replace['#valueName#'] .= '<br/>';
			$html .= template_replace($replace, $template);
			if (trim($html) == '') {
				return $html;
			}
			if ($options != '') {
				$options = jeedom::toHumanReadable($options);
				if (is_json($options)) {
					$options = json_decode($options, true);
				}
				if (is_array($options)) {
					foreach ($options as $key => $value) {
						$replace['#' . $key . '#'] = $value;
					}
				}
			}
			if ($version == 'scenario' && $this->getType() == 'action' && $this->getSubtype() == 'message') {
				if (!isset($replace['#title#'])) {
					$replace['#title#'] = '';
				}
				if (!isset($replace['#message#'])) {
					$replace['#message#'] = '';
				}
			}
			if ($version == 'scenario' && $this->getType() == 'action' && $this->getSubtype() == 'slider' && !isset($replace['#slider#'])) {
				$replace['#slider#'] = '';
			}
			if ($version == 'scenario' && $this->getType() == 'action' && $this->getSubtype() == 'slider' && !isset($replace['#color#'])) {
				$replace['#color#'] = '';
			}
			$replace['#title_placeholder#'] = $this->getDisplay('title_placeholder', __('Titre', __FILE__));
			$replace['#message_placeholder#'] = $this->getDisplay('message_placeholder', __('Message', __FILE__));
			$replace['#message_disable#'] = $this->getDisplay('message_disable', 0);
			$replace['#title_disable#'] = $this->getDisplay('title_disable', 0);
			$replace['#title_possibility_list#'] = str_replace("'", "\'", $this->getDisplay('title_possibility_list', ''));
			$replace['#slider_placeholder#'] = $this->getDisplay('slider_placeholder', __('Valeur', __FILE__));
			$replace['#other_tooltips#'] = ($replace['#name#'] != $this->getName()) ? $this->getName() : '';
			$html = template_replace($replace, $html);
			return $html;
		}
	}

    /*     * **********************Getteur Setteur*************************** */
}

?>
<?php

/* This file is part of the Jeedom Protexiom plugin.
 *
 * This plugin is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This software is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
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
     * Return the corresponding authCode from the somfy code card
     *
     * @author Fdp1
     * @param string $codeId Coordinates of the code on the card
     * @return string authcode, or '' if coordinate is not valid
     * @usage authCode = getAuthCode("A3")
     */
    private function getAuthCode($codeID = '')
    {
    	$authCard=array();
    	$authCode='';
    	if(preg_match ( "/^[A-F][1-5]$/" , $codeID )){//The codeID is valid (from A1 to F5)
    		$lineNum=substr($codeID, 1, 1);
    		list($authCard["A$lineNum"], $authCard["B$lineNum"], $authCard["C$lineNum"], $authCard["D$lineNum"], $authCard["E$lineNum"], $authCard["F$lineNum"])=preg_split("/[^0-9]/", $this->getConfiguration("AuthCardL$lineNum"));
    		$authCode=$authCard[$codeID];
    	}
    	return $authCode;
    }//End getAuthCode func
    
    /*public static function pull($_options) {
        $weather = weather::byId($_options['weather_id']);
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
        }
    }*/

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
    	
        /*$weatherCmd = new weatherCmd();
        $weatherCmd->setName(__('TempÃ©rature', __FILE__));
        $weatherCmd->setEqLogic_id($this->id);
        $weatherCmd->setConfiguration('day', '-1');
        $weatherCmd->setConfiguration('data', 'temp');
        $weatherCmd->setUnite('Â°C');
        $weatherCmd->setType('info');
        $weatherCmd->setSubType('numeric');
        $weatherCmd->save();

        $weatherCmd = new weatherCmd();
        $weatherCmd->setName(__('HumiditÃ©', __FILE__));
        $weatherCmd->setEqLogic_id($this->id);
        $weatherCmd->setConfiguration('day', '-1');
        $weatherCmd->setConfiguration('data', 'humidity');
        $weatherCmd->setUnite('%');
        $weatherCmd->setType('info');
        $weatherCmd->setSubType('numeric');
        $weatherCmd->save();

        $weatherCmd = new weatherCmd();
        $weatherCmd->setName(__('Pression', __FILE__));
        $weatherCmd->setEqLogic_id($this->id);
        $weatherCmd->setConfiguration('day', '-1');
        $weatherCmd->setConfiguration('data', 'pressure');
        $weatherCmd->setUnite('Pa');
        $weatherCmd->setType('info');
        $weatherCmd->setSubType('numeric');
        $weatherCmd->save();

        $weatherCmd = new weatherCmd();
        $weatherCmd->setName(__('Condition Actuelle', __FILE__));
        $weatherCmd->setEqLogic_id($this->id);
        $weatherCmd->setConfiguration('day', '-1');
        $weatherCmd->setConfiguration('data', 'condition');
        $weatherCmd->setUnite('');
        $weatherCmd->setType('info');
        $weatherCmd->setSubType('string');
        $weatherCmd->save();

        $weatherCmd = new weatherCmd();
        $weatherCmd->setName(__('Vitesse du vent', __FILE__));
        $weatherCmd->setEqLogic_id($this->id);
        $weatherCmd->setConfiguration('day', '-1');
        $weatherCmd->setConfiguration('data', 'wind_speed');
        $weatherCmd->setUnite('km/h');
        $weatherCmd->setType('info');
        $weatherCmd->setSubType('numeric');
        $weatherCmd->save();

        $weatherCmd = new weatherCmd();
        $weatherCmd->setName(__('Direction du vent', __FILE__));
        $weatherCmd->setEqLogic_id($this->id);
        $weatherCmd->setConfiguration('day', '-1');
        $weatherCmd->setConfiguration('data', 'wind_direction');
        $weatherCmd->setUnite('');
        $weatherCmd->setType('info');
        $weatherCmd->setSubType('string');
        $weatherCmd->save();

        $weatherCmd = new weatherCmd();
        $weatherCmd->setName(__('Lever du soleil', __FILE__));
        $weatherCmd->setEqLogic_id($this->id);
        $weatherCmd->setConfiguration('day', '-1');
        $weatherCmd->setConfiguration('data', 'sunrise');
        $weatherCmd->setUnite('');
        $weatherCmd->setType('info');
        $weatherCmd->setSubType('numeric');
        $weatherCmd->save();

        $weatherCmd = new weatherCmd();
        $weatherCmd->setName(__('Coucher du soleil', __FILE__));
        $weatherCmd->setEqLogic_id($this->id);
        $weatherCmd->setConfiguration('day', '-1');
        $weatherCmd->setConfiguration('data', 'sunset');
        $weatherCmd->setUnite('');
        $weatherCmd->setType('info');
        $weatherCmd->setSubType('numeric');
        $weatherCmd->save();

        $weatherCmd = new weatherCmd();
        $weatherCmd->setName(__('TempÃ©rature Min', __FILE__));
        $weatherCmd->setEqLogic_id($this->id);
        $weatherCmd->setConfiguration('day', '0');
        $weatherCmd->setConfiguration('data', 'low');
        $weatherCmd->setUnite('Â°C');
        $weatherCmd->setType('info');
        $weatherCmd->setSubType('numeric');
        $weatherCmd->save();

        $weatherCmd = new weatherCmd();
        $weatherCmd->setName(__('TempÃ©rature Max', __FILE__));
        $weatherCmd->setEqLogic_id($this->id);
        $weatherCmd->setConfiguration('day', '0');
        $weatherCmd->setConfiguration('data', 'high');
        $weatherCmd->setUnite('Â°C');
        $weatherCmd->setType('info');
        $weatherCmd->setSubType('numeric');
        $weatherCmd->save();

        $weatherCmd = new weatherCmd();
        $weatherCmd->setName(__('Condition', __FILE__));
        $weatherCmd->setEqLogic_id($this->id);
        $weatherCmd->setConfiguration('day', '0');
        $weatherCmd->setConfiguration('data', 'condition');
        $weatherCmd->setUnite('');
        $weatherCmd->setType('info');
        $weatherCmd->setSubType('string');
        $weatherCmd->save();

        $weatherCmd = new weatherCmd();
        $weatherCmd->setName(__('TempÃ©rature Min +1', __FILE__));
        $weatherCmd->setEqLogic_id($this->id);
        $weatherCmd->setConfiguration('day', '1');
        $weatherCmd->setConfiguration('data', 'low');
        $weatherCmd->setUnite('Â°C');
        $weatherCmd->setType('info');
        $weatherCmd->setSubType('numeric');
        $weatherCmd->save();

        $weatherCmd = new weatherCmd();
        $weatherCmd->setName(__('TempÃ©rature Max +1', __FILE__));
        $weatherCmd->setEqLogic_id($this->id);
        $weatherCmd->setConfiguration('day', '1');
        $weatherCmd->setConfiguration('data', 'high');
        $weatherCmd->setUnite('Â°C');
        $weatherCmd->setType('info');
        $weatherCmd->setSubType('numeric');
        $weatherCmd->save();

        $weatherCmd = new weatherCmd();
        $weatherCmd->setName(__('Condition +1', __FILE__));
        $weatherCmd->setEqLogic_id($this->id);
        $weatherCmd->setConfiguration('day', '1');
        $weatherCmd->setConfiguration('data', 'condition');
        $weatherCmd->setUnite('');
        $weatherCmd->setType('info');
        $weatherCmd->setSubType('string');
        $weatherCmd->save();*/
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

    /*
    public function dontRemoveCmd() {
        return true;
    }
     */

    public function execute($_options = array()) {
        return false;
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
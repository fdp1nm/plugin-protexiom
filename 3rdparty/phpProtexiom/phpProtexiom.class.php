<?php

/* Copyright (C) 2014 fdp1
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

class phpProtexiom {
	/*     * *************************Attributs privés****************************** */

	protected $status = array();
	protected $somfyBaseURL='';
	protected $sslEnabled = false;
	// TODO test SSL
	protected $hwParam=array("Version"  => ""); //Store pretexiom hardware versions parameters

	/*     * *************************Attributs publics	****************************** */

	public $userPwd = '';
	public $authCookie = '';
	public $authCard = array();

	/*     * ***********************Methodes*************************** */

	/**
	 * phpProtexiom Constructor.
	 *
	 * @author Fdp1
	 * @param string $host protexiom host[:port]
	 * @param bool $sslEnabled sslEnabled (optional)
	 */
	function phpProtexiom($host, $sslEnabled=false)
	{
		if($sslEnabled){
			$this->somfyBaseURL='https://'.$host;
		}else{
			$this->somfyBaseURL='http://'.$host;
		}
		$this->sslEnabled=$sslEnabled;
	}

	/**
	 * Parse text HTTP headers, and return them as an array
	 *
	 * @author Fdp1
	 * @param string $header protexiom host
	 * @return array headers as $key => $value
	 */
	protected static function http_parse_headers( $header )
	{
		$retVal = array();
		$fields = explode("\r\n", preg_replace('/\x0D\x0A[\x09\x20]+/', ' ', $header));
		foreach( $fields as $field ) {
			if( preg_match('/([^:]+): (.+)/m', $field, $match) ) {
				$match[1] = preg_replace('/(?<=^|[\x09\x20\x2D])./e', 'strtoupper("\0")', strtolower(trim($match[1])));
				if( isset($retVal[$match[1]]) ) {
					$retVal[$match[1]] = array($retVal[$match[1]], $match[2]);
				} else {
					$retVal[$match[1]] = trim($match[2]);
				}
			}
		}
		return $retVal;
	}
	
	/**
	 * Get the hardware compatibility
	 *
	 * @author Fdp1
	 * @return array compatible hardware versions, and their parameters
	 */
	protected static function getCompatibleHw()
	{
		//Creating Hardware parameters array
		$fullHwParam=array();
		//Version 3
		//V1 MUST be declared after V3, to avoid a false positive
		//V3 Hw would be positive to V1 test, but might then be broken
		$fullHwParam['3']['Pattern']['Auth']="#<b>(..)</b>#";
		$fullHwParam['3']['Pattern']['Error']['0']='/<div id="infobox">(.*?)<\/div>/s';
		$fullHwParam['3']['Pattern']['Error']['1']='/<table>(.*?)<\/table>/s';
		$fullHwParam['3']['URL']['login']="/m_login.htm";
		$fullHwParam['3']['URL']['logout']="/m_logout.htm";
		$fullHwParam['3']['URL']['welcome']="/mu_welcome.htm";
		$fullHwParam['3']['URL']['Error']="/error.htm";
		$fullHwParam['3']['URL']['Status']="/status.xml";
		$fullHwParam['3']['URL']['Pilotage']="/mu_pilotage.htm";
		$fullHwParam['3']['URL']['EraseDefault']="/u_listelmt.htm";
		$fullHwParam['3']['ReqBody']['login']="login=u&password=#UserPwd#&key=#AuthKey#&action=Connexion&img.x=51&img.y=14";
		$fullHwParam['3']['StatusTag']['ZONE_A']="zone0";// ON/OFF
		$fullHwParam['3']['StatusTag']['ZONE_B']="zone1";// ON/OFF
		$fullHwParam['3']['StatusTag']['ZONE_C']="zone2";// ON/OFF
		$fullHwParam['3']['StatusTag']['BATTERY']="defaut0";// Battery default OK/?
		$fullHwParam['3']['StatusTag']['LINK']="defaut1";// Communication default OK/?
		$fullHwParam['3']['StatusTag']['DOOR']="defaut2";// Open door or window OK/?
		$fullHwParam['3']['StatusTag']['ALARM']="defaut3";// Alarm trggered OK/nok_int
		$fullHwParam['3']['StatusTag']['TAMPERED']="defaut4";// Opened device box OK/?
		$fullHwParam['3']['StatusTag']['GSM_LINK']="gsm";// "GSM connectÃ© au rÃ©seau" or ?
		$fullHwParam['3']['StatusTag']['GSM_SIGNAL']="recgsm";// Reception level (Interger, 1, 2, 3, 4)
		$fullHwParam['3']['StatusTag']['GSM_OPERATOR']="opegsm";//  Orange, ...
		$fullHwParam['3']['StatusTag']['CAMERA']="camera";// Web cam connected (disabled or ?)
		$fullHwParam['3']['Pilotage']['ZONEA_ON']="hidden=hidden&zone=Marche%20A&img.x=40&img.y=7";
		$fullHwParam['3']['Pilotage']['ZONEB_ON']="hidden=hidden&zone=Marche%20B&img.x=33&img.y=5";
		$fullHwParam['3']['Pilotage']['ZONEC_ON']="hidden=hidden&zone=Marche%20C&img.x=40&img.y=10";
		$fullHwParam['3']['Pilotage']['ZONEABC_ON']="hidden=hidden&zone=Marche%20A%20B%20C&img.x=45&img.y=7";
		$fullHwParam['3']['Pilotage']['ALARME_OFF']="zone=Arr%eat%20A%20B%20C";
		$fullHwParam['3']['Pilotage']['LIGHT_ON']="hidden=hidden&action_lum=ON";
		$fullHwParam['3']['Pilotage']['LIGHT_OFF']="hidden=hidden&action_lum=OFF";
		$fullHwParam['3']['Pilotage']['SHUTTER_UP']="hidden=hidden&action_vol_montee=";
		$fullHwParam['3']['Pilotage']['SHUTTER_DOWN']="hidden=hidden&action_vol_descente=";
		$fullHwParam['3']['Pilotage']['SHUTTER_STOP']="hidden=hidden&action_vol_stop=";
		$fullHwParam['3']['EraseDefault']['RESET_BATTERY_ERR']="efface=Piles";
		$fullHwParam['3']['EraseDefault']['RESET_ALARM_ERR']="efface=Alarmes";
		$fullHwParam['3']['EraseDefault']['RESET_LINK_ERR']="efface=Liaisons";
		
		//Version 1
		$fullHwParam['1']['Pattern']['Auth']="#Code d'authentification (..)</td>#";
		$fullHwParam['1']['Pattern']['Error']['0']='/<div id="infobox">(.*?)<\/div>/s';
		$fullHwParam['1']['Pattern']['Error']['1']='/<table>(.*?)<\/table>/s';
		$fullHwParam['1']['URL']['login']="/login.htm";
		$fullHwParam['1']['URL']['logout']="/logout.htm";
		$fullHwParam['1']['URL']['welcome']="/welcome.htm";
		$fullHwParam['1']['URL']['Error']="/error.htm";
		$fullHwParam['1']['URL']['Status']="/status.xml";
		$fullHwParam['1']['URL']['Pilotage']="/u_pilotage.htm";
		$fullHwParam['1']['URL']['EraseDefault']="/u_listelmt.htm";
		$fullHwParam['1']['ReqBody']['login']="login=u&password=#UserPwd#&key=#AuthKey#&action=Connexion";
		$fullHwParam['1']['StatusTag']['ZONE_A']="zone0";// ON/OFF
		$fullHwParam['1']['StatusTag']['ZONE_B']="zone1";// ON/OFF
		$fullHwParam['1']['StatusTag']['ZONE_C']="zone2";// ON/OFF
		$fullHwParam['1']['StatusTag']['BATTERY']="defaut0";// Battery default OK/?
		$fullHwParam['1']['StatusTag']['LINK']="defaut1";// Communication default OK/?
		$fullHwParam['1']['StatusTag']['DOOR']="defaut2";// Open door or window OK/?
		$fullHwParam['1']['StatusTag']['ALARM']="defaut3";// Alarm trggered OK/?
		$fullHwParam['1']['StatusTag']['TAMPERED']="defaut4";// Opened device box OK/?
		$fullHwParam['1']['StatusTag']['GSM_LINK']="gsm";// "GSM connectÃ© au rÃ©seau" or ?
		$fullHwParam['1']['StatusTag']['GSM_SIGNAL']="recgsm";// Reception level (Interger, 1, 2, 3, 4)
		$fullHwParam['1']['StatusTag']['GSM_OPERATOR']="opegsm";//  Orange, ...
		$fullHwParam['1']['StatusTag']['CAMERA']="camera";// Web cam connected (disabled or ?)
		$fullHwParam['1']['Pilotage']['ZONEA_ON']="hidden=hidden&zone=Marche+A";
		$fullHwParam['1']['Pilotage']['ZONEB_ON']="hidden=hidden&zone=Marche+B";
		$fullHwParam['1']['Pilotage']['ZONEC_ON']="hidden=hidden&zone=Marche+C";
		$fullHwParam['1']['Pilotage']['ZONEABC_ON']="hidden=hidden&zone=Marche+A+B+C";
		$fullHwParam['1']['Pilotage']['ALARME_OFF']="hidden=hidden&zone=Arr%EAt+A+B+C";
		$fullHwParam['1']['Pilotage']['LIGHT_ON']="hidden=hidden&action_lum=ON";
		$fullHwParam['1']['Pilotage']['LIGHT_OFF']="hidden=hidden&action_lum=OFF";
		$fullHwParam['1']['Pilotage']['SHUTTER_UP']="hidden=hidden&action_vol_montee=";
		$fullHwParam['1']['Pilotage']['SHUTTER_DOWN']="hidden=hidden&action_vol_descente=";
		$fullHwParam['1']['Pilotage']['SHUTTER_STOP']="hidden=hidden&action_vol_stop=";
		$fullHwParam['1']['EraseDefault']['RESET_BATTERY_ERR']="efface=Piles";
		$fullHwParam['1']['EraseDefault']['RESET_ALARM_ERR']="efface=Alarmes";
		$fullHwParam['1']['EraseDefault']['RESET_LINK_ERR']="efface=Liaisons";
		
		//Version 2
		$fullHwParam['2']['Pattern']['Auth']="#<b>(..)</b>#";
		$fullHwParam['2']['Pattern']['Error']['0']='/<div id="infobox">(.*?)<\/div>/s';
		$fullHwParam['2']['Pattern']['Error']['1']='/<table>(.*?)<\/table>/s';
		$fullHwParam['2']['URL']['login']="/fr/m_login.htm";
		$fullHwParam['2']['URL']['logout']="/m_logout.htm";
		$fullHwParam['2']['URL']['welcome']="/fr/mu_welcome.htm";
		$fullHwParam['2']['URL']['Error']="/fr/m_error.htm";
		$fullHwParam['2']['URL']['Status']="/status.xml";
		$fullHwParam['2']['URL']['Pilotage']="/fr/mu_pilotage.htm";
		$fullHwParam['2']['URL']['EraseDefault']="/fr/u_listelmt.htm";
		$fullHwParam['2']['ReqBody']['login']="login=u&password=#UserPwd#&key=#AuthKey#&btn_login=Connexion";
		$fullHwParam['2']['StatusTag']['ZONE_A']="zone0";// ON/OFF
		$fullHwParam['2']['StatusTag']['ZONE_B']="zone1";// ON/OFF
		$fullHwParam['2']['StatusTag']['ZONE_C']="zone2";// ON/OFF
		$fullHwParam['2']['StatusTag']['BATTERY']="defaut0";// Battery default OK/?
		$fullHwParam['2']['StatusTag']['LINK']="defaut1";// Communication default OK/?
		$fullHwParam['2']['StatusTag']['DOOR']="defaut2";// Open door or window OK/?
		$fullHwParam['2']['StatusTag']['ALARM']="defaut3";// Alarm trggered OK/?
		$fullHwParam['2']['StatusTag']['TAMPERED']="defaut4";// Opened device box OK/?
		$fullHwParam['2']['StatusTag']['GSM_LINK']="gsm";// "GSM connectÃ© au rÃ©seau" or ?
		$fullHwParam['2']['StatusTag']['GSM_SIGNAL']="recgsm";// Reception level (Interger, 1, 2, 3, 4)
		$fullHwParam['2']['StatusTag']['GSM_OPERATOR']="opegsm";//  Orange, ...
		$fullHwParam['2']['StatusTag']['CAMERA']="camera";// Web cam connected (disabled or ?)
		$fullHwParam['2']['Pilotage']['ZONEA_ON']="hidden=hidden&btn_zone_on_A=ON";
		$fullHwParam['2']['Pilotage']['ZONEB_ON']="hidden=hidden&btn_zone_on_B=ON";
		$fullHwParam['2']['Pilotage']['ZONEC_ON']="hidden=hidden&btn_zone_on_C=ON";
		$fullHwParam['2']['Pilotage']['ZONEABC_ON']="hidden=hidden&btn_zone_on_ABC=ON";
		$fullHwParam['2']['Pilotage']['ALARME_OFF']="hidden=hidden&btn_zone_off_ABC=OFF";
		$fullHwParam['2']['Pilotage']['LIGHT_ON']="hidden=hidden&btn_lum_on=ON";
		$fullHwParam['2']['Pilotage']['LIGHT_OFF']="hidden=hidden&btn_lum_off=OFF";
		$fullHwParam['2']['Pilotage']['SHUTTER_UP']="hidden=hidden&btn_vol_up=MONTEE";
		$fullHwParam['2']['Pilotage']['SHUTTER_DOWN']="hidden=hidden&btn_vol_down=DESCENTE";
		$fullHwParam['2']['Pilotage']['SHUTTER_STOP']="hidden=hidden&btn_vol_stop=STOP";
		$fullHwParam['2']['EraseDefault']['RESET_BATTERY_ERR']="btn_del_pil=Piles";
		$fullHwParam['2']['EraseDefault']['RESET_ALARM_ERR']="btn_del_alm=Alarmes";
		$fullHwParam['2']['EraseDefault']['RESET_LINK_ERR']="btn_del_lia=Liaisons";

		//Version 4
		//V4 MUST be declared after V2, to avoid a false positive
		//V2 Hw would be positive to V2 test, but might then be broken
		$fullHwParam['4']['Pattern']['Auth']="#<b>(..)</b>#";
		$fullHwParam['4']['Pattern']['Error']['0']='/<div id="infobox">(.*?)<\/div>/s';
		$fullHwParam['4']['Pattern']['Error']['1']='/<table>(.*?)<\/table>/s';
		$fullHwParam['4']['URL']['login']="/fr/login.htm";
		$fullHwParam['4']['URL']['logout']="/logout.htm";
		$fullHwParam['4']['URL']['welcome']="/fr/welcome.htm";
		$fullHwParam['4']['URL']['Error']="/fr/error.htm";
		$fullHwParam['4']['URL']['Status']="/status.xml";
		$fullHwParam['4']['URL']['Pilotage']="/fr/u_pilotage.htm";
		$fullHwParam['4']['URL']['EraseDefault']="/fr/u_listelmt.htm";
		$fullHwParam['4']['ReqBody']['login']="login=u&password=#UserPwd#&key=#AuthKey#&btn_login=Connexion";
		$fullHwParam['4']['StatusTag']['ZONE_A']="zone0";// ON/OFF
		$fullHwParam['4']['StatusTag']['ZONE_B']="zone1";// ON/OFF
		$fullHwParam['4']['StatusTag']['ZONE_C']="zone2";// ON/OFF
		$fullHwParam['4']['StatusTag']['BATTERY']="defaut0";// Battery default OK/?
		$fullHwParam['4']['StatusTag']['LINK']="defaut1";// Communication default OK/?
		$fullHwParam['4']['StatusTag']['DOOR']="defaut2";// Open door or window OK/?
		$fullHwParam['4']['StatusTag']['ALARM']="defaut3";// Alarm trggered OK/?
		$fullHwParam['4']['StatusTag']['TAMPERED']="defaut4";// Opened device box OK/?
		$fullHwParam['4']['StatusTag']['GSM_LINK']="gsm";// "GSM connectÃ© au rÃ©seau" or ?
		$fullHwParam['4']['StatusTag']['GSM_SIGNAL']="recgsm";// Reception level (Interger, 1, 2, 3, 4)
		$fullHwParam['4']['StatusTag']['GSM_OPERATOR']="opegsm";//  Orange, ...
		$fullHwParam['4']['StatusTag']['CAMERA']="camera";// Web cam connected (disabled or ?)
		$fullHwParam['4']['Pilotage']['ZONEA_ON']="hidden=hidden&btn_zone_on_A=Marche A";
		$fullHwParam['4']['Pilotage']['ZONEB_ON']="hidden=hidden&btn_zone_on_B=Marche B";
		$fullHwParam['4']['Pilotage']['ZONEC_ON']="hidden=hidden&btn_zone_on_C=Marche C";
		$fullHwParam['4']['Pilotage']['ZONEABC_ON']="hidden=hidden&btn_zone_on_ABC=Marche A B C";
		$fullHwParam['4']['Pilotage']['ALARME_OFF']="hidden=hidden&btn_zone_off_ABC=Arrêt A B C";
		$fullHwParam['4']['Pilotage']['LIGHT_ON']="hidden=hidden&btn_lum_on=ON";
		$fullHwParam['4']['Pilotage']['LIGHT_OFF']="hidden=hidden&btn_lum_off=OFF";
		$fullHwParam['4']['Pilotage']['SHUTTER_UP']="hidden=hidden&btn_vol_up=";
		$fullHwParam['4']['Pilotage']['SHUTTER_DOWN']="hidden=hidden&btn_vol_down=";
		$fullHwParam['4']['Pilotage']['SHUTTER_STOP']="hidden=hidden&btn_vol_stop=";
		$fullHwParam['4']['EraseDefault']['RESET_BATTERY_ERR']="btn_del_pil=Piles";
		$fullHwParam['4']['EraseDefault']['RESET_ALARM_ERR']="btn_del_alm=Alarmes";
		$fullHwParam['4']['EraseDefault']['RESET_LINK_ERR']="btn_del_lia=Liaisons";
		
		return $fullHwParam;
	}
	
	/**
	 * Get the hardware version
	 *
	 * @author Fdp1
	 * @return string Version number ("" if unset)
	 */
	function getHwVersion()
	{
		return $this->hwParam['Version'];
	}
	
	/**
	 * Set the hardware version
	 *
	 * To be used only if the hardware version is well known.
	 * If not, use instead detectHwVersion()
	 *
	 * @author Fdp1
	 * @return TRUE in case of sucess, FALSE in case of failure
	 */
	function setHwVersion($version)
	{
		$supportedVersion="";
		$fullHwParam=$this->getCompatibleHw();
		foreach ($fullHwParam as $currentHwVersion => $currentHwParam){
			$supportedVersion.=$currentHwVersion." ";
		}
		if(preg_match ( "/^[".$supportedVersion."]$/" , $version )){
			$this->hwParam=$fullHwParam[$version];
			$this->hwParam['Version']=$version;
			return TRUE;
		}else{//The parameter is not a vali version
			return FALSE;
		}
	}
	
	/**
	 * detect (and set) the hardware version
	 *
	 * @author Fdp1
	 * @return string "" in case of success, guessLog in case of failure
	 */
	function detectHwVersion()
	{
		//Creating Hardware parameters array
		$fullHwParam=$this->getCompatibleHw();

		$detectedHardwareVersion="";
		//Lets get started
		$guessLog="Hardware version guessing test result\r\n";
		//First, let's check if a basic HTTP request on the home page is OK.
		//If not, no need to test further
		$response=$this->somfyWget("/", "get");
		if($response['returnCode']=='1'){
			$guessLog.="Connection to host: FAILED\r\n";
		}else{
			$guessLog.="Connection to host: OK\r\n";
			//We can go further			
			foreach ($fullHwParam as $currentHwVersion => $currentHwParam){
				$guessLog.="HW Version: $currentHwVersion\r\n";
				$response=$this->somfyWget($currentHwParam['URL']['login'], "get");
				if($response['returnCode']=='200'){
					$guessLog.="Login URL recognition: OK\r\n";
					//Let's try to get the authCodeID
					$authCodeID='';
					if(preg_match_all($currentHwParam['Pattern']['Auth'], $response['responseBody'], $authCodeID, PREG_SET_ORDER)==1){
						//it would appear that we got a code. Let's check if it's a valid one
						$guessLog.="Auth code ID grabbing test: OK\r\n";
						if(preg_match ( "/^[A-F][1-5]$/" , $authCodeID[0][1] )){//The codeID is valid (from A1 to F5)
							$guessLog.="Auth code ID Validation test: OK\r\n";
							//Let´s now check that every URL used by this HW version exists
							$failedURL=false;
							foreach ($currentHwParam['URL'] as $currentUrlID => $currentUrl){
								if($currentUrlID=="login"){//no need to test login url again
									continue;
								}
								$response=$this->somfyWget($currentUrl, "get");
								if($response['returnCode']=='404'){
									$guessLog.="Test URL [$currentUrlID]: FAILED\r\n";
									$failedURL=true;
								}else{
									$guessLog.="Test URL [$currentUrlID]: ".$response['returnCode']." OK\r\n";
								}
							}
							if(!$failedURL){
								//all tests passed successfully. We found our HW version. Time to stop testing.
								$guessLog.="Version detected: $currentHwVersion\r\n";
								$detectedHardwareVersion=$currentHwVersion;
								break;
							}
						}else{
							$guessLog.="Auth code ID Validation test: FAILED\r\n";
						}
			
					}else{
						$guessLog.="Auth code ID grabbing test: FAILED\r\n";
					}
				}else{//The loginURL doesn't exist. Bad version
					$guessLog.="Login URL recognition: FAILED\r\n";
				}
			}
		}
		
		
		if ($detectedHardwareVersion){
			$this->setHwVersion($detectedHardwareVersion);
			return "";
		}else{
			return $guessLog;
		}

	}

	/**
	 * Perform an HTTP request on the somfy protexiom.
	 *
	 * @author Fdp1
	 * @param string $url url to fetch
	 * @param string $method HTTP method (GET or POST)
	 * @param string $reqBody (optional) request_body
	 * @return array('returnCode'=>$returnCode, 'responseBody'=>$responseBody, 'responseHeaders'=>$responseHeader)
	 * @usage response = SomfyWget("/login.htm", "POST", array('username' => $login, 'password' => $password))
	 */
	protected function somfyWget($url, $method, $reqBody="")
	{
		$myError="";

		//Let's check we've been requested a valid method
		if (is_string($method)){
			$method=strtoupper($method);
			if ($method=="GET" or $method=="POST"){//Valid method. Let's instantiate the browser
				$curlOpt = array(
						CURLOPT_HEADER => 1,
						CURLOPT_RETURNTRANSFER => 1,
						CURLOPT_FORBID_REUSE => 1,
						//CURLOPT_SSL_VERIFYPEER => 1
				);
				if($this->authCookie){
					$curlOpt += array(CURLOPT_COOKIE => $this->authCookie);
				}

				if ($method=="POST"){
					$curlOpt += array(
							CURLOPT_POST => 1,
							//CURLOPT_POSTFIELDS => http_build_query($reqBody)
							CURLOPT_POSTFIELDS => $reqBody
					);
				}else{//Not POST means GET
					if($reqBody!=NULL){
						//$url.=(strpos($url, '?') === FALSE ? '?' : '').http_build_query($reqBody);
						$url.="?".$reqBody;
					}
				}
				$browser=curl_init();
				curl_setopt_array($browser, $curlOpt);
				curl_setopt($browser, CURLOPT_URL, $this->somfyBaseURL.$url);
				//Let's use a fiddler proxy for debug purpose
				//curl_setopt($browser, CURLOPT_PROXY, "192.168.1.21:8888");

				if( ! $response=curl_exec($browser))
				{
					$myError="CURL Error: ".curl_error($browser);
				}else{
					$http_status = curl_getinfo($browser, CURLINFO_HTTP_CODE);
					list($headers, $body) = explode("\r\n\r\n", $response, 2);
					$headers=$this->http_parse_headers($headers);
				}
				curl_close($browser);
				unset($browser);
			}else{//invalid method
				$myError="Invalid Method";
			}
		}else{//invalid method
			$myError="Invalid Method";
		}

		if($myError==""){//Everything went fine
			return array('returnCode'=>$http_status, 'responseBody'=>$body, 'responseHeaders'=>$headers);
		}else{//Somehow, an error happened
			return array('returnCode'=>'1', 'responseBody'=>$myError, 'responseHeaders'=>array());
		}
	}//End somfyWget func
	
	/**
	 * check if the HTTP(S) request returned the expected response code.
	 *
	 * @author Fdp1
	 * @param array $response somfyWget response
	 * @param string $rcode expected return code
	 * @param string $location expected Location in case of a 302 rcode
	 * @return string error message in case of error, "" in case of sucess
	 * @usage $myError = isWgetError()
	 */
	protected function isWgetError($response, $rcode, $location="")
	{
		$myError="";
		
		if($response['returnCode']==$rcode){
			//we got the expected rcode. If it's a 302, let's check the Location.
			if($rcode=='302'){
				//Let's strip the leading / (if exists) since somfy only put it "sometimes"
				if(preg_replace('/^\//', "", $response['responseHeaders']['Location'])==preg_replace('/^\//', "", $this->hwParam['URL']['Error'])){
					$myError="Somfy protexiom returned : ".$this->getSomfyError();
				}elseif(preg_replace('/^\//', "", !$response['responseHeaders']['Location'])==preg_replace('/^\//', "", $location)){
					$myError="Unknow error (HTTP return code: 302 and Location: ".$response['responseHeaders']['Location'].")";
				}//else we got the Location. $myError=""
			}
		}elseif($response['returnCode']=='1'){
			//SomfyWget returned an error
			$myError=$response['responseBody'];
		}else{
			if($response['returnCode']=='302'){
				//Let's strip the leading / (if exists) since somfy only put it "sometimes"
				if(preg_replace('/^\//', "", $response['responseHeaders']['Location'])==preg_replace('/^\//', "", $this->hwParam['URL']['Error'])){
					$myError="Somfy protexiom returned : ".$this->getSomfyError();					
				}else{
					$myError="Unknow error (HTTP return code: 302 and Location: ".$response['responseHeaders']['Location'].")";
				}
			}else{
				$myError="Unknow error (HTTP return code ".$response['returnCode'].")";
			}
		}
		return $myError;
	}//End isWgetError func
	
	/**
	 * get the error code specified by somfy in case of a 302 redirect to the error page.
	 * Perform th web request to the error page, and parse the response to isolate the error message.
	 *
	 * @author Fdp1
	 * @return string error message, or "" if unable to get the error
	 * @usage $myError = getSomfyError()
	 */
	protected function getSomfyError()
	{
		$somfyError=array();
		$myError="";
	
		$response=$this->somfyWget($this->hwParam['URL']['Error'], "GET");
		foreach ($this->hwParam['Pattern']['Error'] as $currentPatternID => $currentPattern){
			if(preg_match_all($currentPattern, $response['responseBody'], $somfyError, PREG_SET_ORDER)==1){
				// It seems we found an error pattern.
				// Let's replace HTML newlines (<br>) with " "
				$myError=preg_replace('/(?:\<br(\s*)?\/?\>)+/i', " ", $somfyError[0][1]);
				// Lets's remove HTML tag
				$myError=strip_tags($myError);
				$myError=str_replace ("&nbsp;", " ", $myError);
				//Let's remove the somfy error code
				$myError=preg_replace('/\(0x[0-9 a-z A-Z]+\)/s', "", $myError);
				//Let's trim the string and remove duplicates spaces / lineend for clean display
				$myError=trim(str_replace ("/(\s)+/s", " ", $myError));
				//Somfy reply ends with CRLF, and others with LF. LF only are not striped by the precedent line. Lets's remove them explicitely
				$myError=str_replace ("\n", " ", $myError);
				break;	
			}	
		}
		
		return $myError;
	}//End getSomfyError func
	
	/**
	 * Login fonction.
	 * Authenticate and set the authentication cookie
	 *
	 * @author Fdp1
	 * @return string error message in case of error, "" in case of sucess
	 * @usage $myError = doLogin()
	 */
	function doLogin()
	{
		$myError="";
		$authCodeID='';
		
		if(!$this->hwParam['Version']){
			//Hardware version unset. Let's try to get it
			$myError=$this->detectHwVersion();
		}
		if(!$myError){
			//First, let'get the authCodeID
			$response=$this->somfyWget($this->hwParam['URL']['login'], "GET");
			if(!$myError=$this->isWgetError($response, '200')){
				if(preg_match_all($this->hwParam['Pattern']['Auth'], $response['responseBody'], $authCodeID, PREG_SET_ORDER)==1){
					//it would appear that we got a code. Let's check if it's a valid one
					if(preg_match ( "/^[A-F][1-5]$/" , $authCodeID[0][1] )){//The codeID is valid (from A1 to F5)
						//Time to login...
						$reqBody=preg_replace(array("/#UserPwd#/", "/#AuthKey#/"), array($this->userPwd, $this->authCard[$authCodeID[0][1]]), $this->hwParam['ReqBody']['login']);
						$response=$this->somfyWget($this->hwParam['URL']['login'], "POST", $reqBody);
						if(!$myError=$this->isWgetError($response, '302', $this->hwParam['URL']['welcome'])){
							$response=$this->somfyWget($this->hwParam['URL']['welcome'], "GET");
							if(!$myError=$this->isWgetError($response, '200')){
								// Successfull login. Let's store the session cookie
								$this->authCookie=$response['responseHeaders']['Set-Cookie'];
							}//else myError != '', will be returned
						}//else myError != '', will be returned
						
					}else{
						$myError="Invalid auth code ID. Login failed.";
					}
				}else{
					$myError="Unable to get auth code ID";
				}
			}//else myError != '', will be returned
		}
		if($myError){
			return "Login failed: ".$myError;
		}else{
			return "";
		}
	}//End doLogin func
	
	/**
	 * Logout fonction.
	 * Logout and reset the authentication cookie
	 *
	 * @author Fdp1
	 * @return string error message in case of error, "" in case of sucess
	 * @usage $myError = doLogout()
	 */
	function doLogout()
	{
		if(!$myError=$this->isWgetError($this->somfyWget($this->hwParam['URL']['logout'], "GET"), '302', $this->hwParam['URL']['login'])){
			$this->authCookie="";
		}else{
			$myError="Logout failed: ".$myError;
		}
		return $myError;
	}//End doLogout func
	
	/**
	 * pullStatus fonction.
	 * Launch login fonction only if session not already active, and the get the satus informations.
	 * Open and close the session only if it was not already opened.
	 *
	 * @author Fdp1
	 * @return string "" in case of success, $myError in case of failure
	 */
	function pullStatus()
	{
		$sessionHandling = false;
		$myError="";
		
		if(!$this->authCookie){
			//Not logged in. Let's log in now, and set a variable to enable logout before exit
			$sessionHandling = true;
			$myError=$this->doLogin();
		}
		
		if(!$myError){//Login OK
			$response=$this->somfyWget($this->hwParam['URL']['Status'], "GET");
			if($sessionHandling){
				$this->doLogout();
			}
			if(!$myError=$this->isWgetError($response, '200')){
				$xmlStatus=simplexml_load_string($response['responseBody']);
				foreach($this->hwParam['StatusTag'] as $key => $val){
					$this->status[$key]=(string)$xmlStatus->$val;
				}
				$this->status['LastRefresh']=date("Y-m-d H:i:s");
			}//else: $myerror should be returned
		}
		
		return $myError;	
	}//End pullStatus func
	
	/**
	 * getStatus fonction.
	 * Get the protexiom status
	 *
	 * @author Fdp1
	 * @return array status
	 */
	function getStatus()
	{	
		return $this->status;
	}//End getStatus func
	
	/**
	 * doAction function send an action to the alarm.
	 * First open session (Login), then send action(s), gupdate the new status,  and logout.
	 * Open and close the session only if it was not already opened.
	 * Possible Actions :
	 *     ZONEA_ON
	 *     ZONEB_ON
	 *     ZONEC_ON
	 *     ZONEABC_ON
	 *     ALARME_OFF
	 *     LIGHT_ON
	 *     LIGHT_OFF
	 *     SHUTTER_UP
	 *     SHUTTER_DOWN
	 *     SHUTTER_STOP
	 *     RESET_BATTERY_ERR
	 *     RESET_ALARM_ERR
	 *     RESET_LINK_ERR
	 *
	 * @author Fdp1
	 * @param string list of actions (one or multiple actions)
	 * @return string "" in case of success, $myError in case of failure
	 * @usage $myError = doAction("ALARME_OFF", "LIGHT_ON", "SHUTTER_UP")
	 */
	function doAction()
	{
		$sessionHandling = false;
		$myError="";
		
		if(!$this->authCookie){
			//Not logged in. Let's log in now, and set a variable to enable logout before exit
			$sessionHandling = true;
			$myError=$this->doLogin();
		}
		
		if(!$myError){//Login OK
			// multiple actions possible
			foreach(func_get_args() as $key => $val){
				if(array_key_exists($val , $this->hwParam['Pilotage'])){
					$response=$this->somfyWget($this->hwParam['URL']['Pilotage'], "POST",$this->hwParam['Pilotage'][$val]);
					$myError.=$this->isWgetError($response, '200');
				}elseif(array_key_exists($val , $this->hwParam['EraseDefault'])){
					$response=$this->somfyWget($this->hwParam['URL']['EraseDefault'], "POST", $this->hwParam['EraseDefault'][$val]);
					$myError.=$this->isWgetError($response, '200');
				}else{
					$myError.="Unable to perform the action \"$val\": invalid action.\r\n";
				}
			}
			$myError.=$this->pullStatus();
			
			if($sessionHandling){
				$this->doLogout();
			}
		}
		
		return $myError;
	}//End doAction func

}//End phpProtexiom Class
?>
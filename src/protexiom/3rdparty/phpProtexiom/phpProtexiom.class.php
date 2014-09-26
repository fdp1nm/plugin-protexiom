<?php
class phpProtexiom {
	/*     * *************************Attributs privés****************************** */

	//private $_somfyStatus = array();
	/* 	private $_SomfyHost = '';
	 private $_SomfyPort = ''; */
	private $somfyBaseURL='';
	private $sslEnabled = false;
	// TODO test SSL
	private $hwParam=array("Version"  => ""); //Store pretexiom hardware versions parameters
	//private $_webProxyHost = '';
	//private $_webProxyPort = '';

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
	private static function http_parse_headers( $header )
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
	 * Remove the first char of a string if it's a /
	 * 
	 * @author Fdp1
	 * @param strinf string2strip,
	 * @return strippedString <string>
	 * @usage $strippedString = StripLeadingSlash($string2strip);
	 */
	private static function stripLeadingSlash($string2strip)
	{
		if (substr($string2strip, 1 , 1) == "/"){
			return substr($string2strip, 2);
		}else{
			return $string2strip;
		}
	}
	
	/**
	 * Get the hardware compatibility
	 *
	 * @author Fdp1
	 * @return array compatible hardware versions, and their parameters
	 */
	private static function getCompatibleHw()
	{
		//Creating Hardware parameters array
		$fullHwParam=array();
		//Version 1
		$fullHwParam['1']['AuthPattern']="#Code d'authentification (..)</td>#";
		$fullHwParam['1']['URL']['login']="/login.htm";
		$fullHwParam['1']['URL']['logout']="/logout.htm";
		$fullHwParam['1']['URL']['welcome']="/welcome.htm";
		$fullHwParam['1']['URL']['loginError']="/error.htm";
		$fullHwParam['1']['ReqBody']['login']="login=u&password=#UserPwd#&key=#AuthKey#&action=Connexion";
		//Version 2
		$fullHwParam['2']['AuthPattern']="#<b>(..)</b>#";
		$fullHwParam['2']['URL']['login']="/fr/m_login.htm";
		$fullHwParam['2']['URL']['logout']="/m_logout.htm";
		$fullHwParam['2']['URL']['welcome']="/fr/mu_welcome.htm";
		$fullHwParam['2']['URL']['loginError']="/fr/m_error.htm";
		$fullHwParam['2']['ReqBody']['login']="login=u&password=#UserPwd#&key=#AuthKey#&btn_login=Connexion";
		//Version 3
		$fullHwParam['3']['AuthPattern']="#<b>(..)</b>#";
		$fullHwParam['3']['URL']['login']="/m_login.htm";
		$fullHwParam['3']['URL']['logout']="/m_logout.htm";
		$fullHwParam['3']['URL']['welcome']="/mu_welcome.htm";
		$fullHwParam['3']['URL']['loginError']="/m_error.htm";
		$fullHwParam['3']['ReqBody']['login']="login=u&password=#UserPwd#&key=#AuthKey#&action=Connexion&img.x=51&img.y=14";
		//Version 4
		//V4 MUST be declared after V2, otherwise to avoid a false positive
		//V2 Hw would be positive to V2 test, but would then be broken
		$fullHwParam['4']['AuthPattern']="#<b>(..)</b>#";
		$fullHwParam['4']['URL']['login']="/fr/login.htm";
		$fullHwParam['4']['URL']['logout']="/logout.htm";
		$fullHwParam['4']['URL']['welcome']="/fr/welcome.htm";
		$fullHwParam['4']['URL']['loginError']="/fr/error.htm";
		$fullHwParam['4']['ReqBody']['login']="login=u&password=#UserPwd#&key=#AuthKey#&btn_login=Connexion";
		/* ActionsParam.Url = "/fr/u_pilotage.htm"
		LogoutUrl = "/logout.htm" */
		
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
					if(preg_match_all($currentHwParam['AuthPattern'], $response['responseBody'], $authCodeID, PREG_SET_ORDER)==1){
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
	private function somfyWget($url, $method, $reqBody="")
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

				if( ! $response=curl_exec($browser))
				{
					$myError=curl_error($browser);
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
			if($response['returnCode']=='200'){
				if(preg_match_all($this->hwParam['AuthPattern'], $response['responseBody'], $authCodeID, PREG_SET_ORDER)==1){
					//it would appear that we got a code. Let's check if it's a valid one
					if(preg_match ( "/^[A-F][1-5]$/" , $authCodeID[0][1] )){//The codeID is valid (from A1 to F5)
						//Time to login...
						$reqBody=preg_replace(array("/#UserPwd#/", "/#AuthKey#/"), array($this->userPwd, $this->authCard[$authCodeID[0][1]]), $this->hwParam['ReqBody']['login']);
						$response=$this->somfyWget($this->hwParam['URL']['login'], "POST", $reqBody);
						if($response['returnCode']=='302' AND $response['responseHeaders']['Location']==$this->hwParam['URL']['welcome']){
							$response=$this->somfyWget($this->hwParam['URL']['welcome'], "GET");
							if($response['returnCode']=='200'){
								// Successfull login. Let's store the session cookie
								$this->authCookie=$response['responseHeaders']['Set-Cookie'];
							}elseif($response['returnCode']=='1'){
								//SomfyWget returned an error
								$myError=$response['responseBody'];
							}else{
								$myError="Login failed with unknow error (HTTP return code ".$response['returnCode'].")";
							}
						}elseif($response['returnCode']=='302' AND $response['responseHeaders']['Location']==$this->hwParam['URL']['loginError']){
							$myError="Login failed. Alarm busy, or wrong password";
						}elseif($response['returnCode']=='1'){
							$myError=$response['responseBody'];
						}else{
							$myError="Login failed with unknow error (HTTP return code ".$response['returnCode'].")";
						}
					}else{
						$myError="Invalid auth code ID. Login failed.";
					}
				}else{
					$myError="Login failed. Unable to get auth code ID";
				}
			}else{
				$myError="Connection to login page failed";
			}
		}
		if($myError){
			return $myError;
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
		$myError="";
		$response=$this->somfyWget($this->hwParam['URL']['logout'], "GET");
		if($response['returnCode']=='302' AND $response['responseHeaders']['Location']==$this->hwParam['URL']['login']){
			$this->authCookie="";
			return "";
		}elseif($response['returnCode']=='1'){
			//SomfyWget returned an error
			$myError=$response['responseBody'];
		}else{
			$myError="Logout failed with unknow error (HTTP return code ".$response['returnCode'].")";
		}
	}//End doLogout func

}//End phpProtexiom Class
?>
  <?php

/**
 * SMS
 *
 * Class used to send sms messages with Netopia's sms service at www.web2sms.ro
 *
 * @author     Andrei 
 * @email      andrei_pericleanu@yahoo.com 
 *
 */
class SMS {

    /**
     * @var string - wsdl
     */
    protected $endpoint = 'https://www.web2sms.ro/wsi/service.php?wsdl';

    /**
     * @var SoapClient
     */
    protected $client;

    /**
     * @var string
     */
    protected $error;

    /**
     * @var SOAPFault
     */
    protected $soapFault;

    /**
     * @var string
     */
    protected $username;

    /**
     * @var string
     */
    protected $authKey;

    /**
     * @var string
     */
    protected $password;

    /**
     * @var string
     */
    protected $sender;

    /**
     * @var string
     */
    protected $callBack;

    /**
     * @var bool
     */
    protected $isUnicode;

    /**
     * @var string
     */
    protected $lastSmsId;

    /**
     * @var string
     */
    protected $sessId = null;

    /**
     * @var int
     */
    protected $validity;


    /**
     * SMS constructor.
     * @param string $username (optional)   - client username
     * @param string $password (optional)   - client password
     * @param string $authKey (optional)   - client password
     * @param string $sender (optional)     - client phone number
     * @param string $callBack (optional)   - public url where the delivery reports will be sent (you should add identifier for each individual SMS message)
     * @param bool $isUnicode (optional)    - if true, unicode encoding will be used (diacritics can be used but the sms length cannot exceed 70 chars)
     * @param int $validity (optional)      - period (minutes) until the sms can be sent. After this period the sms will not be send and client will not be charged
     *
     * @throws Exception
     */
    public function __construct($username = '',$password = '',$authKey = '',$sender = '',$callBack = null,$isUnicode = false,$validity = 0){
        if(class_exists('SoapClient')){
            $this->client = new SoapClient($this->endpoint); 
            $this->username = !empty($this->username) ? $this->username : $username; 
            $this->password = !empty($this->password) ? $this->password : $password; 
            $this->authKey =  !empty($this->authKey) ? $this->authKey :  $authKey;
            $this->sender = $sender;
            $this->callBack = $callBack;
            $this->isUnicode = (bool)$isUnicode;
            $this->validity = (int)$validity;
        }else{
            throw new Exception('SoapClient extension not enabled !');
        }
    }

    /*
     *  METHODS
     */

    /**
     * Send SMS using username and authKey
     *
     * @param string $recipientNr               - phone number of the receiver
     * @param string $body                      - sms text with length > 0 AND length <= 160
     * @param string $callbackUrl (optional)    - public url where the delivery reports will be sent (you should add identifier for each individual SMS message)
     * @param string $scheduledDate (optional)  - date in when the sms is scheduled to be sent, with format YYYY-MM-DD THH:MM:SS.mmmZ or  SQL DateTime format (YYYY-MM-DD HH:MM:SS)
     * @param int    $validity (optional)       - period (minutes) until the sms can be sent. After this period the sms will not be send and client will not be charged
     *
     * @return bool                             - true on success, false on failure
     */

    public function sendSMS($recipientNr,$body,$callbackUrl = null,$scheduledDate = null,$validity = 0){
        $callback = $callbackUrl ? $callbackUrl : $this->callBack;

        //number format check
        $recipientNr = trim($recipientNr,'+');
        if($this->incorrectNumber($recipientNr)){return false;};

        //handle validity
        if(empty($validity) || ! is_numeric($validity)){
            $validity = $this->validity;
        }

        //check SMS length
        if($this->invalidSMSLength($body)){return false;}

        try{
            //sendSMS
            if($callback){
                $result = $this->client->sendSmsAuthKey($this->username,$this->authKey,$this->sender,$recipientNr,$body,$scheduledDate,$validity,$callback);
            }else{
                $result = $this->client->sendSmsAuthKey($this->username,$this->authKey,$this->sender,$recipientNr,$body,$scheduledDate,$validity);
            }

            //get results
            if(strlen($result) >= 32 && strlen($result) <= 60 ){
                //valid response
                $this->lastSmsId = $result;
                return true;
            }else{
                $this->setError($result);
                return false;
            }

        }catch(Exception $e){
            $this->setError($e);
            return false;
        }
    }

    /**
     * Send a simple SMS using username and password as auth
     *
     * @param string $recipientNr               - phone number of the receiver
     * @param string $body                      - sms text with length > 0 AND length <= 160
     * @param string $callbackUrl (optional)    - public url where the delivery reports will be sent (you should add identifier for each individual SMS message)
     * @param string $scheduledDate (optional)  - date in when the sms is scheduled to be sent, with format YYYY-MM-DD THH:MM:SS.mmmZ or  SQL DateTime format (YYYY-MM-DD HH:MM:SS)
     * @param bool   $isUnicode (optional)      - if true, unicode encoding will be used (diacritics can be used but the sms length cannot exced 70 chars)
     *
     * @return bool                             - true on success, false on failure
     */
    public function sendSimpleSMS($recipientNr,$body,$callbackUrl = null,$scheduledDate = null,$isUnicode = false){
        $callback = $callbackUrl ? $callbackUrl : $this->callBack;
        $this->isUnicode = (bool)$isUnicode;

        //number format check
        $recipientNr = trim($recipientNr,'+');
        if($this->incorrectNumber($recipientNr)){return false;};

        //check SMS length
        if($this->invalidSMSLength($body,$isUnicode)){return false;}

        try{
            //sendSMS
            if($callback){
                $result = $this->client->sendSMS($this->username,$this->password,$this->sender,$recipientNr,$body,$this->isUnicode,$scheduledDate,$callback);
            }else{
                $result = $this->client->sendSMS($this->username,$this->password,$this->sender,$recipientNr,$body,$this->isUnicode,$scheduledDate);
            }

            //get results
            if(strlen($result) >= 32 && strlen($result) <= 60 ){
                //valid response
                $this->lastSmsId = $result;
                return true;
            }else{
                $this->setError($result);
                return false;
            }

        }catch(Exception $e){
            $this->setError($e);
            return false;
        }
    }



    /**
     * Send SMS during a session (session must be opened beforehand)
     *
     * @param string $recipientNr               - phone number of the receiver
     * @param string $body                      - sms text with length > 0 AND length <= 160
     * @param string $scheduledDate (optional)  - date in when the sms is scheduled to be sent, with format YYYY-MM-DD THH:MM:SS.mmmZ or  SQL DateTime format (YYYY-MM-DD HH:MM:SS)
     * @param int    $validity (optional)       - period (minutes) until the sms can be sent. After this period the sms will not be send and client will not be charged
     *
     * @return bool                             - true on success, false on failure
     */
    public function sendSessionSMS($recipientNr,$body,$scheduledDate = null,$validity = 0){

        if(!$this->sessId){
            $this->error = 'No session opened';
            return false;
        }

        //handle validity
        if(empty($validity) || ! is_numeric($validity)){
            $validity = $this->validity;
        }

        //number format check
        $recipientNr = trim($recipientNr,'+');
        if($this->incorrectNumber($recipientNr)){ return false;};

        //check SMS length
        if($this->invalidSMSLength($body)){return false;}

        try{
            //send SMS
            $result = $this->client->sendSession($this->sessId,$recipientNr,$body,$scheduledDate,$this->sender,$validity);

            //get results
            if(strlen($result) >= 32 && strlen($result) <= 60 ){
                //valid response
                $this->lastSmsId = $result;
                return true;
            }else{
                $this->setError($result);
                return false;
            }

        }catch(Exception $e){
            $this->setError($e->getMessage());
            return false;
        }
    }

    /**
     * @return bool  - false if error
     */
    public function openSession(){
        if($this->sessId){ return $this;}
        try{
            $result = $this->client->openSession($this->username,$this->password);
            if(is_string($result)){
                $this->sessId = $result;
            };
            return true;
        }catch(Exception $e){
            $this->setError($e->getMessage());
            return false;
        }

    }

    /**
     * @return bool
     */
    public function sessionIsOpened(){
        if($this->sessId){
            return true;
        }else{
            return false;
        }
    }

    /**
     * @return SMS Object
     */
    public function closeSession(){
        if($this->sessId){
            $this->client->closeSession($this->sessId);
            $this->sessId = null;
        }
        return $this;
    }

    /**
     * Send a simple WapPush SMS
     *
     * @param string $recipientNr               - phone number of the receiver
     * @param string $url                       - the url specific to WapPush
     * @param string $body                      - sms text with length > 0 AND length <= 160
     * @param string $scheduledDate (optional)  - date in when the sms is scheduled to be sent, with format YYYY-MM-DD THH:MM:SS.mmmZ or  SQL DateTime format (YYYY-MM-DD HH:MM:SS)
     * @param int    $validity (optional)       - period (minutes) until the sms can be sent. After this period the sms will not be send and client will not be charged
     *
     * @return bool                             - true on success, false on failure
     */
    public function sendWapPushMessage($recipientNr,$url,$body,$scheduledDate = null,$validity = 0){

        //handle validity
        if(empty($validity) || ! is_numeric($validity)){
            $validity = $this->validity;
        }

        //number format check
        $recipientNr = trim($recipientNr,'+');
        if($this->incorrectNumber($recipientNr)){return false;};

        //check SMS length
        if($this->invalidSMSLength($body)){return false;}

        try{
            //send SMS
            $result = $this->client->sendWapPush($recipientNr,$url,$body,$scheduledDate,$this->sender,$validity);

            //get results
            if(strlen($result) >= 32 && strlen($result) <= 60 ){
                //valid response
                $this->lastSmsId = $result;
                return true;
            }else{
                $this->setError($result);
                return false;
            }

        }catch(Exception $e){
            $this->setError($e->getMessage());
            return false;
        }
    }

    /**
     * Send a simple WapPush SMS
     *
     * @param string $recipientNr               - phone number of the receiver
     * @param string $url                       - the url specific to WapPush
     * @param string $body                      - sms text with length > 0 AND length <= 160
     * @param string $scheduledDate (optional)  - date in when the sms is scheduled to be sent, with format YYYY-MM-DD THH:MM:SS.mmmZ or  SQL DateTime format (YYYY-MM-DD HH:MM:SS)
     * @param int    $validity (optional)       - period (minutes) until the sms can be sent. After this period the sms will not be send and client will not be charged
     *
     * @return bool                             - true on success, false on failure
     */
    public function sendSessionWapPushMessage($recipientNr,$url,$body,$scheduledDate = null,$validity = 0){

        if(!$this->sessId){
            $this->error = 'No session opened';
            return false;
        }

        //handle validity
        if(empty($validity) || ! is_numeric($validity)){
            $validity = $this->validity;
        }

        //number format check
        $recipientNr = trim($recipientNr,'+');
        if($this->incorrectNumber($recipientNr)){return false;};

        //check SMS length
        if($this->invalidSMSLength($body)){return false;}

        try{
            //send SMS
            $result = $this->client->sendWapPush($this->sessId,$recipientNr,$url,$body,$scheduledDate,$this->sender,$validity);

            //get results
            if(strlen($result) >= 32 && strlen($result) <= 60 ){
                //valid response
                $this->lastSmsId = $result;
                return true;
            }else{
                $this->setError($result);
                return false;
            }

        }catch(Exception $e){
            $this->setError($e->getMessage());
            return false;
        }
    }
    
    /**
     * @param $number - telephone number to be checked
     *
     * @return bool
     */
    protected function incorrectNumber($number){
        $not_numeric = !is_numeric($number);
        //checking the formats accepted by the service provider
        $not_format_1 = ! (strpos($number, '07') === 0 && strlen($number) == 10);
        $not_format_2 = ! (strpos($number, '407') === 0 && strlen($number) == 11);
        $not_format_3 = ! (strpos($number, '7') === 0 && strlen($number) == 9);

        if($not_numeric || ($not_format_1 && $not_format_2 && $not_format_3) ){
            $this->error = "Incorrect format for phone number: ".$number;
            return true;
        }
        return false;
    }

    protected function invalidSMSLength($body,$isUnicode = false){
        if(strlen($body) > 160 || ($this->isUnicode && strlen($body) > 70)){
            $this->error = 'Maximum SMS length exceeded';
            return false;
        }
        if(empty($body)){
            $this->error = 'No message';
            return false;
        }
    } 

    /**
     * sets the error (optionally the soapFault) property of the SMS class
     *
     * @param mixed $error -> string or SOAPFault to be set
     */
    protected function setError($error){
        if(is_a($error,'SOAPFault')){
            $this->soapFault = $error;
            $this->error = $error->getMessage();
        }else{
            //non soap fault error
            if(is_string($error)){
                $this->error = $error;
            }else{
                $this->error = 'Unknown error !';
            }
        }
    }

    /**
     * @param string $sender
     */
    public function setSender($sender)
    {
        $this->sender = $sender;
    }

 
    /**
     * @return string
     */
    public function getLastSmsId()
    {
        return $this->lastSmsId;
    }

    /**
     * @return string
     */
    public function getError()
    {
        return $this->error;
    }

    /**
     * @return SOAPFault
     */
    public function getSoapFault()
    {
        return $this->soapFault;
    }

    /**
     * @return SOAPClient
     */
    public function getClient()
    {
        return $this->client;
    }

    /**
     * @return string
     */
    public function getSessId()
    {
        return $this->sessId;
    }
}


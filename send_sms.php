require_once ('Sms.php');

//we assume credentials were hardcoded in the sms class
$sms = new SMS();

if($sms->sendSMS('0000000000','my_msg','http://mycallback.url')){
  echo 'The message has been sent !';   
}else{
  echo $sms->getError();
};

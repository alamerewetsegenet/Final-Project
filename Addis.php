<?php
$siteKey = "6LeDaSoUAAAAACnEiqA3QAkiRU-Q_wtk0vuBa_OX";
$secretKey = "6LeDaSoUAAAAACJ69mIHYOxL4atri9oPrjkIVMFv";
date_default_timezone_set('America/Los_Angeles'); #sets default date/timezone for this website
$server = 'hostgator.com';
//end config area ----------------------------------------

spl_autoload_register('MyAutoLoader::NamespaceLoader');#will check subfolders as namespaces
include 'ReCaptcha/ReCaptcha.php'; #required reCAPTCHA class code 
if(
    !isset($siteKey) || 
    !isset($secretKey) || 
    $siteKey == ''  ||  
    $secretKey == ''
)      
{//siteKeys not provided - exit
    echo '<p>Please go into the contact_include.php file and place 
    the <b>$siteKey</b> and <b>$secretKey</b> for the domain where your forms 
    will be posted.</p>';
    die;
}

function loadContact($form,$feedback='')
{
    global $toName,$toAddress,$website,$siteKey,$secretKey,$server;
    
    if($toAddress=='' || $toAddress == 'name@example.com')
    {
        echo '<p>Please place a real email into the variable named <b>$toAddress</b> on your web page.</p>';
        die;
    }

    //fields to skip in email message
    $skipFields = 'g-recaptcha-response,Email';
    if($feedback == '')
    {
        $feedback = 'feedback.php';
    }
    
    if (isset($_POST['g-recaptcha-response'])):
    // If the form submission includes the "g-captcha-response" field
    // Create an instance of the service using your secret
    $recaptcha = new \ReCaptcha\ReCaptcha($secretKey);

    // Make the call to verify the response and also pass the user's IP address
    $resp = $recaptcha->setExpectedHostname($_SERVER['SERVER_NAME'])
                      ->verify($_POST['g-recaptcha-response'], $_SERVER['REMOTE_ADDR']);
    if ($resp->isSuccess()):
        // If the response is a success, process data!
        $aSkip = explode(",",$skipFields); #split form elements to skip into array
        $postData = show_POST($aSkip);#loops through and creates select POST data for display/email
        $fromAddress = "";//default
        if(is_email($_POST['Email']))
        {#Only use Email for return address if valid
            $fromAddress = $_POST['Email'];
            # extra email injector paranoia courtesy of DH: http://wiki.dreamhost.com/PHP_mail()#Mail_Header_Injection
            $fromAddress = preg_replace("([\r\n])", "", $fromAddress);
        }

        if(isset($_POST['Name'])){$Name = $_POST['Name'];}else{$Name = "";} #Name, if used part of subject

        if($Name != ""){$SubjectName = " from: " . $Name . ",";}else{$SubjectName = "";} #Name, if used part of subject
        $postData = str_replace("<br />",PHP_EOL . PHP_EOL,$postData);#replace <br /> tags with double c/r
        $Subject= $website . " message" . $SubjectName . " " . date('F j, Y g:i a');
        $txt =  $Subject . PHP_EOL . PHP_EOL  . $postData; 
        
        //optional identification of name of email server reduces chance of being identified as spam
        if($server==''){
            $server=$_SERVER["SERVER_NAME"];
        }
         email_handler($toAddress,$toName,$Subject,$txt,$fromAddress,$Name,$website,$server);

        //show feedback
        include_once $feedback;
    else:
        // If it's not successful, then one or more error codes will be returned.
        //show form
        include_once $form;
        include_once 'ReCaptcha/js_includes.php'; #hides JS
    endif;
else:
    // Add the g-recaptcha tag to the form you want to include the reCAPTCHA element
    include_once $form;
    include_once 'ReCaptcha/js_includes.php'; #hides JS
endif;

}//end loadContact()

/**
 * formats PHP POST data to text for email, feedback
 * 
 * @param Array $aSkip array of POST elements to be skipped
 * @return string text of all POST elements & data, underscores removed
 * @todo none
 */
function show_POST($aSkip)
{#formats PHP POST data to text for email, feedback
	$myReturn = ""; #init return var
	foreach($_POST as $varName=> $value)
	{#loop POST vars to create JS array on the current page - include email
	 	if(!in_array($varName,$aSkip) || $varName == 'Email')
	 	{#skip passover elements
	 		$strippedVarName = str_replace("_"," ",$varName);#remove underscores
			if(is_array($_POST[$varName]))
		 	{#checkboxes are arrays, and we need to loop through each checked item to insert
		 	    $myReturn .= $strippedVarName . ": " . sanitize_it(implode(",",$_POST[$varName])) . "<br />";
	 		}else{//not an array, create line
	 			$strippedValue = nl_2br2($value); #turn c/r to <br />
	 			$strippedValue = str_replace("<br />","~!~!~",$strippedValue);#change <br /> to our 'unique' string: "~!~!~"
	 			//sanitize_it() function commented out as it can cause errors - see word doc
	 			//$strippedValue = sanitize_it($strippedValue); #remove hacker bits, etc. 
	 			$strippedValue = str_replace("~!~!~","\n",$strippedValue);#our 'unique string changed to line break
	 			$myReturn .= $strippedVarName . ": " . $strippedValue . "<br />"; #
	 		}
		}
	}
	return $myReturn;
}#end show_POST()

 * @param string $str data as entered by user
 * @return data returned after 'sanitized'
 * @todo none
 */
function sanitize_it($str)
{#We would like to trust the user, and aren't using a DB, but we'll limit input to alphanumerics & punctuation
	$str = strip_tags($str); #remove HTML & script tags	
	$str = preg_replace("/[^[:alnum:][:punct:]]/"," ",$str);  #allow alphanumerics & punctuation - convert the rest to single spaces
	return $str;
}#end sanitize_it()


 * @param string $str data as entered by user
 * @return boolean returns true if matches pattern.
 * @todo none
 */
function is_email($myString)
{
  if(preg_match("/^[a-zA-Z0-9_\-\.]+@[a-zA-Z0-9_\-]+\.[a-zA-Z0-9_\-]+$/",$myString))
  {return true;}else{return false;}
}#end is_email()

 * @param string $text Data from DB to be loaded into <textarea>
 * @return string Data stripped of <br /> tag variations, replaced with new line 
 * @todo none 
 */
function br_2nl($text)
{
	$nl = "\n";   //new line character
    $text = str_replace("<br />",$nl,$text);  //XHTML <br />
    $text = str_replace("<br>",$nl,$text); //HTML <br>
    $text = str_replace("<br/>",$nl,$text); //bad break!
    return $text;
    
}
 * @param string $text Data from DB to be loaded into <textarea>
 * @return string Data stripped of <br /> tag variations, replaced with new line 
 * @todo none
 */
function nl_2br2($text)
{
	$text = str_replace(array("\r\n", "\r", "\n"), "<br />", $text);
	return $text;
}#end nl2br2()

function email_handler($toEmail,$toName,$subject,$body,$fromEmail,$fromName,$website,$domain)
{
	$debug=false;//true may show message
	if($fromName==""){$fromName = $website;} //default to website if name not provided
	$headers[] = "MIME-Version: 1.0";
	$headers[] = "Content-type: text/plain; charset=iso-8859-1";
	$headers[] = "From: {$fromName} <noreply@{$domain}>";
    
	if(isset($fromEmail) && $fromEmail != "")
	{//only add reply info if provided
		$headers[] = "Reply-To: {$fromName} <{$fromEmail}>";
	}
	$headers[] = "Subject: {$subject}";
	$headers[] = "X-Mailer: PHP/".phpversion();
	
    //target of form
	$toEmail = 'To:' . $toName . ' <' . $toEmail . '>'; 
	if(@mail($toEmail, $subject, $body, implode(PHP_EOL, $headers)))
	{//only echo if debug is true
		if($debug){echo 'Email sent! ' . date("m/d/y, g:i A");}
	}else{
		if($debug){echo 'Email NOT sent! Unknown error. ' . date("m/d/y, g:i A");}	
	}	

}

class MyAutoLoader
{
    
	public static function NamespaceLoader($class)
    {
       
		$path = str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $class);
        $path = __DIR__ . '/' . $path . '.php';
		//if file exists, include and load class file
		if (file_exists($path)) {
			include $path;
			return; //go no farther
		}else{
            echo 'include file not found!';
            die;
        }
    }#end NamespaceLoader()

}#end MyAutoLoader class

?>


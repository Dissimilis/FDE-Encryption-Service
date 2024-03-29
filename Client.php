<?php


define('AUTH_KEY', 'xxx');
define('GETKEY_ENDPOINT', 'https://encryption.xxx/getkey');
define('NOTIFICATION_URL', 'https://encryption.xxx/enterkey');
define('OTP_FILE', 'otp.key');
define('OTP_LEN', 6);
define('CHECK_DELAY', 5); //how many seconds to wait between key quries

define('PUSOVER_TOKEN', 'xxx');
define('PUSOVER_USER_KEY', 'xxx');
define('PUSOVER_DEVICE', null); //specific device to send to

// Function to generate OTP and save it to a file if not already present
function generate_otp() {
    if (!file_exists(OTP_FILE)) {
        $otp = substr(str_shuffle(str_repeat('ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789', OTP_LEN)), 0, OTP_LEN);
        file_put_contents(OTP_FILE, $otp);
        return true; // OTP generated
    }
    return false; // OTP already present
}

function notify($title, $message, $priority = 0, $sound = 'gamelan') {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://api.pushover.net/1/messages.json");
    curl_setopt($ch, CURLOPT_POSTFIELDS, [
        "token" => PUSOVER_TOKEN,
        "user" => PUSOVER_USER_KEY,
        "device" => PUSOVER_DEVICE,
        "priority" => $priority,
        "title" => $title,
        "sound" => $sound,
        "html" => 1,
		"url" => NOTIFICATION_URL,
        "message" => $message,
    ]);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SAFE_UPLOAD, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    $error = curl_error($ch);
    if ($error) {
        logMessage("cURL Error: " . $error.PHP_EOL);
    }
    curl_close($ch);
}

function check_getkey_endpoint($data) {
    while (true) {
        $postData = [
            'auth' => AUTH_KEY,
            'otp' => file_get_contents(OTP_FILE),
            'data' => $data,
        ];

        $ch = curl_init(GETKEY_ENDPOINT);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
        // Disable SSL certificate verification
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        // Include header in the output for status code check
        curl_setopt($ch, CURLOPT_HEADER, true);

        $response = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE); // Get HTTP status code
        $err = curl_error($ch);
        curl_close($ch);
		
		$body = "";
		if ($response !== false)
			list($header, $body) = explode("\r\n\r\n", $response, 2);        
		if (!$err && $response !== false && $httpcode == 200) {
            
            $encryption_key = trim($body);
            
            logMessage("Array unlocked with encryption key: $encryption_key".PHP_EOL);

            // Delete the OTP file after successful retrieval of encryption key
            unlink(OTP_FILE);
            break;
            
        } else {
            logMessage("Error retrieving encryption key: $err $body".PHP_EOL);
        }
        sleep(CHECK_DELAY);
    }
}

function logMessage($message)
{
    $message = date("Y-m-d H:i:s") . " $message".PHP_EOL;
    print($message);
    flush();
    ob_flush();
}
function getAdditionalData()
{
	return json_encode ($_SERVER, JSON_PRETTY_PRINT);
}

$data = getAdditionalData();
if (generate_otp()) {
    $message = "<b>OTP:</b> " . file_get_contents(OTP_FILE);
    notify("NAS booted",$message.PHP_EOL.PHP_EOL.$data, 0, "bicycle");
}
check_getkey_endpoint($data);


?>

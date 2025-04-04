<?php

$userAuthPassword = getenv('USER_AUTH_PASSWORD') ?: 'abc';
$clientAuthKey = getenv('CLIENT_AUTH_KEY') ?: 'xyz';
$aesKey = getenv('AES_KEY') ?: 'xxx';
$logFilePath = getenv('LOG_FILE_PATH') ?: 'log.txt';

// Rate limiting settings
define('RATE_LIMIT_PER_MINUTE', 60);
define('RATE_LIMIT_PER_HOUR', 300);
define('RATE_LIMIT_FILE', 'rate_limits.json');

/**
 * Simple rate limiting function
 * Limits requests to RATE_LIMIT_PER_MINUTE per minute and RATE_LIMIT_PER_HOUR per hour per IP
 */
function checkRateLimit() {
    // Get client IP address
    $clientIp = $_SERVER['REMOTE_ADDR'];
    if (isset($_SERVER['HTTP_X_FORWARDED_FOR']) && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $clientIp = $_SERVER['HTTP_X_FORWARDED_FOR'];
    }
    
    // Load existing rate limit data
    $rateLimits = [];
    if (file_exists(RATE_LIMIT_FILE)) {
        $fileContent = file_get_contents(RATE_LIMIT_FILE);
        if ($fileContent !== false) {
            $rateLimits = json_decode($fileContent, true) ?: [];
        }
    }
    
    // Get current time
    $currentTime = time();
    
    // Initialize or get rate limit data for this IP
    if (!isset($rateLimits[$clientIp])) {
        $rateLimits[$clientIp] = [
            'minute_count' => 0,
            'minute_start' => $currentTime,
            'hour_count' => 0,
            'hour_start' => $currentTime
        ];
    }
    
    // Check and reset minute counter if needed
    if ($currentTime - $rateLimits[$clientIp]['minute_start'] >= 60) {
        $rateLimits[$clientIp]['minute_count'] = 0;
        $rateLimits[$clientIp]['minute_start'] = $currentTime;
    }
    
    // Check and reset hour counter if needed
    if ($currentTime - $rateLimits[$clientIp]['hour_start'] >= 3600) {
        $rateLimits[$clientIp]['hour_count'] = 0;
        $rateLimits[$clientIp]['hour_start'] = $currentTime;
    }
    
    // Increment counters
    $rateLimits[$clientIp]['minute_count']++;
    $rateLimits[$clientIp]['hour_count']++;
    
    // Check if limits are exceeded
    if ($rateLimits[$clientIp]['minute_count'] > RATE_LIMIT_PER_MINUTE) {
        logMessage("Rate limit exceeded (per minute) for IP: $clientIp", false);
        handleError(429, 'Too Many Requests - Rate limit exceeded. Please try again later.');
    }
    
    if ($rateLimits[$clientIp]['hour_count'] > RATE_LIMIT_PER_HOUR) {
        logMessage("Rate limit exceeded (per hour) for IP: $clientIp", false);
        handleError(429, 'Too Many Requests - Hourly rate limit exceeded. Please try again later.');
    }
    
    // Clean up old entries (older than 2 hours)
    foreach ($rateLimits as $ip => $data) {
        if ($currentTime - $data['hour_start'] > 7200) {
            unset($rateLimits[$ip]);
        }
    }
    
    // Save updated rate limit data
    file_put_contents(RATE_LIMIT_FILE, json_encode($rateLimits, JSON_PRETTY_PRINT));
    
    return true;
}

function encryptData($data, $password) {
    // Generate a secure, random salt
    $salt = random_bytes(SODIUM_CRYPTO_PWHASH_SALTBYTES);

    // Derive the encryption key using Argon2id
    $encryptionKey = sodium_crypto_pwhash(
        1024,
        $password,
        $salt,
        SODIUM_CRYPTO_PWHASH_OPSLIMIT_SENSITIVE*2,
        SODIUM_CRYPTO_PWHASH_MEMLIMIT_SENSITIVE,
        SODIUM_CRYPTO_PWHASH_ALG_ARGON2ID13
    );

    // Generate a random IV for AES-256-CBC
    $ivLength = openssl_cipher_iv_length('AES-256-CBC');
    $iv = openssl_random_pseudo_bytes($ivLength);

    // Encrypt the data
    $encryptedData = openssl_encrypt($data, 'AES-256-CBC', $encryptionKey, 0, $iv);

    // Return the salt, IV, and the encrypted data, all base64-encoded
    return base64_encode($salt . $iv . $encryptedData);
}

function decryptData($encryptedInput, $password) {
    // Decode the base64-encoded input
    $data = base64_decode($encryptedInput);

    // Extract the salt, IV, and the encrypted data
    $saltLength = SODIUM_CRYPTO_PWHASH_SALTBYTES;
    $salt = substr($data, 0, $saltLength);
    $ivLength = openssl_cipher_iv_length('AES-256-CBC');
    $iv = substr($data, $saltLength, $ivLength);
    $encryptedData = substr($data, $saltLength + $ivLength);

    // Derive the encryption key using Argon2id
    $encryptionKey = sodium_crypto_pwhash(
        1024,
        $password,
        $salt,
        SODIUM_CRYPTO_PWHASH_OPSLIMIT_SENSITIVE*2,
        SODIUM_CRYPTO_PWHASH_MEMLIMIT_SENSITIVE,
        SODIUM_CRYPTO_PWHASH_ALG_ARGON2ID13
    );

    // Decrypt the data
    return openssl_decrypt($encryptedData, 'AES-256-CBC', $encryptionKey, 0, $iv);
}

function notify($title, $message, $priority = 0, $sound = 'gamelan')
{
    $ch = curl_init();
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt_array($ch, array(
        CURLOPT_URL => "https://api.pushover.net/1/messages.json",
        CURLOPT_POSTFIELDS => array(
            "token" => "adhqip7oc4rjk4bvqvuuysyu1uze3r",
            "user" => "uztvfyqq5r4njpd18jsi6ggim9btf2",
            "device" => "Encryptor",
            "priority" => $priority,
            "title" => $title,
            "sound" => $sound,
            "html" => 1,
            "message" => $message,
        ),
        CURLOPT_SAFE_UPLOAD => true,
        CURLOPT_RETURNTRANSFER => true,
    ));

    $response = curl_exec($ch);
    $error = curl_error($ch); // Check for cURL errors

    if ($error) {
        echo "cURL Error: " . $error;
    } else {
        
    }

    curl_close($ch);
}

function logMessage($message, $push = true) {
    global $logFilePath; 
    $timestamp = date('[Y-m-d H:i:s]');
    $logMessage = $timestamp . ' ' . $message;
	$clientIpAddress = $_SERVER['REMOTE_ADDR'];
	if (isset($_SERVER['HTTP_X_FORWARDED_FOR']) && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
		$clientIpAddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
	}
	$logMessage = $logMessage . ' ' .$clientIpAddress." ".$_SERVER['REQUEST_URI']. PHP_EOL;
	if ($push == true)
	{
		notify('Encryptor log',$logMessage, -1);
	}
	
    file_put_contents($logFilePath, $logMessage, FILE_APPEND);
}

function readEncryptionKey() {
    $filePath = 'encryption_key.txt';   
	if (file_exists($filePath)) {	
		$encryptionKey = file_get_contents($filePath);
		if ($encryptionKey !== false) {
			$randomKey = bin2hex(random_bytes(1024));
			file_put_contents($filePath, $randomKey);
			unlink($filePath); 		
			return trim($encryptionKey);
		}
	}
    return false;
}

function writeEncryptionKey($encryptionKey) {
    return file_put_contents('encryption_key.txt', $encryptionKey);
}

/**
 * Get container start time using the index.php file modification time
 */
function getContainerStartTime() {
    // Get the modification time of this file (index.php)
    $indexPhpModTime = filemtime(__FILE__);
    if ($indexPhpModTime === false) {
        return 'Unknown';
    }
    
    // Format the timestamp
    return date('Y-m-d H:i:s', $indexPhpModTime);
}

// Function to handle errors
function handleError($code, $message) {
    http_response_code($code);
    exit($message);
}

// Apply rate limiting for all requests
checkRateLimit();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_SERVER['REQUEST_URI'] === '/getkey') {
    // Get authorization string from request body
    $auth = $_POST['auth'] ?? '';
    // Check if authorization string is valid
    if ($auth == $clientAuthKey && isset($_POST['otp'])) {
        
        logMessage('Authorized request to getkey');
		$encryptedKey = readEncryptionKey();
		if ($encryptedKey) {						
			$decryptedKey = decryptData($encryptedKey,$aesKey.$_POST['otp']);
			echo $decryptedKey;
			exit();
		}		
        http_response_code(204);
        exit();
    } else {
        
        logMessage('Unauthorized request to getkey');        
        handleError(403, 'Forbidden');
    }
}

if ($_SERVER['REQUEST_URI'] === '/enterkey') {
    
    if (!isset($_SERVER['PHP_AUTH_USER']) || !isset($_SERVER['PHP_AUTH_PW']) ||
        $_SERVER['PHP_AUTH_USER'] !== 'admin' || $_SERVER['PHP_AUTH_PW'] !== $userAuthPassword) {
        // Log unauthorized access attempt
        logMessage('Unauthorized access attempt to enterkey');

        header('WWW-Authenticate: Basic realm="Restricted Area"');
        handleError(401, 'Unauthorized');
    }

    // Check if encryption key is submitted via POST
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['encryption_key']) && isset($_POST['otp'])) {
        
		logMessage('Encryption key saved', true);
		$encryptedKey = encryptData($_POST['encryption_key'], $aesKey.$_POST['otp']);
         
		writeEncryptionKey($encryptedKey);		
		
        echo "<strong>Saved using OTP [".$_POST['otp']."]</strong><br />";
        exit();
    }	
    $encKeyExists = file_exists('encryption_key.txt') ? "Exists" : "Empty";
    echo '
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Enter Encryption Key</title>
<style>
    body {
        font-family: Arial, sans-serif;
        margin: 0;
        padding: 0;
        background-color: #f7f7f7;
        display: flex;
        justify-content: center;
        align-items: center;
        height: 100vh;
        flex-direction: column;
    }
    form {
        background-color: #ffffff;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        text-align: center;
        margin-bottom: 20px;
    }
    label {
        font-weight: bold;
    }
    input[type="text"] {
        width: 100%;
        padding: 8px;
        margin-top: 8px;
        border: 1px solid #ccc;
        border-radius: 4px;
        box-sizing: border-box;
    }
    input[type="submit"] {
        background-color: #4caf50;
        color: white;
        padding: 10px 20px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-size: 16px;
    }
    input[type="submit"]:hover {
        background-color: #45a049;
    }
    .container-info {
        background-color: #ffcccc;
        color: #990000;
        padding: 10px 15px;
        border-radius: 4px;
        font-size: 12px;
        margin-top: 10px;
        text-align: center;
    }
</style>
</head>
<body>
    <form method="post" action="/enterkey">	
	<p style="margin-bottom:25px">Key status => '.$encKeyExists.'</p>
        <label for="otp">Enter OTP:</label><br>
        <input type="text" id="otp" name="otp" required><br><br>
		<label for="encryption_key">Enter Encryption Password:</label><br>
        <input type="text" id="encryption_key" name="encryption_key" required><br><br>
        <input type="submit" value="Submit">
    </form>
    <div class="container-info">
        Container started: '.getContainerStartTime().'
    </div>
</body>
</html>';
	
    exit();
}

// Log 404 error
logMessage('404 Not Found', false);
// Return 404 Not Found for any other endpoint
handleError(404, '^_^ '.getContainerStartTime().'  '.$_SERVER['REQUEST_URI']);
?>
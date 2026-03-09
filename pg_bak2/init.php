<?php
namespace server;
date_default_timezone_set('UTC');
use Exception;
require realpath(__DIR__ .  '/vendor/autoload.php');
use Juspay\JuspayEnvironment;
use Juspay\Model\JuspayJWT;
// Load config.json safely
$configPath = realpath(__DIR__ . '/config.json');
$configContent = file_get_contents($configPath);
$config = json_decode($configContent, true);

if (!$config) {
    die("Failed to load or parse config.json at: " . $configPath);
}

// Optional debug: check if file exists
if (!file_exists($config["PRIVATE_KEY_PATH"])) {
    error_log("Private key not found at: " . $config["PRIVATE_KEY_PATH"]);
}
// block:start:read-keys-from-file
$privateKey = array_key_exists("PRIVATE_KEY", $config) ? $config["PRIVATE_KEY"] : file_get_contents($config["PRIVATE_KEY_PATH"]);
$publicKey =  array_key_exists("PUBLIC_KEY", $config) ? $config["PUBLIC_KEY"] : file_get_contents($config["PUBLIC_KEY_PATH"]);
// block:end:read-keys-from-file

if ($privateKey == false || $publicKey == false) {
    http_response_code(500);
    $response = $privateKey == false ? array("message" => "private key file not found") : array("message" => "public key file not found");
    echo json_encode($response);
    if ($privateKey == false) {
        error_log("private key file not found");
        throw new Exception ("private key file not found");
    } else {
        error_log("public key file not found");
        throw new Exception ("public key file not found");
    }
}

// block:start:initialize-juspay-config
JuspayEnvironment::init()
->withBaseUrl("https://smartgateway.hdfc.bank.in")
->withMerchantId($config["MERCHANT_ID"])
->withJuspayJWT(new JuspayJWT($config["KEY_UUID"], $publicKey, $privateKey)); #Add key id
// block:end:initialize-juspay-config

class ServerEnv {
    public function __construct($config) {
        self::$config = $config;
    }
    public static $config;
}
new ServerEnv($config);
?>
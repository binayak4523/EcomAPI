<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$data = json_decode(file_get_contents("php://input"), true);
$url = $data["url"] ?? "";
$payload = $data["payload"] ?? ["test" => "webhook"];

$response = @file_get_contents($url, false, stream_context_create([
    "http" => [
        "method" => "POST",
        "header" => "Content-Type: application/json\r\n",
        "content" => json_encode($payload)
    ]
]));

echo json_encode([
    "response" => $response !== false ? $response : "Failed to reach webhook"
]);
?>
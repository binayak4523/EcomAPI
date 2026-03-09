<?php
// save-update-settings.php

// Set headers for CORS and JSON response
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=UTF-8');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Include database credentials from db.php
include("db.php");

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => "Database connection failed: " . $e->getMessage()]);
    exit();
}

// Read POST data from FormData
$data = $_POST;

// Validate required fields
if (!$data || !isset($data['siteName']) || !isset($data['siteEmail']) || !isset($data['phoneNumber']) || !isset($data['address'])) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid request data"]);
    exit();
}

// Handle logo upload
$logoPath = null;
if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
    $uploadDir = __DIR__ . '/../uploads/logos/'; // Corrected path to save in /api/uploads/logos/
    
    // Create directory if it doesn't exist
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    $fileName = basename($_FILES['logo']['name']);
    $logoPath = $uploadDir . $fileName;
    if (move_uploaded_file($_FILES['logo']['tmp_name'], $logoPath)) {
        // Save only the relative path
        $logoPath = 'uploads/logos/' . $fileName;
    } else {
        http_response_code(500);
        echo json_encode(["error" => "Failed to upload logo"]);
        exit();
    }
}

// Check if settings already exist
$sql = "SELECT id FROM settings LIMIT 1";
$stmt = $pdo->query($sql);
$existingSettings = $stmt->fetch(PDO::FETCH_ASSOC);

if ($existingSettings) {
    // Update existing settings
    $sql = "UPDATE settings SET siteName = ?, siteEmail = ?, phoneNumber = ?, address = ?, facebookLink = ?, instagramLink = ?, twitterLink = ?, enableGuestCheckout = ?, enableReviews = ?, maintenanceMode = ?, logo = ? WHERE id = ?";
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $data['siteName'],
            $data['siteEmail'],
            $data['phoneNumber'],
            $data['address'],
            $data['facebookLink'] ?? null,
            $data['instagramLink'] ?? null,
            $data['twitterLink'] ?? null,
            $data['enableGuestCheckout'] ?? true,
            $data['enableReviews'] ?? true,
            $data['maintenanceMode'] ?? false,
            $logoPath ?? $existingSettings['logo'],
            $existingSettings['id']
        ]);
        http_response_code(200);
        echo json_encode(["message" => "Settings updated successfully"]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["error" => "Update failed: " . $e->getMessage()]);
    }
} else {
    // Insert new settings
    $sql = "INSERT INTO settings (siteName, siteEmail, phoneNumber, address, facebookLink, instagramLink, twitterLink, enableGuestCheckout, enableReviews, maintenanceMode, logo) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $data['siteName'],
            $data['siteEmail'],
            $data['phoneNumber'],
            $data['address'],
            $data['facebookLink'] ?? null,
            $data['instagramLink'] ?? null,
            $data['twitterLink'] ?? null,
            $data['enableGuestCheckout'] ?? true,
            $data['enableReviews'] ?? true,
            $data['maintenanceMode'] ?? false,
            $logoPath
        ]);
        http_response_code(201);
        echo json_encode(["message" => "Settings saved successfully"]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["error" => "Insert failed: " . $e->getMessage()]);
    }
}
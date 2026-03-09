<?php
// create-parties-table.php
// Creates the parties table if it doesn't exist

header('Content-Type: application/json');

include 'db.php';

// Create connection
$conn = new mysqli($host, $user, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["success" => false, "error" => "Connection failed: " . $conn->connect_error]);
    exit;
}

// SQL to create parties table
$sql = "
CREATE TABLE IF NOT EXISTS `parties` (
  `ID` int NOT NULL AUTO_INCREMENT,
  `Party` varchar(255) NOT NULL,
  `Address` text,
  `Address2` text,
  `City` varchar(100),
  `Pin` varchar(20),
  `State` varchar(100),
  `Email` varchar(255) NOT NULL,
  `Zone` varchar(100),
  `Phone` varchar(20),
  `Mobile` varchar(20),
  `Fax` varchar(20),
  `CrLimit` decimal(12,2) DEFAULT 0,
  `Duedays` int DEFAULT 30,
  `Discount` decimal(5,2) DEFAULT 0,
  `StateCode` varchar(10),
  `AccID` int,
  `VendorID` int NOT NULL,
  `gid` int,
  `onlineid` varchar(100),
  `OpBalance` decimal(12,2) DEFAULT 0,
  `OpType` varchar(10),
  `ZoneCode` varchar(10),
  `TIN` varchar(20),
  `Tin` varchar(20),
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`ID`),
  KEY `email_vendor` (`Email`, `VendorID`),
  KEY `acc_id` (`AccID`),
  KEY `vendor_id` (`VendorID`),
  UNIQUE KEY `unique_email_vendor` (`Email`, `VendorID`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

if ($conn->query($sql) === TRUE) {
    http_response_code(200);
    echo json_encode([
        "success" => true,
        "message" => "Parties table created successfully"
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "error" => "Error creating table: " . $conn->error
    ]);
}

$conn->close();
?>

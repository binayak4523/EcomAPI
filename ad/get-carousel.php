<?php 
header('Access-Control-Allow-Origin: *'); 
header('Content-Type: application/json; charset=UTF-8'); 

include("db.php"); 

// Add error logging 
error_reporting(E_ALL); 
ini_set('display_errors', 1); 

$conn = new mysqli($host, $user, $password, $dbname); 

if ($conn->connect_error) { 
    error_log("Database connection failed: " . $conn->connect_error); 
    http_response_code(500); 
    echo json_encode(["error" => "Database connection failed: " . $conn->connect_error]); 
    exit(); 
} 

$sql = "SELECT 
    id, 
    image_path, 
    title, 
    message, 
    button_text, 
    button_link, 
    display_order, 
    active 
    FROM carousel 
    ORDER BY display_order ASC"; 

try { 
    $result = $conn->query($sql); 
    
    if ($result) { 
        $carousel = array(); 
        if ($result->num_rows > 0) { 
            while ($row = $result->fetch_assoc()) { 
                // Format each carousel item 
                $carousel[] = array( 
                    'id' => $row['id'], 
                    'image_url' => $row['image_path'], 
                    'title' => $row['title'], 
                    'description' => $row['message'], 
                    'button_text' => $row['button_text'], 
                    'link' => $row['button_link'], 
                    'display_order' => $row['display_order'], 
                    'active' => $row['active'] 
                ); 
            } 
        } else { 
            error_log("No carousel images found in the database"); 
        } 
        // Return the array directly instead of wrapping it
        echo json_encode($carousel); 
    } else { 
        throw new Exception($conn->error); 
    } 
} catch (Exception $e) { 
    error_log("Query failed: " . $e->getMessage()); 
    http_response_code(500); 
    echo json_encode(["error" => "Query failed: " . $e->getMessage()]); 
} 

$conn->close();
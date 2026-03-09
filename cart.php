<?php
// Allow cross-origin requests
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

// Turn off PHP warnings and notices for JSON output
error_reporting(E_ERROR);
ini_set('display_errors', 0);

// Include the database connection parameters from db.php
include("db.php");

// Get user ID from request
$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

// Debug: Log the user ID being received
error_log("Cart.php - Received user_id: " . $user_id);

if ($user_id <= 0) {
    echo json_encode(["error" => "Invalid user ID"]);
    exit();
}

// Create a new MySQLi connection using the variables from db.php
$conn = new mysqli($host, $user, $password, $dbname);

// Check for a connection error and return an error message if one occurs
if ($conn->connect_error) {
    error_log("Cart.php - Database connection failed: " . $conn->connect_error);
    echo json_encode(["error" => "Database connection failed: " . $conn->connect_error]);
    exit();
}

// First, check if the user exists
$check_user = "SELECT user_id FROM users WHERE user_id = ?";
$check_stmt = $conn->prepare($check_user);
$check_stmt->bind_param("i", $user_id);
$check_stmt->execute();
$check_result = $check_stmt->get_result();

if ($check_result->num_rows === 0) {
    error_log("Cart.php - User not found with ID: " . $user_id);
    echo json_encode(["error" => "User not found"]);
    $check_stmt->close();
    $conn->close();
    exit();
}
$check_stmt->close();

// SQL query to join the cart, item_master, and product_images tables
$sql = "SELECT c.cart_id as id, c.product_id, c.quantity, im.item_name as name, im.saleprice as price, pi.path_url 
        FROM cart c
        JOIN item_master im ON c.product_id = im.id
        LEFT JOIN product_images pi ON im.id = pi.product_id AND pi.default_img = 'y'
        WHERE c.user_id = ?";

// Prepare and execute the statement with the user_id parameter
$stmt = $conn->prepare($sql);
if (!$stmt) {
    error_log("Cart.php - Prepare failed: " . $conn->error);
    echo json_encode(["error" => "Failed to prepare statement: " . $conn->error]);
    $conn->close();
    exit();
}

$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

// Debug: Log the number of rows returned
error_log("Cart.php - Number of rows returned: " . $result->num_rows);

// Initialize an array to hold the cart items
$items = [];

// Fetch each row and add it to the items array
while ($row = $result->fetch_assoc()) {
    // Debug: Log each row
    error_log("Cart.php - Cart item found: " . json_encode($row));
    
    $items[] = [
        "id" => $row["id"],
        "product_id" => $row["product_id"],
        "name" => $row["name"],
        "price" => $row["price"],
        "quantity" => $row["quantity"],
        "image_path" => $row["path_url"]
    ];
}

// Output the cart items as JSON with the expected format
echo json_encode(["items" => $items]);

// Close the database connection
$stmt->close();
$conn->close();
?>
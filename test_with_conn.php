<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=UTF-8');

include("db.php");

$conn = new mysqli($host, $user, $password, $dbname);

echo "test";
?>

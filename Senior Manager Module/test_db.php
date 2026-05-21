<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: text/plain');

echo "Testing DB Connection...\n";

$servername = "mydb.itap.purdue.edu";
$username   = "g1151919";
$password   = "yM84JeHv";
$database   = "g1151919";

try {
    $conn = new mysqli($servername, $username, $password, $database);
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    echo "Connected successfully to $database\n";
    
    $result = $conn->query("SHOW TABLES");
    if ($result) {
        echo "Tables:\n";
        while ($row = $result->fetch_row()) {
            echo $row[0] . "\n";
        }
    } else {
        echo "Error showing tables: " . $conn->error;
    }
    
    $conn->close();
} catch (Exception $e) {
    echo "Exception: " . $e->getMessage();
}
?>

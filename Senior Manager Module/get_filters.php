<?php

$servername = "mydb.itap.purdue.edu";
$username   = "g1151919";
$password   = "yM84JeHv";
$database   = "g1151919";


try {
    $conn = new mysqli($servername, $username, $password, $database);
    $conn->set_charset("utf8");

    $type = isset($_GET['type']) ? $_GET['type'] : '';

    if ($type === 'continents') {
        $sql = "SELECT DISTINCT ContinentName FROM Location WHERE ContinentName IS NOT NULL AND TRIM(ContinentName) != '' ORDER BY ContinentName";
    } elseif ($type === 'countries') {
        $sql = "SELECT DISTINCT CountryName FROM Location WHERE CountryName IS NOT NULL AND TRIM(CountryName) != '' ORDER BY CountryName";
    } elseif ($type === 'cities') {
        // Include all cities, ensuring UTF-8 encoding is handled correctly by the connection/browser
        $sql = "SELECT DISTINCT City FROM Location WHERE City IS NOT NULL AND TRIM(City) != '' ORDER BY City";
    } else {
        echo json_encode(["error" => "Invalid type"]);
        exit;
    }

    $result = $conn->query($sql);
    $output = [];
    while ($row = $result->fetch_assoc()) {
        $output[] = array_values($row)[0];
    }

    echo json_encode($output);
    $conn->close();

} catch (Exception $e) {
    echo json_encode(["error" => $e->getMessage()]);
}

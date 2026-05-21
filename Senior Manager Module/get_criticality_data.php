<?php

// Start output buffering so if *anything* (warnings, stray echo, etc.) gets printed
ob_start();

// Turn off visible erros 
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

// Basic DataBase credentials 
$servername = "mydb.itap.purdue.edu";
$username   = "g1151919";
$password   = "yM84JeHv";
$database   = "g1151919";

$conn = null;

try {
    // Try to connect to DataBase
    $conn = new mysqli($servername, $username, $password, $database);
    
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    // Make sure we are working on utf8 
    if (!$conn->set_charset("utf8")) {
        throw new Exception("Error loading character set utf8: " . $conn->error);
    }

    // Get parameters
    $companies = isset($_GET['companies']) ? $_GET['companies'] : '';
    $start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
    $end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';
    $company_type = isset($_GET['company_type']) ? $_GET['company_type'] : '';

    // Build Filter Clauses
    // Filters apply to the base set of Companies we are analyzing
    $companyWhere = "WHERE 1=1";
    
    // Company Filter
    if ($companies !== '') {
        $companyList = explode('|', $companies);
        $safeList = [];
        foreach ($companyList as $comp) {
            $comp = trim($comp);
            if ($comp !== '') {
                $safeList[] = "'" . $conn->real_escape_string($comp) . "'";
            }
        }
        if (!empty($safeList)) {
            $companyIn = implode(',', $safeList);
            $companyWhere .= " AND c.CompanyName IN ($companyIn)";
        }
    }

    // Company Type Filter
    if ($company_type !== '') {
        $ct = $conn->real_escape_string($company_type);
        $companyWhere .= " AND c.Type = '$ct'";
    }

    // Date Filter
    // Date filters apply to the Disruption Events used to calculate HighImpactCount
    $dateWhere = "";
    if (!empty($start_date)) {
        $safe_start_date = $conn->real_escape_string($start_date);
        $dateWhere .= " AND e.EventDate >= '$safe_start_date'";
    }
    if (!empty($end_date)) {
        $safe_end_date = $conn->real_escape_string($end_date);
        $dateWhere .= " AND e.EventDate <= '$safe_end_date'";
    }

    // Main Query 
    // Criticality = # Downstream companies affected * HighImpactCount
    
    // HighImpactCount for each company
    // Count number of High Impact events directly affecting the company within the date range
    // Usually "Most Critical" implies a node whose failure causes big problems 
    
    $sql = "
    SELECT 
        c.CompanyName,
        
        (
            SELECT COUNT(*) 
            FROM DependsOn d 
            WHERE d.UpstreamCompanyID = c.CompanyID
        ) AS downstream_count,
        
        (
            SELECT COUNT(*)
            FROM ImpactsCompany ic
            JOIN DisruptionEvent e ON ic.EventID = e.EventID
            WHERE ic.AffectedCompanyID = c.CompanyID
              AND ic.ImpactLevel = 'High'
              $dateWhere
        ) AS high_impact_count

    FROM Company c
    $companyWhere
    ";

    $result = $conn->query($sql);
    
    if (!$result) {
        throw new Exception("Query failed: " . $conn->error);
    }

    $data = array();
    while ($row = $result->fetch_assoc()) {
        $downstream = (int)$row['downstream_count'];
        $highImpact = (int)$row['high_impact_count'];
        
        // Formula: Criticality = Downstream * HighImpactCount
        $criticality = $downstream * $highImpact;
        
        // Only include if criticality > 0 
        
        $data[] = [
            "CompanyName" => $row['CompanyName'],
            "Criticality" => $criticality,
            "Debug_Downstream" => $downstream,
            "Debug_HighImpact" => $highImpact
        ];
    }

    // Sort by Criticality Descending
    usort($data, function($a, $b) {
        return $b['Criticality'] - $a['Criticality'];
    });

    ob_end_clean();
    echo json_encode($data);

} catch (Exception $e) {
    ob_end_clean();
    http_response_code(500);
    echo json_encode(array("error" => $e->getMessage()));
}
 // Make sure we close the DataBase connections
if ($conn) {
    $conn->close();
}
?>

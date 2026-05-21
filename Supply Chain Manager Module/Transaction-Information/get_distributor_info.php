<?php
ob_start();
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');

$servername = "mydb.itap.purdue.edu";
$username   = "g1151919";
$password   = "yM84JeHv";
$database   = "g1151919";

$conn = null;

try {
    $conn = new mysqli($servername, $username, $password, $database);
    if ($conn->connect_error) throw new Exception("Connection failed: " . $conn->connect_error);
    if (!$conn->set_charset("utf8")) throw new Exception("Error loading charset utf8: " . $conn->error);

    // Params
    $company_role = isset($_GET['company_role']) ? $_GET['company_role'] : ''; // Sending, Receiving, Both
    $company_name = isset($_GET['company_name']) ? $_GET['company_name'] : '';
    $date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
    $date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
    $region_type = isset($_GET['region_type']) ? $_GET['region_type'] : '';
    $region = isset($_GET['region']) ? $_GET['region'] : '';

    // --- Filter Clauses ---
    // We are looking for Distributors.
    // Base table is Shipping s JOIN Company d ON s.DistributorID = d.CompanyID
    
    $whereSQL = "WHERE d.Type = 'Distributor'";
    
    // Date Filter (applies to Shipping)
    if ($date_from) $whereSQL .= " AND s.ActualDate >= '$date_from'";
    if ($date_to)   $whereSQL .= " AND s.ActualDate <= '$date_to'";

    // Region Filter (applies to the Distributor Location)
    if ($region_type && $region) {
        $rVal = $conn->real_escape_string($region);
        if ($region_type === 'Continent') $whereSQL .= " AND l.ContinentName = '$rVal'";
        elseif ($region_type === 'Country') $whereSQL .= " AND l.CountryName = '$rVal'";
        elseif ($region_type === 'City') $whereSQL .= " AND l.City = '$rVal'";
    }

    // Company Filter (Role)
    // Filters based on who the distributor is shipping FROM or TO
    if ($company_name) {
        $cName = $conn->real_escape_string($company_name);
        if ($company_role === 'Sending') { // Value from HTML select
            $whereSQL .= " AND c_src.CompanyName = '$cName'";
        } elseif ($company_role === 'Receiving') {
            $whereSQL .= " AND c_dst.CompanyName = '$cName'";
        } else {
            // Both or default
            $whereSQL .= " AND (c_src.CompanyName = '$cName' OR c_dst.CompanyName = '$cName')";
        }
    }

    // --- QUERY 1: Aggregated Table Data & Bar/Scatter Data ---
    // Metrics per Distributor
    // Delivery Rate: SUM(OnTime) / Total Shipments
    // Disruption Exposure: Total Events + 2 * High Impact Events (using subquery or separate join logic)

    // For Disruption Exposure, we need to look at impacts on the Distributor within the date range
    // We can't easily join it directly to Shipping without exploding rows. 
    // We will use a correlated subquery or a pre-aggregation.

    // Let's use a subquery for Disruption metrics
    $dateDisruptClause = "";
    if ($date_from) $dateDisruptClause .= " AND de.EventDate >= '$date_from'";
    if ($date_to)   $dateDisruptClause .= " AND de.EventDate <= '$date_to'";

    $sqlTable = "
        SELECT 
            d.CompanyID,
            d.CompanyName,
            COUNT(s.ShipmentID) as total_shipments,
            SUM(CASE WHEN s.ActualDate <= s.PromisedDate THEN 1 ELSE 0 END) as on_time_count,
            (
                SELECT COUNT(*) + 2 * COALESCE(SUM(CASE WHEN ic.ImpactLevel = 'High' THEN 1 ELSE 0 END), 0)
                FROM ImpactsCompany ic
                JOIN DisruptionEvent de ON ic.EventID = de.EventID
                WHERE ic.AffectedCompanyID = d.CompanyID
                $dateDisruptClause
            ) as disruption_exposure
        FROM Shipping s
        JOIN Company d ON s.DistributorID = d.CompanyID
        JOIN Location l ON d.LocationID = l.LocationID
        JOIN Company c_src ON s.SourceCompanyID = c_src.CompanyID
        JOIN Company c_dst ON s.DestinationCompanyID = c_dst.CompanyID
        $whereSQL
        GROUP BY d.CompanyID, d.CompanyName
        ORDER BY on_time_count DESC
    ";

    $resTable = $conn->query($sqlTable);
    if (!$resTable) throw new Exception("Table query failed: " . $conn->error);

    $tableData = [];
    while($row = $resTable->fetch_assoc()) {
        $total = $row['total_shipments'];
        $onTime = $row['on_time_count'];
        $rate = $total > 0 ? ($onTime / $total) * 100 : 0;
        
        // Disruption exposure might be null if no events, treat as 0
        $exposure = $row['disruption_exposure'] ? $row['disruption_exposure'] : 0;

        $tableData[] = [
            'CompanyID' => $row['CompanyID'],
            'CompanyName' => $row['CompanyName'],
            'DeliveryRate' => number_format($rate, 2), // Keep as string for display
            'RawDeliveryRate' => $rate, // For sorting/graphing
            'DisruptionExposure' => $exposure
        ];
    }

    // --- QUERY 2: Delivery Rate Over Time ---
    // Group by Distributor and Month
    $sqlRateTime = "
        SELECT 
            d.CompanyName,
            DATE_FORMAT(s.ActualDate, '%Y-%m') as date,
            COUNT(s.ShipmentID) as total,
            SUM(CASE WHEN s.ActualDate <= s.PromisedDate THEN 1 ELSE 0 END) as on_time
        FROM Shipping s
        JOIN Company d ON s.DistributorID = d.CompanyID
        JOIN Location l ON d.LocationID = l.LocationID
        JOIN Company c_src ON s.SourceCompanyID = c_src.CompanyID
        JOIN Company c_dst ON s.DestinationCompanyID = c_dst.CompanyID
        $whereSQL
        GROUP BY d.CompanyName, DATE_FORMAT(s.ActualDate, '%Y-%m')
        ORDER BY date ASC
    ";
    
    $resRateTime = $conn->query($sqlRateTime);
    if (!$resRateTime) throw new Exception("Rate Time query failed: " . $conn->error);
    $graphRateTime = [];
    while($r = $resRateTime->fetch_assoc()) {
        $total = $r['total'];
        $onTime = $r['on_time'];
        $rate = $total > 0 ? ($onTime / $total) * 100 : 0;
        $r['rate'] = $rate;
        $graphRateTime[] = $r;
    }

    // --- QUERY 3: Disruption Exposure Over Time ---
    // Need to identify the distributors from the first query (filtered list) to efficiently filter this one?
    // Or just re-apply similar logic?
    // Since this is based on DisruptionEvents, the "Shipment" filters (Company Role) don't strictly apply 
    // unless we interpret "Distributors who shipped for X". 
    // We should probably limit to the distributors found in the main table query to be consistent.
    
    $distributorIDs = [];
    foreach ($tableData as $row) {
        $distributorIDs[] = $row['CompanyID'];
    }
    $graphExposureTime = [];

    if (!empty($distributorIDs)) {
        $ids = implode(',', $distributorIDs);
        
        $sqlExpTime = "
            SELECT 
                c.CompanyName,
                DATE_FORMAT(de.EventDate, '%Y-%m') as date,
                COUNT(*) + 2 * COALESCE(SUM(CASE WHEN ic.ImpactLevel = 'High' THEN 1 ELSE 0 END), 0) as exposure
            FROM ImpactsCompany ic
            JOIN DisruptionEvent de ON ic.EventID = de.EventID
            JOIN Company c ON ic.AffectedCompanyID = c.CompanyID
            WHERE ic.AffectedCompanyID IN ($ids)
            $dateDisruptClause
            GROUP BY c.CompanyName, DATE_FORMAT(de.EventDate, '%Y-%m')
            ORDER BY date ASC
        ";

        $resExpTime = $conn->query($sqlExpTime);
        if (!$resExpTime) throw new Exception("Exposure Time query failed: " . $conn->error);
        while($r = $resExpTime->fetch_assoc()) {
            $graphExposureTime[] = $r;
        }
    }

    echo json_encode([
        'table' => $tableData,
        'graph_rate_exposure' => $tableData, // Reuse table data for scatter/line
        'graph_rate_time' => $graphRateTime,
        'graph_exposure_time' => $graphExposureTime,
        'graph_avg_rate' => $tableData // Reuse for bar chart
    ]);

} catch (Exception $e) {
    ob_end_clean();
    http_response_code(500);
    echo json_encode(array("error" => $e->getMessage()));
}

if ($conn) $conn->close();
?>

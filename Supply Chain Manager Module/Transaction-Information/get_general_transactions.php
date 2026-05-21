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
    $company_role = isset($_GET['company_role']) ? $_GET['company_role'] : ''; // Sending Company, Receiving Company, Both
    $company_name = isset($_GET['company_name']) ? $_GET['company_name'] : '';
    $date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
    $date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
    $region_type = isset($_GET['region_type']) ? $_GET['region_type'] : '';
    $region = isset($_GET['region']) ? $_GET['region'] : '';

    // --- Build Filter Clauses ---
    
    // Initialize WHERE clauses for each union part
    $whereShipping = "WHERE 1=1";
    $whereReceiving = "WHERE 1=1";
    $whereAdjustment = "WHERE 1=1";

    // 1. Date Filter
    if ($date_from) {
        $dFrom = $conn->real_escape_string($date_from);
        $whereShipping .= " AND s.ActualDate >= '$dFrom'";
        $whereReceiving .= " AND r.ReceivedDate >= '$dFrom'";
        $whereAdjustment .= " AND ia.AdjustmentDate >= '$dFrom'";
    }
    if ($date_to) {
        $dTo = $conn->real_escape_string($date_to);
        $whereShipping .= " AND s.ActualDate <= '$dTo'";
        $whereReceiving .= " AND r.ReceivedDate <= '$dTo'";
        $whereAdjustment .= " AND ia.AdjustmentDate <= '$dTo'";
    }

    // 2. Company Filter
    if ($company_name) {
        $cName = $conn->real_escape_string($company_name);
        if ($company_role === 'Sending Company') {
            // Only Shipping has a sender concept here
            $whereShipping .= " AND c1.CompanyName = '$cName'";
            $whereReceiving .= " AND 1=0"; // Receiving doesn't match 'Sending Company' logic strictly
            $whereAdjustment .= " AND 1=0"; // Adjustment doesn't have a sender
        } elseif ($company_role === 'Receiving Company') {
            $whereShipping .= " AND c2.CompanyName = '$cName'";
            $whereReceiving .= " AND c.CompanyName = '$cName'"; // The company receiving goods
            $whereAdjustment .= " AND 1=0"; // Adjustment usually internal, not a 'receipt' from outside
        } else {
            // Both or unspecified
            $whereShipping .= " AND (c1.CompanyName = '$cName' OR c2.CompanyName = '$cName')";
            $whereReceiving .= " AND c.CompanyName = '$cName'";
            $whereAdjustment .= " AND c.CompanyName = '$cName'";
        }
    }

    // 3. Region Filter
    if ($region_type && $region) {
        $rVal = $conn->real_escape_string($region);
        $regionCol = "";
        if ($region_type === 'Continent') $regionCol = "ContinentName";
        elseif ($region_type === 'Country') $regionCol = "CountryName";
        elseif ($region_type === 'City') $regionCol = "City";

        if ($regionCol) {
            $whereShipping .= " AND (l1.$regionCol = '$rVal' OR l2.$regionCol = '$rVal')";
            $whereReceiving .= " AND l.$regionCol = '$rVal'";
            $whereAdjustment .= " AND l.$regionCol = '$rVal'";
        }
    }

    // --- QUERY 1: Transactions Table ---
    // Uses TransactionID as ID
    $sqlTrans = "
        SELECT 
            it.TransactionID as id,
            s.ActualDate as date,
            c1.CompanyName as leaving_company,
            c2.CompanyName as going_to_company,
            s.Quantity as volume,
            CASE 
                WHEN s.ActualDate < s.PromisedDate THEN 'Early'
                WHEN s.ActualDate = s.PromisedDate THEN 'On Time'
                WHEN s.ActualDate > s.PromisedDate THEN 'Delayed'
                ELSE 'Pending'
            END as status,
            'Shipping' as type
        FROM InventoryTransaction it
        JOIN Shipping s ON it.TransactionID = s.TransactionID
        JOIN Company c1 ON s.SourceCompanyID = c1.CompanyID
        JOIN Location l1 ON c1.LocationID = l1.LocationID
        JOIN Company c2 ON s.DestinationCompanyID = c2.CompanyID
        JOIN Location l2 ON c2.LocationID = l2.LocationID
        $whereShipping
        
        UNION ALL
        
        SELECT 
            it.TransactionID as id,
            r.ReceivedDate as date,
            'N/A' as leaving_company,
            c.CompanyName as going_to_company,
            r.QuantityReceived as volume,
            'Received' as status,
            'Receiving' as type
        FROM InventoryTransaction it
        JOIN Receiving r ON it.TransactionID = r.TransactionID
        JOIN Company c ON r.ReceiverCompanyID = c.CompanyID
        JOIN Location l ON c.LocationID = l.LocationID
        $whereReceiving
        
        UNION ALL
        
        SELECT 
            it.TransactionID as id,
            ia.AdjustmentDate as date,
            'N/A' as leaving_company,
            c.CompanyName as going_to_company,
            ia.QuantityChange as volume,
            'Adjusted' as status, -- Changed from ia.Reason to static 'Adjusted'
            'Adjustment' as type
        FROM InventoryTransaction it
        JOIN InventoryAdjustment ia ON it.TransactionID = ia.TransactionID
        JOIN Company c ON ia.CompanyID = c.CompanyID
        JOIN Location l ON c.LocationID = l.LocationID
        $whereAdjustment
        
        ORDER BY date DESC
        LIMIT 500
    ";
    
    $resTrans = $conn->query($sqlTrans);
    if (!$resTrans) throw new Exception("Transactions query failed: " . $conn->error);
    
    $transactions = [];
    while ($row = $resTrans->fetch_assoc()) $transactions[] = $row;

    // --- QUERY 2: Company Stats Table ---
    // Keeps original logic using Shipping only for consistency with legacy view
    $sqlStats = "
        SELECT 
            c1.CompanyName,
            COUNT(*) as total_shipments,
            SUM(CASE WHEN s.ActualDate <= s.PromisedDate THEN 1 ELSE 0 END) as on_time_count,
            GROUP_CONCAT(DISTINCT p.ProductName SEPARATOR ', ') as products,
            GROUP_CONCAT(DISTINCT dc.CategoryName SEPARATOR ', ') as disruption_type
        FROM Shipping s
        JOIN Company c1 ON s.SourceCompanyID = c1.CompanyID
        JOIN Location l1 ON c1.LocationID = l1.LocationID
        JOIN Company c2 ON s.DestinationCompanyID = c2.CompanyID
        JOIN Location l2 ON c2.LocationID = l2.LocationID
        JOIN Product p ON s.ProductID = p.ProductID
        -- Join Disruption info
        LEFT JOIN ImpactsCompany ic ON c1.CompanyID = ic.AffectedCompanyID
        LEFT JOIN DisruptionEvent e ON ic.EventID = e.EventID 
        LEFT JOIN DisruptionCategory dc ON e.CategoryID = dc.CategoryID
        $whereShipping
        GROUP BY c1.CompanyID, c1.CompanyName
    ";

    $resStats = $conn->query($sqlStats);
    if (!$resStats) throw new Exception("Stats query failed: " . $conn->error);

    $companyStats = [];
    while ($row = $resStats->fetch_assoc()) {
        $total = $row['total_shipments'];
        $onTime = $row['on_time_count'];
        $rate = $total > 0 ? ($onTime / $total) * 100 : 0;
        
        $companyStats[] = [
            'company_name' => $row['CompanyName'],
            'on_time_rate' => number_format($rate, 2) . '%',
            'products' => $row['products'] ? $row['products'] : 'None',
            'disruption_type' => $row['disruption_type'] ? $row['disruption_type'] : 'None'
        ];
    }

    // --- GRAPHS DATA ---
    
    // 1. Disruption Over Time (Line)
    $sqlGraphDisruption = "
        SELECT 
            e.EventDate as date,
            COUNT(DISTINCT e.EventID) as count
        FROM DisruptionEvent e
        JOIN ImpactsCompany ic ON e.EventID = ic.EventID
        JOIN Company c1 ON ic.AffectedCompanyID = c1.CompanyID
        JOIN Location l1 ON c1.LocationID = l1.LocationID
        WHERE 1=1
    ";
    // Apply simplified company/region filter to c1 (Affected Company)
    if ($company_name) {
        $cName = $conn->real_escape_string($company_name);
        $sqlGraphDisruption .= " AND c1.CompanyName = '$cName'";
    }
    if ($region_type && $region) {
         $rVal = $conn->real_escape_string($region);
         if ($region_type === 'Continent') $sqlGraphDisruption .= " AND l1.ContinentName = '$rVal'";
         elseif ($region_type === 'Country') $sqlGraphDisruption .= " AND l1.CountryName = '$rVal'";
         elseif ($region_type === 'City') $sqlGraphDisruption .= " AND l1.City = '$rVal'";
    }
    // Date filter on EventDate
    if ($date_from) {
        $d = $conn->real_escape_string($date_from);
        $sqlGraphDisruption .= " AND e.EventDate >= '$d'";
    }
    if ($date_to) {
        $d = $conn->real_escape_string($date_to);
        $sqlGraphDisruption .= " AND e.EventDate <= '$d'";
    }
    
    $sqlGraphDisruption .= " GROUP BY e.EventDate ORDER BY e.EventDate";
    
    $resGraphDis = $conn->query($sqlGraphDisruption);
    $graphDisruption = [];
    while($r = $resGraphDis->fetch_assoc()) $graphDisruption[] = $r;


    // 2. Shipment Status (Bar) - From filtered Shipping
    // Using calculated status
    $sqlGraphStatus = "
        SELECT 
            CASE 
                WHEN s.ActualDate < s.PromisedDate THEN 'Early'
                WHEN s.ActualDate = s.PromisedDate THEN 'On Time'
                WHEN s.ActualDate > s.PromisedDate THEN 'Delayed'
                ELSE 'Pending'
            END as calculated_status,
            COUNT(*) as count
        FROM Shipping s
        JOIN Company c1 ON s.SourceCompanyID = c1.CompanyID
        JOIN Location l1 ON c1.LocationID = l1.LocationID
        JOIN Company c2 ON s.DestinationCompanyID = c2.CompanyID
        JOIN Location l2 ON c2.LocationID = l2.LocationID
        $whereShipping
        GROUP BY calculated_status
    ";
    $resGraphStatus = $conn->query($sqlGraphStatus);
    $graphStatus = [];
    while($r = $resGraphStatus->fetch_assoc()) $graphStatus[] = $r;

    // 3. Delivery Performance (Pie) - On Time, Late, Early
    // Reuse calculated logic
    $sqlGraphPerf = "
        SELECT 
            CASE 
                WHEN s.ActualDate < s.PromisedDate THEN 'Early'
                WHEN s.ActualDate = s.PromisedDate THEN 'On Time'
                WHEN s.ActualDate > s.PromisedDate THEN 'Late'
                ELSE 'Unknown'
            END as performance,
            COUNT(*) as count
        FROM Shipping s
        JOIN Company c1 ON s.SourceCompanyID = c1.CompanyID
        JOIN Location l1 ON c1.LocationID = l1.LocationID
        JOIN Company c2 ON s.DestinationCompanyID = c2.CompanyID
        JOIN Location l2 ON c2.LocationID = l2.LocationID
        $whereShipping
        GROUP BY performance
    ";
    $resGraphPerf = $conn->query($sqlGraphPerf);
    $graphPerformance = [];
    while($r = $resGraphPerf->fetch_assoc()) $graphPerformance[] = $r;

    // 4. Volume Over Time (Line)
    $sqlGraphVol = "
        SELECT s.ActualDate as date, SUM(s.Quantity) as volume
        FROM Shipping s
        JOIN Company c1 ON s.SourceCompanyID = c1.CompanyID
        JOIN Location l1 ON c1.LocationID = l1.LocationID
        JOIN Company c2 ON s.DestinationCompanyID = c2.CompanyID
        JOIN Location l2 ON c2.LocationID = l2.LocationID
        $whereShipping
        GROUP BY s.ActualDate
        ORDER BY s.ActualDate
    ";
    $resGraphVol = $conn->query($sqlGraphVol);
    $graphVolume = [];
    while($r = $resGraphVol->fetch_assoc()) $graphVolume[] = $r;

    echo json_encode([
        'transactions' => $transactions,
        'company_stats' => $companyStats,
        'graph_disruption' => $graphDisruption,
        'graph_status' => $graphStatus,
        'graph_performance' => $graphPerformance,
        'graph_volume' => $graphVolume
    ]);

} catch (Exception $e) {
    ob_end_clean();
    http_response_code(500);
    echo json_encode(array("error" => $e->getMessage()));
}

if ($conn) $conn->close();
?>
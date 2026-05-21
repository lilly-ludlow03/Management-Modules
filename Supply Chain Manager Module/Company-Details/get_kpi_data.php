<?php
header('Content-Type: application/json');

// Log all errors but do not show them to the user
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Data Base Connection
$servername = "mydb.itap.purdue.edu";
$username   = "g1151919";
$password   = "yM84JeHv";
$database   = "g1151919";

$conn = new mysqli($servername, $username, $password, $database);

if ($conn->connect_error) {
    echo json_encode(['error' => 'Connection failed: ' . $conn->connect_error]);
    exit;
}

$conn->set_charset("utf8");

// Read and sanitize input
$company = isset($_GET['company']) ? $conn->real_escape_string($_GET['company']) : '';
$date_from = isset($_GET['date_from']) ? $conn->real_escape_string($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? $conn->real_escape_string($_GET['date_to']) : '';

if (empty($company)) {
    echo json_encode(['error' => 'Company name is required']);
    exit;
}

// 1) Get Company ID
$sqlId = "SELECT CompanyID FROM Company WHERE CompanyName = '$company'";
$resId = $conn->query($sqlId);
if (!$resId || $resId->num_rows == 0) {
    echo json_encode(['error' => 'Company not found']);
    exit;
}
$companyId = $resId->fetch_assoc()['CompanyID'];

// Date filter
$dateClauseShip = "";
$dateClauseEvent = "";
if (!empty($date_from) && !empty($date_to)) {
    $dateClauseShip = "AND s.ActualDate BETWEEN '$date_from' AND '$date_to'";
    $dateClauseEvent = "AND e.EventDate BETWEEN '$date_from' AND '$date_to'"; 
}

// 2) Statistics
$sqlStats = "
    SELECT 
        COUNT(*) as total_shipments,
        SUM(CASE WHEN ActualDate <= PromisedDate THEN 1 ELSE 0 END) as on_time_count,
        AVG(CASE WHEN ActualDate > PromisedDate THEN DATEDIFF(ActualDate, PromisedDate) ELSE NULL END) as avg_delay,
        STDDEV(CASE WHEN ActualDate > PromisedDate THEN DATEDIFF(ActualDate, PromisedDate) ELSE NULL END) as std_dev_delay
    FROM Shipping s
    WHERE s.SourceCompanyID = $companyId $dateClauseShip
";
$resStats = $conn->query($sqlStats);
$onTimeRate = 0;
$avgDelay = 0;
$stdDevDelay = 0;

if ($resStats && $stats = $resStats->fetch_assoc()) {
    if ($stats['total_shipments'] > 0) {
        $onTimeRate = ($stats['on_time_count'] / $stats['total_shipments']) * 100;
    }
    $avgDelay = $stats['avg_delay'] ? round($stats['avg_delay'], 2) : 0;
    $stdDevDelay = $stats['std_dev_delay'] ? round($stats['std_dev_delay'], 2) : 0;
}

// 3) Financial Health 
$dateCol = null;
$scoreCol = 'HealthScore';
$yearCol = null;
$quarterCol = null;
$hasScore = false;

$colsSql = "SHOW COLUMNS FROM FinancialReport";
$colsRes = $conn->query($colsSql);
if ($colsRes) {
    while ($row = $colsRes->fetch_assoc()) {
        $field = $row['Field'];
        if (strcasecmp($field, 'HealthScore') === 0) {
            $hasScore = true;
        }
        
        // Date Column
        if ($dateCol === null) {
            if (stripos($field, 'Date') !== false || stripos($field, 'Time') !== false) {
                $dateCol = $field;
            }
        } else {
            // Prefer 'ReportDate' or 'Date' over 'UpdateDate'
            if (stripos($field, 'Report') !== false || strcasecmp($field, 'Date') === 0) {
                $dateCol = $field;
            }
        }

        // Year or Quarter
        if (stripos($field, 'Year') !== false) {
            $yearCol = $field;
        }
        if (stripos($field, 'Quarter') !== false || stripos($field, 'Qtr') !== false) {
            $quarterCol = $field;
        }
    }
}

$finHealth = "N/A";
$finHealthQuarters = null;

// We'll also reuse this for graph data later
if ($hasScore) {
    $quarters = [1 => null, 2 => null, 3 => null, 4 => null];
    $latestOverall = null; 

    if ($dateCol) {
        $sqlFin = "SELECT $scoreCol, $dateCol FROM FinancialReport WHERE CompanyID = $companyId";
        
        // Apply Date Filters if needed
        if (!empty($date_from)) {
            $sqlFin .= " AND $dateCol >= '$date_from'";
        }
        if (!empty($date_to)) {
            $sqlFin .= " AND $dateCol <= '$date_to'";
        }
        
        // Order by Date DESC to get latest within range
        $sqlFin .= " ORDER BY $dateCol DESC LIMIT 100";
        
        $resFin = $conn->query($sqlFin);
        
        if ($resFin) {
            while ($row = $resFin->fetch_assoc()) {
                $score = $row[$scoreCol];
                $dateVal = $row[$dateCol];
                
                if ($dateVal && $ts = strtotime($dateVal)) {
                    $q = (int)ceil(date('n', $ts) / 3);
                    if ($q >= 1 && $q <= 4) {
                
                        if ($quarters[$q] === null) {
                            $quarters[$q] = ['ts' => $ts, 'score' => $score];
                        }
                    }
                    if ($latestOverall === null) {
                        $latestOverall = ['ts' => $ts, 'score' => $score];
                    }
                }
            }
        }
    } elseif ($yearCol && $quarterCol) {
        $sqlFin = "SELECT $scoreCol, $yearCol, $quarterCol FROM FinancialReport WHERE CompanyID = $companyId";
        
        // No date column
        $sqlFin .= " ORDER BY $yearCol DESC, $quarterCol DESC LIMIT 200";
        
        $resFin = $conn->query($sqlFin);

        if ($resFin) {
            while ($row = $resFin->fetch_assoc()) {
                $score = $row[$scoreCol];
                $y = (int)$row[$yearCol];
                $qRaw = $row[$quarterCol];
                // Extract numeric quarter
                $q = (int)filter_var($qRaw, FILTER_SANITIZE_NUMBER_INT);
                
                if ($q >= 1 && $q <= 4 && $y > 0) {
                    // Approximate end of quarter date for applying date range filter
                    $qEndMonth = $q * 3;
                    $qEndDate = date("Y-m-t", strtotime("$y-$qEndMonth-01"));
                    
                    if (!empty($date_from) && $qEndDate < $date_from) continue;
                    if (!empty($date_to) && $qEndDate > $date_to) continue; 

                    $ts = $y * 10 + $q; 
                    
                    if ($quarters[$q] === null || $ts > $quarters[$q]['ts']) {
                        $quarters[$q] = ['ts' => $ts, 'score' => $score];
                    }
                    if ($latestOverall === null || $ts > $latestOverall['ts']) {
                        $latestOverall = ['ts' => $ts, 'score' => $score];
                    }
                }
            }
        }
    } else {
        // Fallback
        $sqlFin = "SELECT $scoreCol FROM FinancialReport WHERE CompanyID = $companyId LIMIT 1";
        $resFin = $conn->query($sqlFin);
        if ($resFin && $row = $resFin->fetch_assoc()) {
            $latestOverall = ['ts' => 0, 'score' => $row[$scoreCol]];
        }
    }

    if ($latestOverall) {
        $finHealth = $latestOverall['score'];
        // Only populate quarters  if we find quarter data
        if ($dateCol || ($yearCol && $quarterCol)) {
            $finHealthQuarters = [];
            foreach ($quarters as $q => $data) {
                $finHealthQuarters["Q$q"] = $data ? $data['score'] : 'N/A';
            }
        }
    }
}

// 4) Distribution of Disruption Events
$sqlDist = "
    SELECT dc.CategoryName, COUNT(*) as count
    FROM ImpactsCompany ic
    JOIN DisruptionEvent e ON ic.EventID = e.EventID
    JOIN DisruptionCategory dc ON e.CategoryID = dc.CategoryID
    WHERE ic.AffectedCompanyID = $companyId $dateClauseEvent
    GROUP BY dc.CategoryName
";
$resDist = $conn->query($sqlDist);
$distString = [];
if ($resDist) {
    while ($row = $resDist->fetch_assoc()) {
        $distString[] = $row['CategoryName'] . ": " . $row['count'];
    }
}
$distribution = empty($distString) ? "None" : implode(", ", $distString);

// 5) Table Data - Date, Event, Disruption Event ID
$sqlTable = "
    SELECT 
        e.EventDate as date,
        dc.CategoryName as event,
        e.EventID as shipment
    FROM ImpactsCompany ic
    JOIN DisruptionEvent e ON ic.EventID = e.EventID
    JOIN DisruptionCategory dc ON e.CategoryID = dc.CategoryID
    WHERE ic.AffectedCompanyID = $companyId 
    $dateClauseEvent
    ORDER BY e.EventDate DESC
    LIMIT 50
";

$resTable = $conn->query($sqlTable);
$tableData = [];
if ($resTable) {
    while ($row = $resTable->fetch_assoc()) {
        $tableData[] = $row;
    }
} else {
   
}

// 6) Graph Data of Financial Health

$graphData = [];
if (isset($quarters) && is_array($quarters)) {
    foreach ([1, 2, 3, 4] as $q) {
        $val = isset($quarters[$q]) && $quarters[$q] !== null ? $quarters[$q]['score'] : null;
        $graphData[] = [
            'quarter' => $q,
            'score' => $val
        ];
    }
}
// Build and send JSON response
$response = [
    'stats' => [
        'name' => $company,
        'on_time_rate' => number_format($onTimeRate, 2) . '%',
        'avg_delay' => $avgDelay . ' days',
        'std_dev_delay' => $stdDevDelay . ' days',
        'fin_health' => $finHealth,
        'fin_health_quarters' => $finHealthQuarters,
        'distribution' => $distribution
    ],
    'table' => $tableData,
    'graph' => $graphData
];

echo json_encode($response);
$conn->close();
?>
<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$currentUser = getCurrentUser();

// Get request IDs from query parameter
$requestIds = $_GET['ids'] ?? '';
if (empty($requestIds)) {
    http_response_code(400);
    echo json_encode(['error' => 'No request IDs provided']);
    exit();
}

// Convert to array and validate
$idArray = array_map('intval', explode(',', $requestIds));
$idArray = array_filter($idArray, function($id) { return $id > 0; });

if (empty($idArray)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request IDs']);
    exit();
}

// Check permissions
if ($currentUser['role'] === 'transporter') {
    // Transporters can only export their assigned requests
    $count = $db->fetch("
        SELECT COUNT(*) as count FROM transport_requests
        WHERE id IN (" . str_repeat('?,', count($idArray) - 1) . "?) 
        AND transporter_id = ?
    ", array_merge($idArray, [$currentUser['id']]))['count'] ?? 0;
    
    if ($count != count($idArray)) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        exit();
    }
} elseif ($currentUser['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit();
}

// Get transport requests with details
$requests = $db->fetchAll("
    SELECT tr.*, 
           u.full_name as client_name, u.phone as client_phone, u.email as client_email,
           pc.name as produce_category, p.name as produce_name,
           tp.full_name as transporter_name, tp.phone as transporter_phone
    FROM transport_requests tr
    JOIN users u ON tr.client_id = u.id
    LEFT JOIN users tp ON tr.transporter_id = tp.id
    LEFT JOIN produce_categories pc ON tr.produce_category_id = pc.id
    LEFT JOIN produce p ON tr.produce_id = p.id
    WHERE tr.id IN (" . str_repeat('?,', count($idArray) - 1) . "?)
    ORDER BY tr.scheduled_date ASC
", $idArray);

if (empty($requests)) {
    http_response_code(404);
    echo json_encode(['error' => 'No requests found']);
    exit();
}

// Set headers for CSV download
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="transport_route_' . date('Y-m-d_H-i-s') . '.csv"');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: 0');

// Open output stream
$output = fopen('php://output', 'w');

// Add BOM for proper UTF-8 encoding in Excel
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// CSV headers
$headers = [
    'Sequence',
    'Request ID',
    'Client Name',
    'Client Phone',
    'Client Email',
    'Pickup Location',
    'Delivery Location',
    'Produce Category',
    'Produce Name',
    'Weight (kg)',
    'Volume (m³)',
    'Amount (KES)',
    'Scheduled Date',
    'Scheduled Time',
    'Status',
    'Transporter Name',
    'Transporter Phone',
    'Special Instructions',
    'Distance (km)',
    'Estimated Time (min)'
];
fputcsv($output, $headers);

// Calculate route sequence and distances
$totalDistance = 0;
$totalTime = 0;
$sequence = 1;
$prevLat = null;
$prevLng = null;

foreach ($requests as $request) {
    // Calculate distance from previous location
    $distance = 0;
    if ($prevLat && $prevLng) {
        $distance = calculateDistance(
            $prevLat, $prevLng,
            $request['pickup_latitude'], $request['pickup_longitude']
        );
    }
    
    // Estimate time (30 minutes per request + travel time)
    $travelTime = ($distance / 40) * 60; // 40 km/h average
    $estimatedTime = 30 + $travelTime;
    
    $totalDistance += $distance;
    $totalTime += $estimatedTime;
    
    // Add row to CSV
    $row = [
        $sequence,
        $request['id'],
        $request['client_name'],
        $request['client_phone'],
        $request['client_email'],
        $request['pickup_location'],
        $request['delivery_location'],
        $request['produce_category'] ?: '',
        $request['produce_name'] ?: 'Mixed',
        $request['weight'] ?: 0,
        $request['volume'] ?: 0,
        $request['amount'],
        formatDate($request['scheduled_date']),
        formatTime($request['scheduled_date']),
        ucfirst($request['status']),
        $request['transporter_name'] ?: '',
        $request['transporter_phone'] ?: '',
        $request['special_instructions'] ?: '',
        round($distance, 2),
        round($estimatedTime)
    ];
    fputcsv($output, $row);
    
    $prevLat = $request['pickup_latitude'];
    $prevLng = $request['pickup_longitude'];
    $sequence++;
}

// Add summary section
fputcsv($output, []);
fputcsv($output, ['ROUTE SUMMARY']);
fputcsv($output, ['Total Requests', count($requests)]);
fputcsv($output, ['Total Distance (km)', round($totalDistance, 2)]);
fputcsv($output, ['Total Estimated Time (min)', round($totalTime)]);
fputcsv($output, ['Total Weight (kg)', array_sum(array_column($requests, 'weight'))]);
fputcsv($output, ['Total Volume (m³)', array_sum(array_column($requests, 'volume'))]);
fputcsv($output, ['Total Amount (KES)', array_sum(array_column($requests, 'amount'))]);

// Add pickup/delivery addresses for GPS navigation
fputcsv($output, []);
fputcsv($output, ['GPS NAVIGATION POINTS']);
fputcsv($output, ['Type', 'Location', 'Latitude', 'Longitude']);

foreach ($requests as $index => $request) {
    fputcsv($output, [
        'Pickup #' . ($index + 1),
        $request['pickup_location'],
        $request['pickup_latitude'],
        $request['pickup_longitude']
    ]);
    
    fputcsv($output, [
        'Delivery #' . ($index + 1),
        $request['delivery_location'],
        $request['delivery_latitude'],
        $request['delivery_longitude']
    ]);
}

// Add contact information
fputcsv($output, []);
fputcsv($output, ['CONTACT INFORMATION']);
fputcsv($output, ['Client', 'Phone', 'Email']);

$clients = [];
foreach ($requests as $request) {
    $clientKey = $request['client_name'] . '|' . $request['client_phone'];
    if (!isset($clients[$clientKey])) {
        $clients[$clientKey] = [
            'name' => $request['client_name'],
            'phone' => $request['client_phone'],
            'email' => $request['client_email']
        ];
        fputcsv($output, [
            $request['client_name'],
            $request['client_phone'],
            $request['client_email']
        ]);
    }
}

// Add export metadata
fputcsv($output, []);
fputcsv($output, ['EXPORT INFORMATION']);
fputcsv($output, ['Export Date', date('Y-m-d H:i:s')]);
fputcsv($output, ['Exported By', $currentUser['full_name']]);
fputcsv($output, ['User Role', ucfirst($currentUser['role'])]);
fputcsv($output, ['System', APP_NAME]);

// Close output stream
fclose($output);

// Log export activity
logActivity($currentUser['id'], 'export_transport_route', "Exported route for " . count($requests) . " transport requests");

// Helper functions
function calculateDistance($lat1, $lon1, $lat2, $lon2) {
    $earthRadius = 6371; // Earth's radius in kilometers
    
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    
    $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon/2) * sin($dLon/2);
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    
    return $earthRadius * $c;
}

function formatDate($date) {
    return date('d M Y', strtotime($date));
}

function formatTime($date) {
    return date('h:i A', strtotime($date));
}

// Exit script
exit();
?>

<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check if user is logged in
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$currentUser = getCurrentUser();
$stat = $_GET['stat'] ?? '';

// Only transporters can access their stats
if ($currentUser['role'] !== 'transporter') {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit();
}

switch ($stat) {
    case 'completed_today':
        $count = $db->fetch("
            SELECT COUNT(*) as count FROM transport_requests
            WHERE transporter_id = ? AND status = 'completed' 
            AND DATE(updated_at) = CURDATE()
        ", [$currentUser['id']])['count'] ?? 0;
        
        echo json_encode([
            'success' => true,
            'count' => $count
        ]);
        break;
        
    case 'total_distance_today':
        $totalDistance = 0;
        $todayRoutes = $db->fetchAll("
            SELECT tr.id FROM transport_requests tr
            WHERE tr.transporter_id = ? AND tr.status = 'completed'
            AND DATE(tr.updated_at) = CURDATE()
        ", [$currentUser['id']]);
        
        foreach ($todayRoutes as $route) {
            $locations = $db->fetchAll("
                SELECT latitude, longitude FROM transport_locations
                WHERE transport_request_id = ?
                ORDER BY timestamp ASC
            ", [$route['id']]);
            
            if (count($locations) > 1) {
                for ($i = 1; $i < count($locations); $i++) {
                    $distance = calculateDistance(
                        $locations[$i-1]['latitude'],
                        $locations[$i-1]['longitude'],
                        $locations[$i]['latitude'],
                        $locations[$i]['longitude']
                    );
                    $totalDistance += $distance;
                }
            }
        }
        
        echo json_encode([
            'success' => true,
            'distance' => round($totalDistance, 2)
        ]);
        break;
        
    case 'average_speed_today':
        $avgSpeed = 0;
        $speedData = $db->fetchAll("
            SELECT AVG(current_speed) as avg_speed FROM transporter_profiles tp
            WHERE tp.user_id = ? AND tp.last_location_update >= CURDATE()
        ", [$currentUser['id']]);
        
        if ($speedData && $speedData[0]['avg_speed']) {
            $avgSpeed = $speedData[0]['avg_speed'] * 3.6; // Convert m/s to km/h
        }
        
        echo json_encode([
            'success' => true,
            'speed' => round($avgSpeed, 1)
        ]);
        break;
        
    case 'active_time_today':
        $activeTime = 0;
        $activeRequests = $db->fetchAll("
            SELECT tr.created_at, tr.updated_at FROM transport_requests tr
            WHERE tr.transporter_id = ? AND tr.status IN ('assigned', 'picked_up', 'in_transit')
            AND DATE(tr.created_at) = CURDATE()
        ", [$currentUser['id']]);
        
        foreach ($activeRequests as $request) {
            $startTime = new DateTime($request['created_at']);
            $endTime = new DateTime($request['updated_at']);
            $activeTime += ($endTime->getTimestamp() - $startTime->getTimestamp()) / 3600; // Convert to hours
        }
        
        echo json_encode([
            'success' => true,
            'hours' => round($activeTime, 1)
        ]);
        break;
        
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid stat parameter']);
        break;
}

// Helper function
function calculateDistance($lat1, $lon1, $lat2, $lon2) {
    $earthRadius = 6371; // Earth's radius in kilometers
    
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    
    $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon/2) * sin($dLon/2);
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    
    return $earthRadius * $c;
}
?>

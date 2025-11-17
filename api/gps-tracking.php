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
$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

switch ($action) {
    case 'update_location':
        if ($method === 'POST') {
            // Only transporters can update their location
            if ($currentUser['role'] !== 'transporter') {
                http_response_code(403);
                echo json_encode(['error' => 'Only transporters can update location']);
                exit();
            }
            
            $latitude = floatval($_POST['latitude'] ?? 0);
            $longitude = floatval($_POST['longitude'] ?? 0);
            $accuracy = floatval($_POST['accuracy'] ?? 0);
            $speed = floatval($_POST['speed'] ?? 0);
            $heading = floatval($_POST['heading'] ?? 0);
            $timestamp = $_POST['timestamp'] ?? date('Y-m-d H:i:s');
            
            // Validate coordinates
            if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid coordinates']);
                exit();
            }
            
            // Update transporter location
            $db->update('transporter_profiles', [
                'current_latitude' => $latitude,
                'current_longitude' => $longitude,
                'last_location_update' => date('Y-m-d H:i:s'),
                'location_accuracy' => $accuracy,
                'current_speed' => $speed,
                'current_heading' => $heading
            ], 'user_id = ?', [$currentUser['id']]);
            
            // Log location update
            logActivity($currentUser['id'], 'location_updated', "Location updated to {$latitude}, {$longitude}");
            
            // Update active transport requests with location
            $activeRequests = $db->fetchAll("
                SELECT tr.id FROM transport_requests tr
                WHERE tr.transporter_id = ? AND tr.status IN ('assigned', 'picked_up')
            ", [$currentUser['id']]);
            
            foreach ($activeRequests as $request) {
                $db->insert('transport_locations', [
                    'transport_request_id' => $request['id'],
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'accuracy' => $accuracy,
                    'speed' => $speed,
                    'heading' => $heading,
                    'timestamp' => $timestamp
                ]);
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Location updated successfully',
                'timestamp' => date('Y-m-d H:i:s')
            ]);
        } else {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
        }
        break;
        
    case 'get_location':
        if ($method === 'GET') {
            $transporterId = intval($_GET['transporter_id'] ?? 0);
            
            if ($transporterId <= 0) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid transporter ID']);
                exit();
            }
            
            // Check if user has permission to view this transporter's location
            if ($currentUser['role'] === 'farmer') {
                // Farmers can only see location of transporters assigned to their requests
                $hasPermission = $db->fetch("
                    SELECT COUNT(*) as count FROM transport_requests tr
                    WHERE tr.transporter_id = ? AND tr.client_id = ? AND tr.status IN ('assigned', 'picked_up')
                ", [$transporterId, $currentUser['id']])['count'] > 0;
                
                if (!$hasPermission) {
                    http_response_code(403);
                    echo json_encode(['error' => 'Permission denied']);
                    exit();
                }
            } elseif ($currentUser['role'] !== 'admin' && $currentUser['id'] !== $transporterId) {
                http_response_code(403);
                echo json_encode(['error' => 'Permission denied']);
                exit();
            }
            
            // Get current location
            $transporter = $db->fetch("
                SELECT tp.*, u.full_name, u.phone
                FROM transporter_profiles tp
                JOIN users u ON tp.user_id = u.id
                WHERE tp.user_id = ?
            ", [$transporterId]);
            
            if (!$transporter) {
                http_response_code(404);
                echo json_encode(['error' => 'Transporter not found']);
                exit();
            }
            
            // Get recent location history
            $locationHistory = $db->fetchAll("
                SELECT * FROM transport_locations tl
                JOIN transport_requests tr ON tl.transport_request_id = tr.id
                WHERE tr.transporter_id = ? AND tl.timestamp >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
                ORDER BY tl.timestamp DESC
                LIMIT 100
            ", [$transporterId]);
            
            echo json_encode([
                'success' => true,
                'transporter' => [
                    'id' => $transporterId,
                    'name' => $transporter['full_name'],
                    'phone' => $transporter['phone'],
                    'current_location' => [
                        'latitude' => $transporter['current_latitude'],
                        'longitude' => $transporter['current_longitude'],
                        'accuracy' => $transporter['location_accuracy'],
                        'speed' => $transporter['current_speed'],
                        'heading' => $transporter['current_heading'],
                        'last_update' => $transporter['last_location_update']
                    ],
                    'location_history' => $locationHistory
                ]
            ]);
        } else {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
        }
        break;
        
    case 'get_route':
        if ($method === 'GET') {
            $requestId = intval($_GET['request_id'] ?? 0);
            
            if ($requestId <= 0) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid request ID']);
                exit();
            }
            
            // Check permission
            $request = $db->fetch("
                SELECT tr.*, u.full_name as client_name
                FROM transport_requests tr
                JOIN users u ON tr.client_id = u.id
                WHERE tr.id = ? AND (tr.client_id = ? OR tr.transporter_id = ? OR ? = 'admin')
            ", [$requestId, $currentUser['id'], $currentUser['id'], $currentUser['role']]);
            
            if (!$request) {
                http_response_code(404);
                echo json_encode(['error' => 'Request not found or permission denied']);
                exit();
            }
            
            // Get route points
            $routePoints = $db->fetchAll("
                SELECT * FROM transport_locations
                WHERE transport_request_id = ?
                ORDER BY timestamp ASC
            ", [$requestId]);
            
            // Calculate route statistics
            $totalDistance = 0;
            $estimatedTime = 0;
            
            if (count($routePoints) > 1) {
                for ($i = 1; $i < count($routePoints); $i++) {
                    $distance = calculateDistance(
                        $routePoints[$i-1]['latitude'],
                        $routePoints[$i-1]['longitude'],
                        $routePoints[$i]['latitude'],
                        $routePoints[$i]['longitude']
                    );
                    $totalDistance += $distance;
                }
                
                // Estimate time based on average speed
                $avgSpeed = 40; // km/h
                $estimatedTime = ($totalDistance / $avgSpeed) * 60; // minutes
            }
            
            echo json_encode([
                'success' => true,
                'request' => [
                    'id' => $requestId,
                    'status' => $request['status'],
                    'pickup_location' => [
                        'latitude' => $request['pickup_latitude'],
                        'longitude' => $request['pickup_longitude'],
                        'address' => $request['pickup_location']
                    ],
                    'delivery_location' => [
                        'latitude' => $request['delivery_latitude'],
                        'longitude' => $request['delivery_longitude'],
                        'address' => $request['delivery_location']
                    ]
                ],
                'route' => [
                    'points' => $routePoints,
                    'total_distance_km' => round($totalDistance, 2),
                    'estimated_time_minutes' => round($estimatedTime),
                    'progress_percentage' => $request['status'] === 'completed' ? 100 : 
                                           ($request['status'] === 'picked_up' ? 60 : 
                                           ($request['status'] === 'assigned' ? 20 : 0))
                ]
            ]);
        } else {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
        }
        break;
        
    case 'optimize_route':
        if ($method === 'POST') {
            // Only transporters can optimize routes
            if ($currentUser['role'] !== 'transporter') {
                http_response_code(403);
                echo json_encode(['error' => 'Only transporters can optimize routes']);
                exit();
            }
            
            $requestIds = $_POST['request_ids'] ?? [];
            
            if (empty($requestIds) || !is_array($requestIds)) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid request IDs']);
                exit();
            }
            
            // Get all requests
            $requests = $db->fetchAll("
                SELECT tr.*, u.full_name as client_name
                FROM transport_requests tr
                JOIN users u ON tr.client_id = u.id
                WHERE tr.id IN (" . str_repeat('?,', count($requestIds) - 1) . "?) AND tr.transporter_id = ? AND tr.status = 'assigned'
            ", array_merge($requestIds, [$currentUser['id']]));
            
            if (empty($requests)) {
                http_response_code(404);
                echo json_encode(['error' => 'No valid requests found']);
                exit();
            }
            
            // Simple route optimization (nearest neighbor algorithm)
            $optimizedRoute = optimizeRoute($requests);
            
            echo json_encode([
                'success' => true,
                'optimized_route' => $optimizedRoute,
                'total_distance' => array_sum(array_column($optimizedRoute, 'distance_from_previous')),
                'estimated_time' => count($optimizedRoute) * 30 // 30 minutes per delivery estimate
            ]);
        } else {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
        }
        break;
        
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
        break;
}

// Helper functions
function calculateDistance($lat1, $lon1, $lat2, $lon2) {
    $earthRadius = 6371; // Earth's radius in kilometers
    
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    
    $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon/2) * sin($dLon/2);
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    
    return $earthRadius * $c;
}

function optimizeRoute($requests) {
    if (empty($requests)) {
        return [];
    }
    
    $unvisited = $requests;
    $route = [];
    $currentLat = null;
    $currentLon = null;
    
    // Start with the first request
    $current = array_shift($unvisited);
    $route[] = array_merge($current, ['distance_from_previous' => 0]);
    $currentLat = $current['pickup_latitude'];
    $currentLon = $current['pickup_longitude'];
    
    // Find nearest neighbor for each remaining request
    while (!empty($unvisited)) {
        $nearestIndex = 0;
        $nearestDistance = PHP_FLOAT_MAX;
        
        foreach ($unvisited as $index => $request) {
            $distance = calculateDistance(
                $currentLat, $currentLon,
                $request['pickup_latitude'], $request['pickup_longitude']
            );
            
            if ($distance < $nearestDistance) {
                $nearestDistance = $distance;
                $nearestIndex = $index;
            }
        }
        
        $next = $unvisited[$nearestIndex];
        $route[] = array_merge($next, ['distance_from_previous' => $nearestDistance]);
        
        $currentLat = $next['pickup_latitude'];
        $currentLon = $next['pickup_longitude'];
        array_splice($unvisited, $nearestIndex, 1);
    }
    
    return $route;
}
?>

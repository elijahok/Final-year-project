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
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'POST':
        // Only transporters can optimize routes
        if ($currentUser['role'] !== 'transporter') {
            http_response_code(403);
            echo json_encode(['error' => 'Only transporters can optimize routes']);
            exit();
        }
        
        $requestData = json_decode(file_get_contents('php://input'), true);
        $requestIds = $requestData['request_ids'] ?? [];
        $optimizationType = $requestData['type'] ?? 'nearest_neighbor'; // nearest_neighbor, shortest_path, time_based
        
        if (empty($requestIds) || !is_array($requestIds)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid request IDs']);
            exit();
        }
        
        // Validate CSRF token
        if (!isset($requestData['csrf_token']) || !verifyCSRFToken($requestData['csrf_token'])) {
            http_response_code(403);
            echo json_encode(['error' => 'Invalid CSRF token']);
            exit();
        }
        
        // Get all requests with their locations
        $requests = $db->fetchAll("
            SELECT tr.*, u.full_name as client_name, u.phone as client_phone,
                   tp.current_latitude as current_lat, tp.current_longitude as current_lng
            FROM transport_requests tr
            JOIN users u ON tr.client_id = u.id
            LEFT JOIN transporter_profiles tp ON tp.user_id = ?
            WHERE tr.id IN (" . str_repeat('?,', count($requestIds) - 1) . "?) 
            AND tr.transporter_id = ? AND tr.status = 'assigned'
        ", array_merge([$currentUser['id']], $requestIds, [$currentUser['id']]));
        
        if (empty($requests)) {
            http_response_code(404);
            echo json_encode(['error' => 'No valid requests found']);
            exit();
        }
        
        // Optimize route based on type
        $optimizedRoute = [];
        switch ($optimizationType) {
            case 'nearest_neighbor':
                $optimizedRoute = optimizeNearestNeighbor($requests);
                break;
            case 'shortest_path':
                $optimizedRoute = optimizeShortestPath($requests);
                break;
            case 'time_based':
                $optimizedRoute = optimizeTimeBased($requests);
                break;
            default:
                $optimizedRoute = optimizeNearestNeighbor($requests);
                break;
        }
        
        // Calculate route statistics
        $stats = calculateRouteStats($optimizedRoute);
        
        // Log route optimization
        logActivity($currentUser['id'], 'route_optimized', "Optimized route for " . count($requests) . " requests using {$optimizationType}");
        
        echo json_encode([
            'success' => true,
            'route' => $optimizedRoute,
            'statistics' => $stats,
            'optimization_type' => $optimizationType
        ]);
        break;
        
    case 'GET':
        // Get route history and analytics
        $transporterId = intval($_GET['transporter_id'] ?? 0);
        $dateFrom = $_GET['date_from'] ?? date('Y-m-d', strtotime('-7 days'));
        $dateTo = $_GET['date_to'] ?? date('Y-m-d');
        
        // Check permissions
        if ($currentUser['role'] === 'transporter') {
            $transporterId = $currentUser['id'];
        } elseif ($currentUser['role'] === 'admin') {
            if ($transporterId <= 0) {
                http_response_code(400);
                echo json_encode(['error' => 'Transporter ID required']);
                exit();
            }
        } else {
            http_response_code(403);
            echo json_encode(['error' => 'Access denied']);
            exit();
        }
        
        // Get route history
        $routeHistory = $db->fetchAll("
            SELECT tr.*, u.full_name as client_name,
                   COUNT(tl.id) as location_points,
                   MIN(tl.timestamp) as start_time,
                   MAX(tl.timestamp) as end_time
            FROM transport_requests tr
            JOIN users u ON tr.client_id = u.id
            LEFT JOIN transport_locations tl ON tr.id = tl.transport_request_id
            WHERE tr.transporter_id = ? AND tr.status = 'completed'
            AND DATE(tr.updated_at) BETWEEN ? AND ?
            GROUP BY tr.id
            ORDER BY tr.updated_at DESC
        ", [$transporterId, $dateFrom, $dateTo]);
        
        // Calculate analytics
        $analytics = [
            'total_routes' => count($routeHistory),
            'total_distance' => 0,
            'total_time' => 0,
            'average_route_distance' => 0,
            'average_route_time' => 0,
            'optimization_savings' => 0
        ];
        
        foreach ($routeHistory as $route) {
            $routeDistance = calculateRouteDistance($route['id']);
            $routeTime = calculateRouteTime($route['start_time'], $route['end_time']);
            
            $analytics['total_distance'] += $routeDistance;
            $analytics['total_time'] += $routeTime;
        }
        
        if ($analytics['total_routes'] > 0) {
            $analytics['average_route_distance'] = $analytics['total_distance'] / $analytics['total_routes'];
            $analytics['average_route_time'] = $analytics['total_time'] / $analytics['total_routes'];
        }
        
        echo json_encode([
            'success' => true,
            'history' => $routeHistory,
            'analytics' => $analytics,
            'date_range' => [
                'from' => $dateFrom,
                'to' => $dateTo
            ]
        ]);
        break;
        
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        break;
}

// Route optimization algorithms
function optimizeNearestNeighbor($requests) {
    if (empty($requests)) {
        return [];
    }
    
    $unvisited = $requests;
    $route = [];
    $currentLat = null;
    $currentLng = null;
    
    // Start from transporter's current location or first request
    $transporter = array_shift($unvisited);
    if ($transporter['current_lat'] && $transporter['current_lng']) {
        $currentLat = $transporter['current_lat'];
        $currentLng = $transporter['current_lng'];
    } else {
        // Use first request's pickup location as starting point
        $currentLat = $transporter['pickup_latitude'];
        $currentLng = $transporter['pickup_longitude'];
    }
    
    $route[] = array_merge($transporter, ['sequence' => 1, 'distance_from_previous' => 0]);
    
    // Find nearest neighbor for each remaining request
    $sequence = 2;
    while (!empty($unvisited)) {
        $nearestIndex = 0;
        $nearestDistance = PHP_FLOAT_MAX;
        
        foreach ($unvisited as $index => $request) {
            $distance = calculateDistance(
                $currentLat, $currentLng,
                $request['pickup_latitude'], $request['pickup_longitude']
            );
            
            if ($distance < $nearestDistance) {
                $nearestDistance = $distance;
                $nearestIndex = $index;
            }
        }
        
        $next = $unvisited[$nearestIndex];
        $route[] = array_merge($next, ['sequence' => $sequence, 'distance_from_previous' => $nearestDistance]);
        
        $currentLat = $next['pickup_latitude'];
        $currentLng = $next['pickup_longitude'];
        array_splice($unvisited, $nearestIndex, 1);
        $sequence++;
    }
    
    return $route;
}

function optimizeShortestPath($requests) {
    // This is a simplified implementation
    // In production, use a proper algorithm like Dijkstra's or TSP solver
    return optimizeNearestNeighbor($requests);
}

function optimizeTimeBased($requests) {
    // Sort by scheduled date first, then optimize within time windows
    usort($requests, function($a, $b) {
        return strtotime($a['scheduled_date']) - strtotime($b['scheduled_date']);
    });
    
    $timeWindows = [];
    foreach ($requests as $request) {
        $date = date('Y-m-d', strtotime($request['scheduled_date']));
        if (!isset($timeWindows[$date])) {
            $timeWindows[$date] = [];
        }
        $timeWindows[$date][] = $request;
    }
    
    $optimizedRoute = [];
    $sequence = 1;
    
    foreach ($timeWindows as $date => $windowRequests) {
        if (count($windowRequests) > 1) {
            $optimizedWindow = optimizeNearestNeighbor($windowRequests);
            foreach ($optimizedWindow as $request) {
                $request['sequence'] = $sequence++;
                $optimizedRoute[] = $request;
            }
        } else {
            $windowRequests[0]['sequence'] = $sequence++;
            $windowRequests[0]['distance_from_previous'] = 0;
            $optimizedRoute[] = $windowRequests[0];
        }
    }
    
    return $optimizedRoute;
}

function calculateRouteStats($route) {
    $totalDistance = array_sum(array_column($route, 'distance_from_previous'));
    $totalTime = 0;
    $totalWeight = 0;
    $totalVolume = 0;
    
    foreach ($route as $request) {
        // Estimate time (30 minutes per request + travel time)
        $travelTime = ($request['distance_from_previous'] / 40) * 60; // 40 km/h average
        $totalTime += 30 + $travelTime;
        
        // Sum up weight and volume
        $totalWeight += $request['weight'] ?? 0;
        $totalVolume += $request['volume'] ?? 0;
    }
    
    return [
        'total_distance_km' => round($totalDistance, 2),
        'total_time_minutes' => round($totalTime),
        'total_weight_kg' => $totalWeight,
        'total_volume_m3' => $totalVolume,
        'number_of_stops' => count($route),
        'average_distance_per_stop' => count($route) > 0 ? round($totalDistance / count($route), 2) : 0,
        'estimated_completion_time' => date('H:i', strtotime('+' . $totalTime . ' minutes'))
    ];
}

function calculateRouteDistance($requestId) {
    $locations = $db->fetchAll("
        SELECT latitude, longitude FROM transport_locations
        WHERE transport_request_id = ?
        ORDER BY timestamp ASC
    ", [$requestId]);
    
    $totalDistance = 0;
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
    
    return $totalDistance;
}

function calculateRouteTime($startTime, $endTime) {
    if (!$startTime || !$endTime) {
        return 0;
    }
    
    $start = new DateTime($startTime);
    $end = new DateTime($endTime);
    return ($end->getTimestamp() - $start->getTimestamp()) / 3600; // Convert to hours
}

function calculateDistance($lat1, $lon1, $lat2, $lon2) {
    $earthRadius = 6371; // Earth's radius in kilometers
    
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    
    $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon/2) * sin($dLon/2);
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    
    return $earthRadius * $c;
}
?>

<?php
// Utility Functions

// Security Functions
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(CSRF_TOKEN_LENGTH));
    }
    return $_SESSION['csrf_token'];
}

function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// TOTP Functions for 2FA
function generateTOTPSecret() {
    return bin2hex(random_bytes(16));
}

function verifyTOTP($secret, $code) {
    // This is a simplified implementation
    // In production, use a proper TOTP library
    $timestamp = floor(time() / 30);
    $expectedCode = substr(md5($secret . $timestamp), 0, 6);
    return hash_equals($expectedCode, $code);
}

function generateTOTPQRCode($secret, $email, $appName) {
    // This is a placeholder - in production, use a proper QR code library
    $otpUrl = "otpauth://totp/{$appName}:{$email}?secret={$secret}&issuer={$appName}";
    return "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=" . urlencode($otpUrl);
}

function sanitizeInput($input) {
    if (is_array($input)) {
        return array_map('sanitizeInput', $input);
    }
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT, ['cost' => 12]);
}

function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

// Authentication Functions
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . '/public/login.php');
        exit();
    }
}

function hasRole($role) {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === $role;
}

function requireRole($role) {
    requireLogin();
    if (!hasRole($role)) {
        $_SESSION['error'] = 'Access denied. Insufficient permissions.';
        header('Location: ' . BASE_URL . '/public/dashboard.php');
        exit();
    }
}

// User Functions
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    global $db;
    return $db->fetch("SELECT * FROM users WHERE id = ?", [$_SESSION['user_id']]);
}

function logActivity($userId, $action, $details = '') {
    global $db;
    
    $data = [
        'user_id' => $userId,
        'action' => $action,
        'details' => $details,
        'ip_address' => $_SERVER['REMOTE_ADDR'],
        'user_agent' => $_SERVER['HTTP_USER_AGENT'],
        'created_at' => date('Y-m-d H:i:s')
    ];
    
    return $db->insert('audit_log', $data);
}

// File Upload Functions
function uploadFile($file, $destination, $allowedTypes = []) {
    if (empty($allowedTypes)) {
        $allowedTypes = ALLOWED_FILE_TYPES;
    }
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'File upload error'];
    }
    
    if ($file['size'] > MAX_FILE_SIZE) {
        return ['success' => false, 'error' => 'File too large'];
    }
    
    $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($fileExt, $allowedTypes)) {
        return ['success' => false, 'error' => 'Invalid file type'];
    }
    
    $fileName = uniqid() . '.' . $fileExt;
    $filePath = $destination . '/' . $fileName;
    
    if (!is_dir($destination)) {
        mkdir($destination, 0755, true);
    }
    
    if (move_uploaded_file($file['tmp_name'], $filePath)) {
        return ['success' => true, 'filename' => $fileName, 'path' => $filePath];
    }
    
    return ['success' => false, 'error' => 'Failed to move uploaded file'];
}

// Notification Functions
function addNotification($userId, $title, $message, $type = 'info') {
    global $db;
    
    $data = [
        'user_id' => $userId,
        'title' => $title,
        'message' => $message,
        'type' => $type,
        'is_read' => 0,
        'created_at' => date('Y-m-d H:i:s')
    ];
    
    return $db->insert('notifications', $data);
}

function getUnreadNotifications($userId) {
    global $db;
    return $db->fetchAll("SELECT * FROM notifications WHERE user_id = ? AND is_read = 0 ORDER BY created_at DESC", [$userId]);
}

function markNotificationAsRead($notificationId) {
    global $db;
    return $db->update('notifications', ['is_read' => 1], 'id = ?', [$notificationId]);
}

// GPS and Location Functions
function calculateDistance($lat1, $lon1, $lat2, $lon2) {
    $earthRadius = 6371; // Earth's radius in kilometers
    
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    
    $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon/2) * sin($dLon/2);
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    
    return $earthRadius * $c;
}

function getOptimalRoute($waypoints) {
    // Simple implementation - in production, use Google Maps API or similar
    if (count($waypoints) < 2) {
        return $waypoints;
    }
    
    // Nearest Neighbor Algorithm for route optimization
    $route = [];
    $unvisited = $waypoints;
    $current = array_shift($unvisited);
    $route[] = $current;
    
    while (!empty($unvisited)) {
        $nearestIndex = 0;
        $nearestDistance = calculateDistance(
            $current['lat'], $current['lon'],
            $unvisited[0]['lat'], $unvisited[0]['lon']
        );
        
        for ($i = 1; $i < count($unvisited); $i++) {
            $distance = calculateDistance(
                $current['lat'], $current['lon'],
                $unvisited[$i]['lat'], $unvisited[$i]['lon']
            );
            
            if ($distance < $nearestDistance) {
                $nearestDistance = $distance;
                $nearestIndex = $i;
            }
        }
        
        $current = $unvisited[$nearestIndex];
        $route[] = $current;
        array_splice($unvisited, $nearestIndex, 1);
    }
    
    return $route;
}

// Mobile Money Functions
function generateTransactionRef() {
    return 'TXN' . date('YmdHis') . strtoupper(substr(md5(uniqid()), 0, 6));
}

function processMobileMoneyPayment($phoneNumber, $amount, $provider, $reference) {
    // Placeholder for mobile money integration
    // In production, integrate with M-Pesa, Airtel Money APIs
    
    $data = [
        'transaction_ref' => $reference,
        'phone_number' => $phoneNumber,
        'amount' => $amount,
        'provider' => $provider,
        'status' => 'pending',
        'created_at' => date('Y-m-d H:i:s')
    ];
    
    global $db;
    $transactionId = $db->insert('mobile_money_transactions', $data);
    
    // Simulate processing (in production, this would be asynchronous)
    $success = rand(0, 1); // 50% success rate for demo
    
    if ($success) {
        $db->update('mobile_money_transactions', 
            ['status' => 'completed', 'processed_at' => date('Y-m-d H:i:s')], 
            'id = ?', 
            [$transactionId]
        );
        return ['success' => true, 'transaction_id' => $transactionId];
    } else {
        $db->update('mobile_money_transactions', 
            ['status' => 'failed', 'processed_at' => date('Y-m-d H:i:s')], 
            'id = ?', 
            [$transactionId]
        );
        return ['success' => false, 'error' => 'Payment failed'];
    }
}

// Scoring Functions
function calculateBidScore($priceScore, $complianceScore, $qualityScore, $deliveryScore) {
    $totalScore = ($priceScore * SCORE_WEIGHT_PRICE) + 
                  ($complianceScore * SCORE_WEIGHT_COMPLIANCE) + 
                  ($qualityScore * SCORE_WEIGHT_QUALITY) + 
                  ($deliveryScore * SCORE_WEIGHT_DELIVERY);
    
    return round($totalScore, 2);
}

// Format Functions
function formatCurrency($amount, $currency = 'KES') {
    return $currency . ' ' . number_format($amount, 2);
}

function formatDate($date, $format = 'd M Y H:i') {
    return date($format, strtotime($date));
}

function timeAgo($datetime) {
    $time = strtotime($datetime);
    $now = time();
    $diff = $now - $time;
    
    if ($diff < 60) {
        return 'just now';
    } elseif ($diff < 3600) {
        return floor($diff / 60) . ' minutes ago';
    } elseif ($diff < 86400) {
        return floor($diff / 3600) . ' hours ago';
    } elseif ($diff < 2592000) {
        return floor($diff / 86400) . ' days ago';
    } else {
        return formatDate($datetime);
    }
}

// Language Functions
function translate($key, $language = null) {
    $language = $language ?? $_SESSION['language'] ?? DEFAULT_LANGUAGE;
    
    $translations = [
        'en' => [
            'login' => 'Login',
            'logout' => 'Logout',
            'dashboard' => 'Dashboard',
            'tenders' => 'Tenders',
            'bids' => 'Bids',
            'profile' => 'Profile',
            'settings' => 'Settings'
        ],
        'sw' => [
            'login' => 'Ingia',
            'logout' => 'Toka',
            'dashboard' => 'Dashibodi',
            'tenders' => 'Zabuni',
            'bids' => 'Biashara',
            'profile' => 'Wasifu',
            'settings' => 'Mipangilio'
        ]
    ];
    
    return $translations[$language][$key] ?? $key;
}

// Pagination Functions
function paginate($query, $page = 1, $limit = 10) {
    $offset = ($page - 1) * $limit;
    
    global $db;
    
    // Get total count
    $countQuery = "SELECT COUNT(*) as total FROM ($query) as subquery";
    $total = $db->fetch($countQuery)['total'];
    
    // Get paginated results
    $paginatedQuery = $query . " LIMIT {$limit} OFFSET {$offset}";
    $results = $db->fetchAll($paginatedQuery);
    
    return [
        'data' => $results,
        'total' => $total,
        'page' => $page,
        'limit' => $limit,
        'pages' => ceil($total / $limit)
    ];
}

// Flash Messages
function setFlashMessage($type, $message) {
    $_SESSION['flash'][$type] = $message;
}

function getFlashMessage($type) {
    if (isset($_SESSION['flash'][$type])) {
        $message = $_SESSION['flash'][$type];
        unset($_SESSION['flash'][$type]);
        return $message;
    }
    return null;
}

// Error Handler
function handleError($errno, $errstr, $errfile, $errline) {
    if (DEBUG_MODE) {
        error_log("Error: $errstr in $errfile on line $errline");
    }
    return true;
}

// Exception Handler
function handleException($exception) {
    if (DEBUG_MODE) {
        error_log("Exception: " . $exception->getMessage() . " in " . $exception->getFile() . " on line " . $exception->getLine());
    } else {
        error_log("Exception occurred");
    }
    
    // Show user-friendly error page
    http_response_code(500);
    include __DIR__ . '/../public/error.php';
    exit();
}

// Set error and exception handlers
set_error_handler('handleError');
set_exception_handler('handleException');
?>

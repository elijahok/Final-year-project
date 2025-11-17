<?php
// Include configuration and functions
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect(BASE_URL . '/public/login.php');
    exit;
}

// Get user role
$currentUser = getCurrentUser();
$role = $currentUser['role'];

// Check if user is transporter
if ($role !== 'transporter') {
    http_response_code(403);
    echo 'Access denied';
    exit;
}

// Get transporter profile
$transporterProfile = getTransporterProfile($currentUser['id']);
if (!$transporterProfile) {
    http_response_code(404);
    echo 'Transporter profile not found';
    exit;
}

// Initialize database
$db = Database::getInstance();

// Get filters
$rating = (int)($_GET['rating'] ?? 0);

// Build query conditions
$whereConditions = ["tr.transporter_id = ?"];
$params = [$transporterProfile['id']];

if ($rating > 0 && $rating <= 5) {
    $whereConditions[] = "tr.overall_rating = ?";
    $params[] = $rating;
}

$whereClause = "WHERE " . implode(' AND ', $whereConditions);

// Get ratings
$ratings = $db->fetchAll("
    SELECT tr.*, 
           t_req.fee,
           t_req.quantity,
           t_req.weight,
           t_req.pickup_location,
           t_req.delivery_location,
           t_req.pickup_date,
           t_req.pickup_time,
           pc.name as produce_category,
           fp.farm_name,
           u_f.full_name as farmer_name,
           u_f.phone as farmer_phone,
           fp.farm_location
    FROM transporter_ratings tr
    LEFT JOIN transport_requests t_req ON tr.transport_request_id = t_req.id
    LEFT JOIN produce_categories pc ON t_req.produce_category_id = pc.id
    LEFT JOIN farmer_profiles fp ON tr.farmer_id = fp.id
    LEFT JOIN users u_f ON fp.user_id = u_f.id
    {$whereClause}
    ORDER BY tr.created_at DESC
", $params);

// Set headers for download
$filename = "transporter_ratings_" . date('Y-m-d') . ".csv";

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: 0');

// Open output stream
$output = fopen('php://output', 'w');

// CSV headers
$headers = [
    'Rating ID',
    'Transport Request ID',
    'Farmer Name',
    'Farm Name',
    'Farm Location',
    'Farmer Phone',
    'Produce Category',
    'Quantity',
    'Weight (kg)',
    'Pickup Location',
    'Delivery Location',
    'Pickup Date',
    'Pickup Time',
    'Fee (KES)',
    'Overall Rating',
    'Punctuality Rating',
    'Professionalism Rating',
    'Vehicle Condition Rating',
    'Communication Rating',
    'Review',
    'Rated At'
];

fputcsv($output, $headers);

// CSV data
foreach ($ratings as $ratingItem) {
    $row = [
        $ratingItem['id'],
        $ratingItem['transport_request_id'],
        $ratingItem['farmer_name'] ?? 'N/A',
        $ratingItem['farm_name'] ?? 'N/A',
        $ratingItem['farm_location'] ?? 'N/A',
        $ratingItem['farmer_phone'] ?? 'N/A',
        $ratingItem['produce_category'] ?? 'General',
        $ratingItem['quantity'],
        $ratingItem['weight'],
        str_replace(["\r\n", "\n", "\r"], " ", $ratingItem['pickup_location']),
        str_replace(["\r\n", "\n", "\r"], " ", $ratingItem['delivery_location']),
        $ratingItem['pickup_date'],
        $ratingItem['pickup_time'],
        $ratingItem['fee'],
        $ratingItem['overall_rating'],
        $ratingItem['punctuality_rating'],
        $ratingItem['professionalism_rating'],
        $ratingItem['vehicle_condition_rating'],
        $ratingItem['communication_rating'],
        str_replace(["\r\n", "\n", "\r"], " ", $ratingItem['review'] ?? ''),
        $ratingItem['created_at']
    ];
    
    fputcsv($output, $row);
}

// Close output stream
fclose($output);

// Log export activity
logActivity(
    $currentUser['id'],
    'export_ratings',
    'transporter_rating',
    0,
    "Transporter exported ratings (rating filter: {$rating})",
    $_SERVER['REMOTE_ADDR']
);

exit;
?>

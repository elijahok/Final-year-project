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

// Initialize database
$db = Database::getInstance();

// Get filters
$status = sanitizeInput($_GET['status'] ?? 'all');
$format = sanitizeInput($_GET['format'] ?? 'csv');

// Build query conditions based on user role
$whereConditions = [];
$params = [];

switch ($role) {
    case 'farmer':
        $farmerProfile = getFarmerProfile($currentUser['id']);
        if ($farmerProfile) {
            $whereConditions[] = "tr.farmer_id = ?";
            $params[] = $farmerProfile['id'];
        }
        break;
        
    case 'transporter':
        $transporterProfile = getTransporterProfile($currentUser['id']);
        if ($transporterProfile) {
            $whereConditions[] = "tr.transporter_id = ?";
            $params[] = $transporterProfile['id'];
        }
        break;
        
    case 'admin':
        // Admin can see all requests
        break;
        
    default:
        // Vendors and others can't see transport requests
        http_response_code(403);
        echo 'Access denied';
        exit;
}

if ($status !== 'all') {
    $whereConditions[] = "tr.status = ?";
    $params[] = $status;
}

$whereClause = !empty($whereConditions) ? "WHERE " . implode(' AND ', $whereConditions) : '';

// Get transport requests
$requests = $db->fetchAll("
    SELECT tr.*,
           fp.farm_name,
           u_f.full_name as farmer_name,
           u_f.phone as farmer_phone,
           u_t.full_name as transporter_name,
           u_t.phone as transporter_phone,
           tp.vehicle_type as transporter_vehicle,
           tp.vehicle_license,
           pc.name as produce_category
    FROM transport_requests tr
    LEFT JOIN farmer_profiles fp ON tr.farmer_id = fp.id
    LEFT JOIN users u_f ON fp.user_id = u_f.id
    LEFT JOIN transporter_profiles tp ON tr.transporter_id = tp.id
    LEFT JOIN users u_t ON tp.user_id = u_t.id
    LEFT JOIN produce_categories pc ON tr.produce_category_id = pc.id
    {$whereClause}
    ORDER BY tr.created_at DESC
", $params);

// Set headers for download
$filename = "transport_requests_" . date('Y-m-d') . ".csv";

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: 0');

// Open output stream
$output = fopen('php://output', 'w');

// CSV headers
$headers = [
    'Request ID',
    'Farmer',
    'Farm Name',
    'Farmer Phone',
    'Transporter',
    'Transporter Phone',
    'Vehicle Type',
    'Vehicle License',
    'Produce Category',
    'Quantity',
    'Weight (kg)',
    'Pickup Location',
    'Delivery Location',
    'Pickup Date',
    'Pickup Time',
    'Vehicle Type Required',
    'Urgency',
    'Fee (KES)',
    'Status',
    'Special Instructions',
    'Preferred Transporter',
    'Created At',
    'Accepted At',
    'Picked Up At',
    'Completed At',
    'Cancelled At',
    'Cancel Reason',
    'Rating',
    'Review'
];

fputcsv($output, $headers);

// CSV data
foreach ($requests as $request) {
    $row = [
        $request['id'],
        $request['farmer_name'] ?? 'N/A',
        $request['farm_name'] ?? 'N/A',
        $request['farmer_phone'] ?? 'N/A',
        $request['transporter_name'] ?? 'N/A',
        $request['transporter_phone'] ?? 'N/A',
        $request['transporter_vehicle'] ?? 'N/A',
        $request['vehicle_license'] ?? 'N/A',
        $request['produce_category'] ?? 'General',
        $request['quantity'],
        $request['weight'],
        str_replace(["\r\n", "\n", "\r"], " ", $request['pickup_location']),
        str_replace(["\r\n", "\n", "\r"], " ", $request['delivery_location']),
        $request['pickup_date'],
        $request['pickup_time'],
        ucfirst(str_replace('_', ' ', $request['vehicle_type'] ?? '')),
        ucfirst($request['urgency'] ?? ''),
        $request['fee'],
        ucfirst(str_replace('_', ' ', $request['status'])),
        str_replace(["\r\n", "\n", "\r"], " ", $request['special_instructions'] ?? ''),
        $request['preferred_transporter'] ?? 'None',
        $request['created_at'],
        $request['accepted_at'],
        $request['picked_up_at'],
        $request['completed_at'],
        $request['cancelled_at'],
        str_replace(["\r\n", "\n", "\r"], " ", $request['cancel_reason'] ?? ''),
        $request['rating'] ?? '',
        str_replace(["\r\n", "\n", "\r"], " ", $request['review'] ?? '')
    ];
    
    fputcsv($output, $row);
}

// Close output stream
fclose($output);

// Log export activity
logActivity(
    $currentUser['id'],
    'export_transport_requests',
    'transport_request',
    0,
    "User exported transport requests (status: {$status}, format: {$format})",
    $_SERVER['REMOTE_ADDR']
);

exit;
?>

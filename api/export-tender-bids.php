<?php
// Include configuration and functions
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: ' . BASE_URL . '/public/login.php');
    exit;
}

// Get tender ID
$tenderId = (int)($_GET['tender_id'] ?? 0);

if (!$tenderId) {
    $_SESSION['flash_error'] = 'Invalid tender ID';
    header('Location: ' . BASE_URL . '/public/index.php');
    exit;
}

// Initialize database
$db = Database::getInstance();

// Get tender details
$tender = $db->fetch("
    SELECT t.*, pc.name as category_name, u.full_name as created_by_name
    FROM tenders t
    LEFT JOIN produce_categories pc ON t.produce_category_id = pc.id
    LEFT JOIN users u ON t.created_by = u.id
    WHERE t.id = ?
", [$tenderId]);

if (!$tender) {
    $_SESSION['flash_error'] = 'Tender not found';
    header('Location: ' . BASE_URL . '/public/index.php');
    exit;
}

// Check user permissions based on role
$currentUser = getCurrentUser();
$userRole = $currentUser['role'];

// Get bids based on user role
$bids = [];
switch ($userRole) {
    case 'admin':
    case 'farmer':
        // Can see all bids for this tender
        $bids = $db->fetchAll("
            SELECT b.*, 
                   vp.company_name,
                   vp.contact_person,
                   vp.phone,
                   vp.email,
                   u.full_name as vendor_name,
                   bs.total_score,
                   bs.price_score,
                   bs.delivery_score,
                   bs.vendor_rating_score,
                   bs.experience_score,
                   bs.proposal_score
            FROM bids b
            LEFT JOIN vendor_profiles vp ON b.vendor_id = vp.id
            LEFT JOIN users u ON vp.user_id = u.id
            LEFT JOIN bid_scores bs ON b.id = bs.bid_id
            WHERE b.tender_id = ?
            ORDER BY bs.total_score DESC, b.created_at DESC
        ", [$tenderId]);
        break;
        
    case 'vendor':
        // Can only see their own bids
        $vendorProfile = getVendorProfile($currentUser['id']);
        if ($vendorProfile) {
            $bids = $db->fetchAll("
                SELECT b.*, 
                       vp.company_name,
                       vp.contact_person,
                       vp.phone,
                       vp.email,
                       u.full_name as vendor_name,
                       bs.total_score,
                       bs.price_score,
                       bs.delivery_score,
                       bs.vendor_rating_score,
                       bs.experience_score,
                       bs.proposal_score
                FROM bids b
                LEFT JOIN vendor_profiles vp ON b.vendor_id = vp.id
                LEFT JOIN users u ON vp.user_id = u.id
                LEFT JOIN bid_scores bs ON b.id = bs.bid_id
                WHERE b.tender_id = ? AND b.vendor_id = ?
                ORDER BY bs.total_score DESC, b.created_at DESC
            ", [$tenderId, $vendorProfile['id']]);
        }
        break;
        
    default:
        $_SESSION['flash_error'] = 'Access denied';
        header('Location: ' . BASE_URL . '/public/index.php');
        exit;
}

// Set headers for CSV download
$filename = "tender_bids_{$tenderId}_export_" . date('Y-m-d_H-i-s') . ".csv";
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: 0');

// Open output stream
$output = fopen('php://output', 'w');

// Add UTF-8 BOM for proper Excel display
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// CSV headers
$headers = [
    'Bid ID',
    'Company Name',
    'Contact Person',
    'Phone',
    'Email',
    'Total Price',
    'Unit Price',
    'Delivery Time',
    'Bid Status',
    'Total Score',
    'Price Score',
    'Delivery Score',
    'Vendor Rating Score',
    'Experience Score',
    'Proposal Score',
    'Submitted At',
    'Proposal Text'
];
fputcsv($output, $headers);

// Add tender information as first row
$tenderInfo = [
    'TENDER INFORMATION',
    'Title: ' . $tender['title'],
    'Category: ' . ($tender['category_name'] ?? 'N/A'),
    'Budget: ' . formatCurrency($tender['budget_range_min']) . ' - ' . formatCurrency($tender['budget_range_max']),
    'Quantity: ' . $tender['quantity'] . ' ' . $tender['unit'],
    'Deadline: ' . formatDate($tender['deadline']),
    'Status: ' . ucfirst(str_replace('_', ' ', $tender['status'])),
    'Created By: ' . $tender['created_by_name'],
    '',
    '',
    '',
    '',
    '',
    '',
    '',
    '',
    ''
];
fputcsv($output, $tenderInfo);

// Add empty row
fputcsv($output, []);

// Add bid data
foreach ($bids as $bid) {
    $row = [
        $bid['id'],
        $bid['company_name'] ?? 'N/A',
        $bid['contact_person'] ?? 'N/A',
        $bid['phone'] ?? 'N/A',
        $bid['email'] ?? 'N/A',
        $bid['total_price'],
        $bid['unit_price'],
        $bid['delivery_time_days'] . ' days',
        ucfirst($bid['status']),
        $bid['total_score'] ? number_format($bid['total_score'], 1) : 'Not scored',
        $bid['price_score'] ? number_format($bid['price_score'], 1) : 'N/A',
        $bid['delivery_score'] ? number_format($bid['delivery_score'], 1) : 'N/A',
        $bid['vendor_rating_score'] ? number_format($bid['vendor_rating_score'], 1) : 'N/A',
        $bid['experience_score'] ? number_format($bid['experience_score'], 1) : 'N/A',
        $bid['proposal_score'] ? number_format($bid['proposal_score'], 1) : 'N/A',
        formatDate($bid['created_at']),
        strip_tags($bid['proposal_text'] ?? '')
    ];
    fputcsv($output, $row);
}

// Add summary statistics
fputcsv($output, []);
$summaryHeaders = ['SUMMARY STATISTICS'];
fputcsv($output, $summaryHeaders);

$summary = [
    'Total Bids',
    count($bids),
    '',
    '',
    '',
    '',
    '',
    '',
    '',
    '',
    '',
    '',
    '',
    '',
    '',
    '',
    ''
];
fputcsv($output, $summary);

if (!empty($bids)) {
    $priceStats = [
        'Price Statistics',
        '',
        '',
        '',
        '',
        '',
        '',
        '',
        '',
        '',
        '',
        '',
        '',
        '',
        '',
        '',
        ''
    ];
    fputcsv($output, $priceStats);
    
    $prices = array_column($bids, 'total_price');
    $priceDetails = [
        'Average Price',
        formatCurrency(array_sum($prices) / count($prices)),
        '',
        '',
        '',
        '',
        '',
        '',
        '',
        '',
        '',
        '',
        '',
        '',
        '',
        '',
        ''
    ];
    fputcsv($output, $priceDetails);
    
    $priceDetails = [
        'Min Price',
        formatCurrency(min($prices)),
        '',
        '',
        '',
        '',
        '',
        '',
        '',
        '',
        '',
        '',
        '',
        '',
        '',
        '',
        ''
    ];
    fputcsv($output, $priceDetails);
    
    $priceDetails = [
        'Max Price',
        formatCurrency(max($prices)),
        '',
        '',
        '',
        '',
        '',
        '',
        '',
        '',
        '',
        '',
        '',
        '',
        '',
        '',
        ''
    ];
    fputcsv($output, $priceDetails);
    
    // Score statistics
    $scores = array_filter(array_column($bids, 'total_score'));
    if (!empty($scores)) {
        $scoreStats = [
            'Score Statistics',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            ''
        ];
        fputcsv($output, $scoreStats);
        
        $scoreDetails = [
            'Average Score',
            number_format(array_sum($scores) / count($scores), 1) . '%',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            ''
        ];
        fputcsv($output, $scoreDetails);
        
        $scoreDetails = [
            'Highest Score',
            number_format(max($scores), 1) . '%',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            ''
        ];
        fputcsv($output, $scoreDetails);
    }
}

// Close output stream
fclose($output);

// Log activity
logActivity(
    $currentUser['id'],
    'export_bids',
    'tender',
    $tenderId,
    "Exported bids for tender '{$tender['title']}'",
    $_SERVER['REMOTE_ADDR']
);

// Exit
exit;
?>

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

// Get filter parameters
$dateFrom = $_GET['date_from'] ?? date('Y-m-01'); // Default to first day of current month
$dateTo = $_GET['date_to'] ?? date('Y-m-d'); // Default to today
$transactionType = $_GET['transaction_type'] ?? ''; // credit, debit, or empty for all

// Validate dates
if (!strtotime($dateFrom) || !strtotime($dateTo)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid date format']);
    exit();
}

// Build query
$whereConditions = ["wt.user_id = ?"];
$params = [$currentUser['id']];

if ($transactionType && in_array($transactionType, ['credit', 'debit'])) {
    $whereConditions[] = "wt.transaction_type = ?";
    $params[] = $transactionType;
}

$whereClause = "WHERE " . implode(' AND ', $whereConditions);

// Get transactions
$transactions = $db->fetchAll("
    SELECT wt.*,
           CASE 
               WHEN wt.transaction_type = 'credit' THEN '+'
               ELSE '-'
           END as sign,
           u.full_name as related_user_name,
           u.phone as related_user_phone
    FROM wallet_transactions wt
    LEFT JOIN users u ON wt.related_user_id = u.id
    {$whereClause}
    AND DATE(wt.created_at) BETWEEN ? AND ?
    ORDER BY wt.created_at DESC
", array_merge($params, [$dateFrom, $dateTo]));

// Set headers for CSV download
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="wallet_transactions_' . date('Y-m-d') . '.csv"');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: 0');

// Open output stream
$output = fopen('php://output', 'w');

// Add BOM for proper UTF-8 encoding in Excel
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// CSV headers
$headers = [
    'Transaction ID',
    'Date',
    'Time',
    'Description',
    'Type',
    'Amount (KES)',
    'Related User',
    'Related User Phone',
    'Reference ID'
];
fputcsv($output, $headers);

// Add transactions data
foreach ($transactions as $transaction) {
    $row = [
        $transaction['id'],
        date('Y-m-d', strtotime($transaction['created_at'])),
        date('H:i:s', strtotime($transaction['created_at'])),
        $transaction['description'],
        ucfirst($transaction['transaction_type']),
        $transaction['amount'],
        $transaction['related_user_name'] ?? '',
        $transaction['related_user_phone'] ?? '',
        $transaction['reference_id'] ?? ''
    ];
    fputcsv($output, $row);
}

// Add summary row
$totalCredits = array_sum(array_filter(array_column($transactions, 'amount'), function($amt, $key) use ($transactions) {
    return $transactions[$key]['transaction_type'] === 'credit';
}, ARRAY_FILTER_USE_BOTH));

$totalDebits = array_sum(array_filter(array_column($transactions, 'amount'), function($amt, $key) use ($transactions) {
    return $transactions[$key]['transaction_type'] === 'debit';
}, ARRAY_FILTER_USE_BOTH));

$netBalance = $totalCredits - $totalDebits;

// Empty row
fputcsv($output, []);

// Summary headers
fputcsv($output, ['SUMMARY']);
fputcsv($output, ['Total Credits', $totalCredits]);
fputcsv($output, ['Total Debits', $totalDebits]);
fputcsv($output, ['Net Balance', $netBalance]);
fputcsv($output, ['Number of Transactions', count($transactions)]);
fputcsv($output, ['Export Date', date('Y-m-d H:i:s')]);
fputcsv($output, ['Exported By', $currentUser['full_name']]);

// Close output stream
fclose($output);

// Log export activity
logActivity($currentUser['id'], 'export_wallet_transactions', "Exported wallet transactions from {$dateFrom} to {$dateTo}");

// Exit script
exit();
?>

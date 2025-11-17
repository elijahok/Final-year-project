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
    case 'initiate_payment':
        if ($method === 'POST') {
            $amount = floatval($_POST['amount'] ?? 0);
            $phoneNumber = trim($_POST['phone_number'] ?? '');
            $provider = $_POST['provider'] ?? ''; // mpesa, airtel
            $paymentType = $_POST['payment_type'] ?? ''; // transport_payment, bid_payment, wallet_topup
            $referenceId = intval($_POST['reference_id'] ?? 0);
            
            // Validate input
            if ($amount <= 0) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid amount']);
                exit();
            }
            
            if (empty($phoneNumber) || !validatePhoneNumber($phoneNumber)) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid phone number']);
                exit();
            }
            
            if (!in_array($provider, ['mpesa', 'airtel'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid payment provider']);
                exit();
            }
            
            if (!in_array($paymentType, ['transport_payment', 'bid_payment', 'wallet_topup'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid payment type']);
                exit();
            }
            
            // Validate CSRF token
            if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
                http_response_code(403);
                echo json_encode(['error' => 'Invalid CSRF token']);
                exit();
            }
            
            // Initiate payment based on provider
            $paymentResult = false;
            if ($provider === 'mpesa') {
                $paymentResult = initiateMpesaPayment($amount, $phoneNumber, $paymentType, $referenceId);
            } elseif ($provider === 'airtel') {
                $paymentResult = initiateAirtelPayment($amount, $phoneNumber, $paymentType, $referenceId);
            }
            
            if ($paymentResult) {
                // Create transaction record
                $transactionId = $db->insert('mobile_money_transactions', [
                    'user_id' => $currentUser['id'],
                    'amount' => $amount,
                    'phone_number' => $phoneNumber,
                    'provider' => $provider,
                    'payment_type' => $paymentType,
                    'reference_id' => $referenceId,
                    'transaction_id' => $paymentResult['transaction_id'],
                    'status' => 'pending',
                    'created_at' => date('Y-m-d H:i:s')
                ]);
                
                // Log payment initiation
                logActivity($currentUser['id'], 'payment_initiated', "Payment of {$amount} initiated via {$provider}");
                
                echo json_encode([
                    'success' => true,
                    'transaction_id' => $transactionId,
                    'provider_transaction_id' => $paymentResult['transaction_id'],
                    'message' => $paymentResult['message'] ?? 'Payment initiated successfully'
                ]);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to initiate payment']);
            }
        } else {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
        }
        break;
        
    case 'check_status':
        if ($method === 'GET') {
            $transactionId = intval($_GET['transaction_id'] ?? 0);
            
            if ($transactionId <= 0) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid transaction ID']);
                exit();
            }
            
            // Get transaction details
            $transaction = $db->fetch("
                SELECT * FROM mobile_money_transactions
                WHERE id = ? AND user_id = ?
            ", [$transactionId, $currentUser['id']]);
            
            if (!$transaction) {
                http_response_code(404);
                echo json_encode(['error' => 'Transaction not found']);
                exit();
            }
            
            // Check payment status with provider
            $statusResult = false;
            if ($transaction['provider'] === 'mpesa') {
                $statusResult = checkMpesaStatus($transaction['transaction_id']);
            } elseif ($transaction['provider'] === 'airtel') {
                $statusResult = checkAirtelStatus($transaction['transaction_id']);
            }
            
            if ($statusResult) {
                // Update transaction status
                $newStatus = $statusResult['status'];
                if ($newStatus !== $transaction['status']) {
                    $db->update('mobile_money_transactions', [
                        'status' => $newStatus,
                        'provider_response' => json_encode($statusResult),
                        'updated_at' => date('Y-m-d H:i:s')
                    ], 'id = ?', [$transactionId]);
                    
                    // If payment is successful, process the payment
                    if ($newStatus === 'completed') {
                        processSuccessfulPayment($transaction);
                    }
                    
                    // Log status change
                    logActivity($currentUser['id'], 'payment_status_updated', "Payment status updated to {$newStatus}");
                }
                
                echo json_encode([
                    'success' => true,
                    'status' => $newStatus,
                    'message' => $statusResult['message'] ?? 'Status checked successfully'
                ]);
            } else {
                echo json_encode([
                    'success' => true,
                    'status' => $transaction['status'],
                    'message' => 'Unable to check status with provider'
                ]);
            }
        } else {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
        }
        break;
        
    case 'wallet_balance':
        if ($method === 'GET') {
            $wallet = $db->fetch("
                SELECT * FROM wallets
                WHERE user_id = ?
            ", [$currentUser['id']]);
            
            if (!$wallet) {
                // Create wallet if it doesn't exist
                $walletId = $db->insert('wallets', [
                    'user_id' => $currentUser['id'],
                    'balance' => 0,
                    'created_at' => date('Y-m-d H:i:s')
                ]);
                
                $wallet = [
                    'id' => $walletId,
                    'balance' => 0
                ];
            }
            
            echo json_encode([
                'success' => true,
                'balance' => floatval($wallet['balance']),
                'currency' => 'KES'
            ]);
        } else {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
        }
        break;
        
    case 'wallet_transactions':
        if ($method === 'GET') {
            $limit = intval($_GET['limit'] ?? 20);
            $offset = intval($_GET['offset'] ?? 0);
            
            $transactions = $db->fetchAll("
                SELECT wt.*, 
                       CASE 
                           WHEN wt.transaction_type = 'credit' THEN '+'
                           ELSE '-'
                       END as sign,
                       u.full_name as related_user_name
                FROM wallet_transactions wt
                LEFT JOIN users u ON wt.related_user_id = u.id
                WHERE wt.user_id = ?
                ORDER BY wt.created_at DESC
                LIMIT ? OFFSET ?
            ", [$currentUser['id'], $limit, $offset]);
            
            echo json_encode([
                'success' => true,
                'transactions' => $transactions
            ]);
        } else {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
        }
        break;
        
    case 'topup_wallet':
        if ($method === 'POST') {
            $amount = floatval($_POST['amount'] ?? 0);
            $phoneNumber = trim($_POST['phone_number'] ?? '');
            $provider = $_POST['provider'] ?? '';
            
            // Validate input
            if ($amount <= 0) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid amount']);
                exit();
            }
            
            if (empty($phoneNumber) || !validatePhoneNumber($phoneNumber)) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid phone number']);
                exit();
            }
            
            if (!in_array($provider, ['mpesa', 'airtel'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid payment provider']);
                exit();
            }
            
            // Validate CSRF token
            if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
                http_response_code(403);
                echo json_encode(['error' => 'Invalid CSRF token']);
                exit();
            }
            
            // Initiate payment
            $paymentResult = false;
            if ($provider === 'mpesa') {
                $paymentResult = initiateMpesaPayment($amount, $phoneNumber, 'wallet_topup', 0);
            } elseif ($provider === 'airtel') {
                $paymentResult = initiateAirtelPayment($amount, $phoneNumber, 'wallet_topup', 0);
            }
            
            if ($paymentResult) {
                // Create transaction record
                $transactionId = $db->insert('mobile_money_transactions', [
                    'user_id' => $currentUser['id'],
                    'amount' => $amount,
                    'phone_number' => $phoneNumber,
                    'provider' => $provider,
                    'payment_type' => 'wallet_topup',
                    'reference_id' => 0,
                    'transaction_id' => $paymentResult['transaction_id'],
                    'status' => 'pending',
                    'created_at' => date('Y-m-d H:i:s')
                ]);
                
                // Log wallet topup initiation
                logActivity($currentUser['id'], 'wallet_topup_initiated', "Wallet topup of {$amount} initiated via {$provider}");
                
                echo json_encode([
                    'success' => true,
                    'transaction_id' => $transactionId,
                    'provider_transaction_id' => $paymentResult['transaction_id'],
                    'message' => $paymentResult['message'] ?? 'Wallet topup initiated successfully'
                ]);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to initiate wallet topup']);
            }
        } else {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
        }
        break;
        
    case 'withdraw_wallet':
        if ($method === 'POST') {
            $amount = floatval($_POST['amount'] ?? 0);
            $phoneNumber = trim($_POST['phone_number'] ?? '');
            $provider = $_POST['provider'] ?? '';
            
            // Validate input
            if ($amount <= 0) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid amount']);
                exit();
            }
            
            if (empty($phoneNumber) || !validatePhoneNumber($phoneNumber)) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid phone number']);
                exit();
            }
            
            if (!in_array($provider, ['mpesa', 'airtel'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid payment provider']);
                exit();
            }
            
            // Validate CSRF token
            if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
                http_response_code(403);
                echo json_encode(['error' => 'Invalid CSRF token']);
                exit();
            }
            
            // Check wallet balance
            $wallet = $db->fetch("SELECT balance FROM wallets WHERE user_id = ?", [$currentUser['id']]);
            if (!$wallet || $wallet['balance'] < $amount) {
                http_response_code(400);
                echo json_encode(['error' => 'Insufficient wallet balance']);
                exit();
            }
            
            // Process withdrawal
            try {
                $db->beginTransaction();
                
                // Deduct from wallet
                $db->update('wallets', [
                    'balance' => $wallet['balance'] - $amount,
                    'updated_at' => date('Y-m-d H:i:s')
                ], 'user_id = ?', [$currentUser['id']]);
                
                // Create wallet transaction
                $db->insert('wallet_transactions', [
                    'user_id' => $currentUser['id'],
                    'amount' => $amount,
                    'transaction_type' => 'debit',
                    'description' => "Withdrawal to {$phoneNumber} via {$provider}",
                    'reference_id' => 0,
                    'created_at' => date('Y-m-d H:i:s')
                ]);
                
                // Initiate payout (in production, this would integrate with the provider's payout API)
                $payoutResult = initiatePayout($amount, $phoneNumber, $provider);
                
                if ($payoutResult) {
                    $db->commit();
                    
                    // Log withdrawal
                    logActivity($currentUser['id'], 'wallet_withdrawal', "Withdrawal of {$amount} to {$phoneNumber} via {$provider}");
                    
                    echo json_encode([
                        'success' => true,
                        'message' => 'Withdrawal processed successfully',
                        'payout_id' => $payoutResult['payout_id']
                    ]);
                } else {
                    $db->rollback();
                    http_response_code(500);
                    echo json_encode(['error' => 'Failed to process withdrawal']);
                }
            } catch (Exception $e) {
                $db->rollback();
                http_response_code(500);
                echo json_encode(['error' => 'Transaction failed']);
            }
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

// Payment provider functions (placeholders - in production, integrate with actual APIs)
function initiateMpesaPayment($amount, $phoneNumber, $paymentType, $referenceId) {
    // This is a placeholder implementation
    // In production, integrate with M-Pesa API
    
    $transactionId = 'MPESA_' . time() . '_' . rand(1000, 9999);
    
    // Simulate API call
    return [
        'transaction_id' => $transactionId,
        'message' => 'Please enter your M-Pesa PIN to complete the transaction'
    ];
}

function initiateAirtelPayment($amount, $phoneNumber, $paymentType, $referenceId) {
    // This is a placeholder implementation
    // In production, integrate with Airtel Money API
    
    $transactionId = 'AIRTEL_' . time() . '_' . rand(1000, 9999);
    
    // Simulate API call
    return [
        'transaction_id' => $transactionId,
        'message' => 'Please confirm the transaction on your Airtel Money account'
    ];
}

function checkMpesaStatus($transactionId) {
    // This is a placeholder implementation
    // In production, check status with M-Pesa API
    
    // Simulate random status for demo
    $statuses = ['pending', 'completed', 'failed'];
    $status = $statuses[array_rand($statuses)];
    
    return [
        'status' => $status,
        'message' => "Transaction status: {$status}"
    ];
}

function checkAirtelStatus($transactionId) {
    // This is a placeholder implementation
    // In production, check status with Airtel Money API
    
    // Simulate random status for demo
    $statuses = ['pending', 'completed', 'failed'];
    $status = $statuses[array_rand($statuses)];
    
    return [
        'status' => $status,
        'message' => "Transaction status: {$status}"
    ];
}

function initiatePayout($amount, $phoneNumber, $provider) {
    // This is a placeholder implementation
    // In production, integrate with provider's payout API
    
    $payoutId = 'PAYOUT_' . time() . '_' . rand(1000, 9999);
    
    return [
        'payout_id' => $payoutId,
        'message' => 'Payout initiated successfully'
    ];
}

function processSuccessfulPayment($transaction) {
    global $db;
    
    try {
        $db->beginTransaction();
        
        // Get or create wallet
        $wallet = $db->fetch("SELECT * FROM wallets WHERE user_id = ?", [$transaction['user_id']]);
        if (!$wallet) {
            $walletId = $db->insert('wallets', [
                'user_id' => $transaction['user_id'],
                'balance' => 0,
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            $wallet = [
                'id' => $walletId,
                'balance' => 0
            ];
        }
        
        // Add to wallet balance
        $newBalance = $wallet['balance'] + $transaction['amount'];
        $db->update('wallets', [
            'balance' => $newBalance,
            'updated_at' => date('Y-m-d H:i:s')
        ], 'id = ?', [$wallet['id']]);
        
        // Create wallet transaction
        $db->insert('wallet_transactions', [
            'user_id' => $transaction['user_id'],
            'amount' => $transaction['amount'],
            'transaction_type' => 'credit',
            'description' => "Payment via {$transaction['provider']}",
            'reference_id' => $transaction['id'],
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        // Process the original payment type
        if ($transaction['payment_type'] === 'transport_payment') {
            // Update transport request payment status
            $db->update('transport_requests', [
                'payment_status' => 'paid',
                'updated_at' => date('Y-m-d H:i:s')
            ], 'id = ?', [$transaction['reference_id']]);
        } elseif ($transaction['payment_type'] === 'bid_payment') {
            // Update bid payment status
            $db->update('bids', [
                'payment_status' => 'paid',
                'updated_at' => date('Y-m-d H:i:s')
            ], 'id = ?', [$transaction['reference_id']]);
        }
        
        $db->commit();
        
        // Send notification
        addNotification($transaction['user_id'], 'Payment Received', 
                        "Payment of {$transaction['amount']} has been received and added to your wallet", 'success');
        
    } catch (Exception $e) {
        $db->rollback();
        throw $e;
    }
}

function validatePhoneNumber($phoneNumber) {
    // Remove any non-digit characters
    $phone = preg_replace('/[^0-9]/', '', $phoneNumber);
    
    // Check if it's a valid Kenyan phone number
    if (strlen($phone) === 12 && substr($phone, 0, 3) === '254') {
        return true;
    } elseif (strlen($phone) === 10 && substr($phone, 0, 1) === '0') {
        return true;
    } elseif (strlen($phone) === 9 && substr($phone, 0, 1) === '7') {
        return true;
    }
    
    return false;
}
?>

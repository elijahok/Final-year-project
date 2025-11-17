<?php
// Include configuration and functions
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

// Set headers for JSON response
header('Content-Type: application/json');

// Initialize database
$db = Database::getInstance();

// Check if request is AJAX
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || $_SERVER['HTTP_X_REQUESTED_WITH'] !== 'XMLHttpRequest') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit;
}

// Validate CSRF token for POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!isset($input['csrf_token']) || !validateCSRFToken($input['csrf_token'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
        exit;
    }
}

// Handle different actions
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'calculate_bid_score':
        calculateBidScore();
        break;
    
    case 'evaluate_bids':
        evaluateBids();
        break;
    
    case 'get_scoring_criteria':
        getScoringCriteria();
        break;
    
    case 'update_bid_score':
        updateBidScore();
        break;
    
    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
        break;
}

/**
 * Calculate bid score based on various criteria
 */
function calculateBidScore() {
    global $db;
    
    $bidId = (int)($_GET['bid_id'] ?? 0);
    
    if (!$bidId) {
        echo json_encode(['success' => false, 'error' => 'Bid ID is required']);
        return;
    }
    
    // Get bid details
    $bid = $db->fetch("
        SELECT b.*, t.budget_min, t.budget_max, t.quantity, t.unit,
               vp.rating as vendor_rating, vp.experience_years
        FROM bids b
        JOIN tenders t ON b.tender_id = t.id
        LEFT JOIN vendor_profiles vp ON b.vendor_id = vp.user_id
        WHERE b.id = ?
    ", [$bidId]);
    
    if (!$bid) {
        echo json_encode(['success' => false, 'error' => 'Bid not found']);
        return;
    }
    
    // Calculate individual scores
    $scores = [];
    
    // Price competitiveness (40%)
    if ($bid['amount'] >= $bid['budget_min'] && $bid['amount'] <= $bid['budget_max']) {
        $priceRange = $bid['budget_max'] - $bid['budget_min'];
        $pricePosition = ($bid['budget_max'] - $bid['amount']) / $priceRange;
        $scores['price_competitiveness'] = round($pricePosition * 40, 2);
    } else {
        $scores['price_competitiveness'] = 0;
    }
    
    // Delivery timeline (25%)
    $optimalDelivery = 7; // 7 days is optimal
    if ($bid['delivery_timeline'] > 0) {
        $deliveryScore = max(0, ($optimalDelivery - min($bid['delivery_timeline'], 30)) / $optimalDelivery * 25);
        $scores['delivery_timeline'] = round($deliveryScore, 2);
    } else {
        $scores['delivery_timeline'] = 0;
    }
    
    // Vendor rating (20%)
    $vendorRating = $bid['vendor_rating'] ?? 0;
    $scores['vendor_rating'] = round(($vendorRating / 5) * 20, 2);
    
    // Experience (10%)
    $experienceYears = $bid['experience_years'] ?? 0;
    $experienceScore = min($experienceYears / 10 * 10, 10); // Max 10 points, capped at 10 years
    $scores['experience'] = round($experienceScore, 2);
    
    // Proposal quality (5%)
    $proposalLength = strlen($bid['proposal'] ?? '');
    if ($proposalLength > 100) {
        $proposalScore = min($proposalLength / 1000 * 5, 5); // Max 5 points
    } else {
        $proposalScore = 0;
    }
    $scores['proposal_quality'] = round($proposalScore, 2);
    
    // Total score
    $totalScore = array_sum($scores);
    
    // Update bid score in database
    $db->update('bids', [
        'bid_score' => $totalScore,
        'score_breakdown' => json_encode($scores),
        'score_updated_at' => date('Y-m-d H:i:s')
    ], 'id = ?', [$bidId]);
    
    // Log scoring activity
    logActivity(getCurrentUser()['id'], 'calculate_bid_score', "Calculated score for bid #{$bidId}: {$totalScore}", [
        'bid_id' => $bidId,
        'total_score' => $totalScore,
        'breakdown' => $scores
    ]);
    
    echo json_encode([
        'success' => true,
        'total_score' => $totalScore,
        'breakdown' => $scores,
        'grade' => getScoreGrade($totalScore)
    ]);
}

/**
 * Evaluate all bids for a tender
 */
function evaluateBids() {
    global $db;
    
    $tenderId = (int)($_GET['tender_id'] ?? 0);
    
    if (!$tenderId) {
        echo json_encode(['success' => false, 'error' => 'Tender ID is required']);
        return;
    }
    
    // Get all bids for the tender
    $bids = $db->fetchAll("
        SELECT b.*, vp.company_name, vp.rating as vendor_rating
        FROM bids b
        LEFT JOIN vendor_profiles vp ON b.vendor_id = vp.user_id
        WHERE b.tender_id = ? AND b.status = 'pending'
        ORDER BY b.bid_score DESC
    ", [$tenderId]);
    
    if (empty($bids)) {
        echo json_encode(['success' => false, 'error' => 'No pending bids found for this tender']);
        return;
    }
    
    $evaluation = [];
    $rank = 1;
    
    foreach ($bids as $bid) {
        // Calculate score if not already calculated
        if (!$bid['bid_score']) {
            // Calculate score (reuse logic from calculateBidScore)
            $scores = calculateBidScoreComponents($bid);
            $totalScore = array_sum($scores);
            
            // Update bid score
            $db->update('bids', [
                'bid_score' => $totalScore,
                'score_breakdown' => json_encode($scores),
                'score_updated_at' => date('Y-m-d H:i:s')
            ], 'id = ?', [$bid['id']]);
            
            $bid['bid_score'] = $totalScore;
            $bid['score_breakdown'] = $scores;
        } else {
            $bid['score_breakdown'] = json_decode($bid['score_breakdown'] ?? '{}', true);
        }
        
        $evaluation[] = [
            'rank' => $rank++,
            'bid_id' => $bid['id'],
            'vendor_name' => $bid['company_name'] ?? 'Unknown',
            'amount' => $bid['amount'],
            'total_score' => $bid['bid_score'],
            'breakdown' => $bid['score_breakdown'],
            'grade' => getScoreGrade($bid['bid_score']),
            'delivery_timeline' => $bid['delivery_timeline'],
            'vendor_rating' => $bid['vendor_rating']
        ];
    }
    
    // Log evaluation activity
    logActivity(getCurrentUser()['id'], 'evaluate_bids', "Evaluated bids for tender #{$tenderId}", [
        'tender_id' => $tenderId,
        'bid_count' => count($bids)
    ]);
    
    echo json_encode([
        'success' => true,
        'evaluation' => $evaluation,
        'tender_id' => $tenderId
    ]);
}

/**
 * Get scoring criteria configuration
 */
function getScoringCriteria() {
    $criteria = [
        'price_competitiveness' => [
            'name' => 'Price Competitiveness',
            'weight' => 40,
            'description' => 'How competitive the bid price is compared to the budget range',
            'calculation' => 'Based on position within budget range'
        ],
        'delivery_timeline' => [
            'name' => 'Delivery Timeline',
            'weight' => 25,
            'description' => 'How quickly the vendor can deliver the goods',
            'calculation' => 'Optimal delivery time is 7 days'
        ],
        'vendor_rating' => [
            'name' => 'Vendor Rating',
            'weight' => 20,
            'description' => 'Historical performance rating of the vendor',
            'calculation' => 'Based on vendor rating (1-5 stars)'
        ],
        'experience' => [
            'name' => 'Experience',
            'weight' => 10,
            'description' => 'Years of experience in the industry',
            'calculation' => 'Based on years of experience (max 10 years)'
        ],
        'proposal_quality' => [
            'name' => 'Proposal Quality',
            'weight' => 5,
            'description' => 'Quality and detail of the submitted proposal',
            'calculation' => 'Based on proposal length and content'
        ]
    ];
    
    echo json_encode([
        'success' => true,
        'criteria' => $criteria
    ]);
}

/**
 * Update bid score manually
 */
function updateBidScore() {
    global $db;
    
    $input = json_decode(file_get_contents('php://input'), true);
    $bidId = (int)($input['bid_id'] ?? 0);
    $manualScores = $input['scores'] ?? [];
    
    if (!$bidId || empty($manualScores)) {
        echo json_encode(['success' => false, 'error' => 'Bid ID and scores are required']);
        return;
    }
    
    // Validate scores
    $validCriteria = ['price_competitiveness', 'delivery_timeline', 'vendor_rating', 'experience', 'proposal_quality'];
    $totalWeight = 0;
    
    foreach ($manualScores as $criterion => $score) {
        if (!in_array($criterion, $validCriteria)) {
            echo json_encode(['success' => false, 'error' => "Invalid criterion: {$criterion}"]);
            return;
        }
        if ($score < 0 || $score > 40) { // Max weight is 40 for price
            echo json_encode(['success' => false, 'error' => "Invalid score for {$criterion}"]);
            return;
        }
        $totalWeight += $score;
    }
    
    // Update bid score
    $totalScore = array_sum($manualScores);
    $db->update('bids', [
        'bid_score' => $totalScore,
        'score_breakdown' => json_encode($manualScores),
        'score_updated_at' => date('Y-m-d H:i:s'),
        'manual_score_adjustment' => 1
    ], 'id = ?', [$bidId]);
    
    // Log manual adjustment
    logActivity(getCurrentUser()['id'], 'manual_score_adjustment', "Manually adjusted score for bid #{$bidId}: {$totalScore}", [
        'bid_id' => $bidId,
        'previous_score' => $db->fetch("SELECT bid_score FROM bids WHERE id = ?", [$bidId])['bid_score'],
        'new_score' => $totalScore,
        'breakdown' => $manualScores
    ]);
    
    echo json_encode([
        'success' => true,
        'total_score' => $totalScore,
        'breakdown' => $manualScores,
        'grade' => getScoreGrade($totalScore)
    ]);
}

/**
 * Helper function to calculate bid score components
 */
function calculateBidScoreComponents($bid) {
    $scores = [];
    
    // Price competitiveness (40%)
    if ($bid['amount'] >= $bid['budget_min'] && $bid['amount'] <= $bid['budget_max']) {
        $priceRange = $bid['budget_max'] - $bid['budget_min'];
        $pricePosition = ($bid['budget_max'] - $bid['amount']) / $priceRange;
        $scores['price_competitiveness'] = round($pricePosition * 40, 2);
    } else {
        $scores['price_competitiveness'] = 0;
    }
    
    // Delivery timeline (25%)
    $optimalDelivery = 7;
    if ($bid['delivery_timeline'] > 0) {
        $deliveryScore = max(0, ($optimalDelivery - min($bid['delivery_timeline'], 30)) / $optimalDelivery * 25);
        $scores['delivery_timeline'] = round($deliveryScore, 2);
    } else {
        $scores['delivery_timeline'] = 0;
    }
    
    // Vendor rating (20%)
    $vendorRating = $bid['vendor_rating'] ?? 0;
    $scores['vendor_rating'] = round(($vendorRating / 5) * 20, 2);
    
    // Experience (10%)
    $experienceYears = $bid['experience_years'] ?? 0;
    $experienceScore = min($experienceYears / 10 * 10, 10);
    $scores['experience'] = round($experienceScore, 2);
    
    // Proposal quality (5%)
    $proposalLength = strlen($bid['proposal'] ?? '');
    if ($proposalLength > 100) {
        $proposalScore = min($proposalLength / 1000 * 5, 5);
    } else {
        $proposalScore = 0;
    }
    $scores['proposal_quality'] = round($proposalScore, 2);
    
    return $scores;
}

/**
 * Helper function to get grade based on score
 */
function getScoreGrade($score) {
    if ($score >= 90) return 'A+';
    if ($score >= 80) return 'A';
    if ($score >= 70) return 'B+';
    if ($score >= 60) return 'B';
    if ($score >= 50) return 'C+';
    if ($score >= 40) return 'C';
    if ($score >= 30) return 'D';
    return 'F';
}
?>

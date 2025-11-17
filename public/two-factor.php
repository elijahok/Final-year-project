<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect(BASE_URL . '/public/login.php');
}

$currentUser = getCurrentUser();
$error = '';
$success = '';

// Handle 2FA setup or verification
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'setup') {
        // Generate secret for user
        $secret = generateTOTPSecret();
        
        // Save secret to user profile
        $db->update('users', [
            'two_factor_secret' => $secret,
            'two_factor_enabled' => 0
        ], 'id = ?', [$currentUser['id']]);
        
        // Generate QR code URL
        $qrUrl = generateTOTPQRCode($secret, $currentUser['email'], APP_NAME);
        
        $success = '2FA setup initiated. Please scan the QR code with your authenticator app.';
    } elseif ($action === 'verify') {
        $code = trim($_POST['code'] ?? '');
        
        if (empty($code)) {
            $error = 'Please enter the verification code.';
        } else {
            $user = $db->fetch("SELECT two_factor_secret FROM users WHERE id = ?", [$currentUser['id']]);
            
            if ($user && verifyTOTP($user['two_factor_secret'], $code)) {
                // Enable 2FA
                $db->update('users', [
                    'two_factor_enabled' => 1
                ], 'id = ?', [$currentUser['id']]);
                
                $success = 'Two-factor authentication has been enabled successfully!';
                
                // Log 2FA enable
                logActivity($currentUser['id'], '2fa_enabled', 'Two-factor authentication enabled');
                
                // Redirect to dashboard
                header('refresh:2;url=' . BASE_URL . '/public/index.php');
            } else {
                $error = 'Invalid verification code. Please try again.';
            }
        }
    } elseif ($action === 'disable') {
        $password = trim($_POST['password'] ?? '');
        
        if (empty($password)) {
            $error = 'Please enter your password to disable 2FA.';
        } else {
            // Verify password
            if (verifyPassword($password, $currentUser['password_hash'])) {
                // Disable 2FA
                $db->update('users', [
                    'two_factor_enabled' => 0,
                    'two_factor_secret' => null
                ], 'id = ?', [$currentUser['id']]);
                
                $success = 'Two-factor authentication has been disabled.';
                
                // Log 2FA disable
                logActivity($currentUser['id'], '2fa_disabled', 'Two-factor authentication disabled');
            } else {
                $error = 'Invalid password.';
            }
        }
    }
}

// Get current 2FA status
$twoFactorEnabled = $currentUser['two_factor_enabled'] ?? 0;
$twoFactorSecret = $currentUser['two_factor_secret'] ?? '';

$pageTitle = 'Two-Factor Authentication - ' . APP_NAME;
include __DIR__ . '/../includes/header.php';
?>

<div class="container py-5">
    <div class="row">
        <div class="col-lg-6 mx-auto">
            <div class="card shadow">
                <div class="card-body p-5">
                    <h2 class="h3 mb-4 text-primary">
                        <i class="fas fa-shield-alt me-2"></i>
                        Two-Factor Authentication
                    </h2>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <?= $error ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle me-2"></i>
                            <?= $success ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($twoFactorEnabled): ?>
                        <!-- 2FA Enabled Status -->
                        <div class="text-center mb-4">
                            <div class="mb-3">
                                <i class="fas fa-check-circle fa-4x text-success"></i>
                            </div>
                            <h4 class="text-success">2FA is Enabled</h4>
                            <p class="text-muted">Your account is protected with two-factor authentication.</p>
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Backup Codes:</strong> Make sure you have saved your backup codes in a safe place. You can use these to access your account if you lose access to your authenticator device.
                        </div>
                        
                        <!-- Disable 2FA Form -->
                        <form method="POST" action="">
                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                            <input type="hidden" name="action" value="disable">
                            
                            <div class="mb-3">
                                <label for="password" class="form-label">Enter Password to Disable 2FA</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                    <input type="password" class="form-control" id="password" name="password" required>
                                </div>
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" class="btn btn-danger">
                                    <i class="fas fa-times me-2"></i>
                                    Disable Two-Factor Authentication
                                </button>
                            </div>
                        </form>
                    <?php elseif ($twoFactorSecret): ?>
                        <!-- 2FA Setup - Verification -->
                        <div class="text-center mb-4">
                            <h4>Verify Your Authenticator App</h4>
                            <p class="text-muted">Enter the 6-digit code from your authenticator app to complete setup.</p>
                        </div>
                        
                        <form method="POST" action="">
                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                            <input type="hidden" name="action" value="verify">
                            
                            <div class="mb-3">
                                <label for="code" class="form-label">Verification Code</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-key"></i></span>
                                    <input type="text" class="form-control" id="code" name="code" 
                                           placeholder="000000" maxlength="6" pattern="[0-9]{6}" required>
                                </div>
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-check me-2"></i>
                                    Verify and Enable 2FA
                                </button>
                            </div>
                        </form>
                    <?php else: ?>
                        <!-- 2FA Setup - Initial -->
                        <div class="text-center mb-4">
                            <div class="mb-3">
                                <i class="fas fa-shield-alt fa-4x text-primary"></i>
                            </div>
                            <h4>Enable Two-Factor Authentication</h4>
                            <p class="text-muted">Add an extra layer of security to your account with 2FA.</p>
                        </div>
                        
                        <div class="alert alert-info">
                            <h6 class="alert-heading">How it works:</h6>
                            <ol class="mb-0">
                                <li>Download an authenticator app (Google Authenticator, Authy, etc.)</li>
                                <li>Click the button below to generate a secret key</li>
                                <li>Scan the QR code with your authenticator app</li>
                                <li>Enter the verification code to enable 2FA</li>
                            </ol>
                        </div>
                        
                        <form method="POST" action="">
                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                            <input type="hidden" name="action" value="setup">
                            
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-plus me-2"></i>
                                    Set Up Two-Factor Authentication
                                </button>
                            </div>
                        </form>
                    <?php endif; ?>
                    
                    <div class="mt-4">
                        <a href="index.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-2"></i>
                            Back to Dashboard
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

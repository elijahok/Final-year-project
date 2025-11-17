<?php
// Application Configuration
define('APP_NAME', 'Smart E-Procurement System');
define('APP_VERSION', '1.0.0');
define('BASE_URL', 'http://localhost/smart-eprocurement-system');
define('DEBUG_MODE', true);

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'smart_eprocurement');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Security Configuration
define('CSRF_TOKEN_LENGTH', 32);
define('SESSION_LIFETIME', 7200); // 2 hours
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 900); // 15 minutes
define('PASSWORD_RESET_EXPIRY', 3600); // 1 hour

// Scoring System Weights (can be adjusted)
define('SCORE_WEIGHT_PRICE', 0.4);
define('SCORE_WEIGHT_COMPLIANCE', 0.2);
define('SCORE_WEIGHT_QUALITY', 0.2);
define('SCORE_WEIGHT_DELIVERY', 0.2);

// File Upload Configuration
define('MAX_FILE_SIZE', 5242880); // 5MB
define('ALLOWED_FILE_TYPES', ['pdf', 'doc', 'docx', 'xls', 'xlsx']);

// Company Information
define('SUPPORT_EMAIL', 'support@smarteprocurement.com');
define('COMPANY_ADDRESS', '123 Business Street, Nairobi, Kenya');
define('COMPANY_PHONE', '+254 700 000 000');

// GPS & Route Configuration
define('GPS_API_KEY', ''); // Add your Google Maps API key
define('ROUTE_OPTIMIZATION_ENABLED', true);

// Mobile Money Configuration
define('MPESA_CONSUMER_KEY', '');
define('MPESA_CONSUMER_SECRET', '');
define('MPESA_PASSKEY', '');
define('AIRTEL_MONEY_API_KEY', '');

// Email Configuration (for notifications)
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', '');
define('SMTP_PASSWORD', '');
define('SMTP_FROM_EMAIL', 'noreply@eprocurement.com');
define('SMTP_FROM_NAME', 'Smart E-Procurement');

// Language Configuration
define('DEFAULT_LANGUAGE', 'en');
define('SUPPORTED_LANGUAGES', ['en', 'sw']);

// Timezone
date_default_timezone_set('Africa/Nairobi');

// Error Reporting
if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Start Session
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_strict_mode', 1);
    ini_set('session.cookie_secure', 0); // Set to 1 for HTTPS
    session_start();
}

// Include Functions
require_once __DIR__ . '/../includes/functions.php';
?>

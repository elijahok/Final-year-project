<?php
$pageTitle = $pageTitle ?? APP_NAME;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="<?= BASE_URL ?>/assets/images/favicon.ico">
    
    <!-- Meta Tags for SEO -->
    <meta name="description" content="<?= APP_NAME ?> - Smart E-Procurement System">
    <meta name="keywords" content="procurement, tender, bidding, vendor, e-procurement">
    <meta name="author" content="<?= APP_NAME ?>">
    
    <!-- Open Graph Meta Tags -->
    <meta property="og:title" content="<?= htmlspecialchars($pageTitle) ?>">
    <meta property="og:description" content="<?= APP_NAME ?> - Smart E-Procurement System">
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?= BASE_URL . $_SERVER['REQUEST_URI'] ?>">
    
    <!-- CSRF Token for AJAX requests -->
    <meta name="csrf-token" content="<?= generateCSRFToken() ?>">
    
    <style>
        /* Custom animations */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .fade-in {
            animation: fadeIn 0.5s ease-out;
        }
        
        /* Loading spinner */
        .spinner {
            border: 3px solid #f3f3f3;
            border-top: 3px solid #007bff;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
        }
        
        ::-webkit-scrollbar-track {
            background: #f1f1f1;
        }
        
        ::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 4px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: #555;
        }
        
        /* Print styles */
        @media print {
            .no-print { display: none !important; }
            .print-only { display: block !important; }
        }
        
        /* Accessibility */
        .sr-only {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0,0,0,0);
            white-space: nowrap;
            border: 0;
        }
        
        /* Focus styles */
        .btn:focus, .form-control:focus, .form-select:focus {
            outline: 2px solid #007bff;
            outline-offset: 2px;
        }
        
        /* Dark mode support */
        @media (prefers-color-scheme: dark) {
            .bg-light { background-color: #2d3748 !important; }
            .text-muted { color: #a0aec0 !important; }
        }
    </style>
</head>
<body>
    <!-- Skip to main content for accessibility -->
    <a href="#main-content" class="sr-only">Skip to main content</a>
    
    <!-- Navigation -->
    <?php if (isLoggedIn()): ?>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary sticky-top no-print">
        <div class="container-fluid">
            <a class="navbar-brand" href="<?= BASE_URL ?>/public/<?= $_SESSION['user_role'] ?>/dashboard.php">
                <i class="fas fa-warehouse me-2"></i>
                <?= APP_NAME ?>
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="<?= BASE_URL ?>/public/<?= $_SESSION['user_role'] ?>/dashboard.php">
                            <i class="fas fa-tachometer-alt me-1"></i>
                            <?= translate('dashboard') ?>
                        </a>
                    </li>
                    
                    <?php if ($_SESSION['user_role'] === 'admin'): ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                                <i class="fas fa-file-contract me-1"></i>
                                <?= translate('tenders') ?>
                            </a>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="<?= BASE_URL ?>/public/admin/tenders.php">Manage Tenders</a></li>
                                <li><a class="dropdown-item" href="<?= BASE_URL ?>/public/admin/tender-add.php">Add Tender</a></li>
                                <li><a class="dropdown-item" href="<?= BASE_URL ?>/public/admin/bids.php">Review Bids</a></li>
                            </ul>
                        </li>
                        
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                                <i class="fas fa-users me-1"></i>
                                Users
                            </a>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="<?= BASE_URL ?>/public/admin/users.php">Manage Users</a></li>
                                <li><a class="dropdown-item" href="<?= BASE_URL ?>/public/admin/vendors.php">Vendors</a></li>
                                <li><a class="dropdown-item" href="<?= BASE_URL ?>/public/admin/transporters.php">Transporters</a></li>
                                <li><a class="dropdown-item" href="<?= BASE_URL ?>/public/admin/farmers.php">Farmers</a></li>
                            </ul>
                        </li>
                        
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                                <i class="fas fa-chart-line me-1"></i>
                                Analytics
                            </a>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="<?= BASE_URL ?>/public/admin/analytics.php">Dashboard Analytics</a></li>
                                <li><a class="dropdown-item" href="<?= BASE_URL ?>/public/admin/reports.php">Reports</a></li>
                                <li><a class="dropdown-item" href="<?= BASE_URL ?>/public/admin/audit.php">Audit Log</a></li>
                            </ul>
                        </li>
                        
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                                <i class="fas fa-truck me-1"></i>
                                Transport
                            </a>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="<?= BASE_URL ?>/public/admin/transport-requests.php">Transport Requests</a></li>
                                <li><a class="dropdown-item" href="<?= BASE_URL ?>/public/admin/gps-tracking.php">GPS Tracking</a></li>
                                <li><a class="dropdown-item" href="<?= BASE_URL ?>/public/admin/quality-reports.php">Quality Reports</a></li>
                            </ul>
                        </li>
                    <?php endif; ?>
                    
                    <?php if ($_SESSION['user_role'] === 'vendor'): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?= BASE_URL ?>/public/vendor/tenders.php">
                                <i class="fas fa-file-contract me-1"></i>
                                <?= translate('tenders') ?>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?= BASE_URL ?>/public/vendor/bids.php">
                                <i class="fas fa-handshake me-1"></i>
                                My Bids
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?= BASE_URL ?>/public/vendor/transport.php">
                                <i class="fas fa-truck me-1"></i>
                                Transport
                            </a>
                        </li>
                    <?php endif; ?>
                    
                    <?php if ($_SESSION['user_role'] === 'transporter'): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?= BASE_URL ?>/public/transporter/requests.php">
                                <i class="fas fa-clipboard-list me-1"></i>
                                Transport Requests
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?= BASE_URL ?>/public/transporter/my-trips.php">
                                <i class="fas fa-route me-1"></i>
                                My Trips
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?= BASE_URL ?>/public/transporter/ratings.php">
                                <i class="fas fa-star me-1"></i>
                                Ratings
                            </a>
                        </li>
                    <?php endif; ?>
                    
                    <?php if ($_SESSION['user_role'] === 'farmer'): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?= BASE_URL ?>/public/farmer/produce.php">
                                <i class="fas fa-seedling me-1"></i>
                                My Produce
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?= BASE_URL ?>/public/farmer/transport.php">
                                <i class="fas fa-truck me-1"></i>
                                Request Transport
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?= BASE_URL ?>/public/farmer/orders.php">
                                <i class="fas fa-shopping-cart me-1"></i>
                                Orders
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
                
                <ul class="navbar-nav">
                    <!-- Notifications -->
                    <li class="nav-item dropdown">
                        <a class="nav-link position-relative" href="#" data-bs-toggle="dropdown">
                            <i class="fas fa-bell"></i>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" id="notificationCount">
                                0
                            </span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" style="min-width: 300px;">
                            <li class="dropdown-header">Notifications</li>
                            <li><hr class="dropdown-divider"></li>
                            <li id="notificationsList">
                                <div class="dropdown-item text-muted text-center">
                                    <small>No new notifications</small>
                                </div>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item text-center" href="<?= BASE_URL ?>/public/notifications.php">
                                    View All Notifications
                                </a>
                            </li>
                        </ul>
                    </li>
                    
                    <!-- Wallet -->
                    <li class="nav-item dropdown">
                        <a class="nav-link" href="#" data-bs-toggle="dropdown">
                            <i class="fas fa-wallet me-1"></i>
                            <span id="walletBalance">KES 0.00</span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li class="dropdown-header">My Wallet</li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item" href="<?= BASE_URL ?>/public/wallet.php">
                                    <i class="fas fa-eye me-2"></i>View Balance
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="<?= BASE_URL ?>/public/wallet.php?action=fund">
                                    <i class="fas fa-plus me-2"></i>Add Funds
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="<?= BASE_URL ?>/public/wallet.php?action=withdraw">
                                    <i class="fas fa-minus me-2"></i>Withdraw
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="<?= BASE_URL ?>/public/wallet.php?action=history">
                                    <i class="fas fa-history me-2"></i>Transaction History
                                </a>
                            </li>
                        </ul>
                    </li>
                    
                    <!-- User Menu -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle me-1"></i>
                            <?= htmlspecialchars($_SESSION['user_name']) ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li class="dropdown-header"><?= ucfirst($_SESSION['user_role']) ?></li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item" href="<?= BASE_URL ?>/public/profile.php">
                                    <i class="fas fa-user me-2"></i><?= translate('profile') ?>
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="<?= BASE_URL ?>/public/settings.php">
                                    <i class="fas fa-cog me-2"></i><?= translate('settings') ?>
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item text-danger" href="<?= BASE_URL ?>/public/logout.php">
                                    <i class="fas fa-sign-out-alt me-2"></i><?= translate('logout') ?>
                                </a>
                            </li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    <?php endif; ?>
    
    <!-- Main Content -->
    <main id="main-content" class="<?= isLoggedIn() ? 'container-fluid py-4' : '' ?>">
        
        <!-- Flash Messages -->
        <?php if (isset($_SESSION['flash'])): ?>
            <?php foreach ($_SESSION['flash'] as $type => $message): ?>
                <div class="alert alert-<?= $type ?> alert-dismissible fade show no-print" role="alert">
                    <i class="fas fa-<?= $type === 'success' ? 'check-circle' : ($type === 'danger' ? 'exclamation-triangle' : 'info-circle') ?> me-2"></i>
                    <?= $message ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endforeach; ?>
            <?php unset($_SESSION['flash']); ?>
        <?php endif; ?>

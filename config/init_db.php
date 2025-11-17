<?php
// Database Initialization Script
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';

// Security check
if (!isset($_GET['key']) || $_GET['key'] !== 'init_2024_secure') {
    die('Access denied. Invalid initialization key.');
}

echo "<h2>Initializing Smart E-Procurement System Database</h2>";

try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    
    echo "<p>✓ Database connection established</p>";
    
    // Create tables
    
    // Users table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(255) UNIQUE NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            full_name VARCHAR(255) NOT NULL,
            role ENUM('admin', 'vendor', 'transporter', 'farmer') NOT NULL,
            status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
            phone VARCHAR(20),
            profile_image VARCHAR(255),
            last_login DATETIME,
            login_attempts INT DEFAULT 0,
            locked_until DATETIME,
            language VARCHAR(5) DEFAULT 'en',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_email (email),
            INDEX idx_role (role),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "<p>✓ Users table created</p>";
    
    // Vendor profiles table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS vendor_profiles (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            company_name VARCHAR(255) NOT NULL,
            company_address TEXT,
            tax_id VARCHAR(50),
            registration_number VARCHAR(50),
            business_license VARCHAR(255),
            contact_person VARCHAR(255),
            contact_phone VARCHAR(20),
            website VARCHAR(255),
            description TEXT,
            services_offered TEXT,
            years_in_business INT,
            annual_revenue DECIMAL(15,2),
            employee_count INT,
            certifications TEXT,
            latitude DECIMAL(10,8),
            longitude DECIMAL(11,8),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_user_id (user_id),
            INDEX idx_company_name (company_name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "<p>✓ Vendor profiles table created</p>";
    
    // Transporter profiles table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS transporter_profiles (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            company_name VARCHAR(255) NOT NULL,
            vehicle_type ENUM('truck', 'van', 'motorcycle', 'bicycle', 'pickup') NOT NULL,
            vehicle_capacity DECIMAL(8,2),
            vehicle_plate VARCHAR(20) UNIQUE,
            driver_license VARCHAR(50),
            insurance_details TEXT,
            service_areas TEXT,
            base_location VARCHAR(255),
            latitude DECIMAL(10,8),
            longitude DECIMAL(11,8),
            current_latitude DECIMAL(10,8),
            current_longitude DECIMAL(11,8),
            is_available BOOLEAN DEFAULT TRUE,
            rating DECIMAL(3,2) DEFAULT 0.00,
            total_trips INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_user_id (user_id),
            INDEX idx_available (is_available),
            INDEX idx_location (latitude, longitude),
            INDEX idx_rating (rating)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "<p>✓ Transporter profiles table created</p>";
    
    // Farmer profiles table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS farmer_profiles (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            farm_name VARCHAR(255),
            farm_address TEXT,
            farm_size DECIMAL(10,2),
            main_crops TEXT,
            farming_type ENUM('organic', 'conventional', 'mixed') DEFAULT 'conventional',
            certification TEXT,
            latitude DECIMAL(10,8),
            longitude DECIMAL(11,8),
            harvest_seasons TEXT,
            average_yield DECIMAL(10,2),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_user_id (user_id),
            INDEX idx_location (latitude, longitude)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "<p>✓ Farmer profiles table created</p>";
    
    // Tenders table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS tenders (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            description TEXT NOT NULL,
            category VARCHAR(100),
            budget_min DECIMAL(15,2),
            budget_max DECIMAL(15,2),
            deadline DATETIME NOT NULL,
            status ENUM('draft', 'published', 'closed', 'awarded', 'cancelled') DEFAULT 'draft',
            created_by INT NOT NULL,
            awarded_to INT NULL,
            awarded_amount DECIMAL(15,2) NULL,
            delivery_location VARCHAR(255),
            delivery_lat DECIMAL(10,8),
            delivery_lng DECIMAL(11,8),
            requirements TEXT,
            evaluation_criteria TEXT,
            attachments TEXT,
            view_count INT DEFAULT 0,
            bid_count INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (created_by) REFERENCES users(id),
            FOREIGN KEY (awarded_to) REFERENCES users(id),
            INDEX idx_status (status),
            INDEX idx_deadline (deadline),
            INDEX idx_category (category),
            INDEX idx_created_by (created_by),
            INDEX idx_delivery_location (delivery_lat, delivery_lng)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "<p>✓ Tenders table created</p>";
    
    // Bids table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS bids (
            id INT AUTO_INCREMENT PRIMARY KEY,
            tender_id INT NOT NULL,
            vendor_id INT NOT NULL,
            amount DECIMAL(15,2) NOT NULL,
            notes TEXT,
            status ENUM('submitted', 'under_review', 'accepted', 'rejected', 'withdrawn') DEFAULT 'submitted',
            submission_date DATETIME DEFAULT CURRENT_TIMESTAMP,
            review_date DATETIME NULL,
            reviewed_by INT NULL,
            review_notes TEXT,
            attachments TEXT,
            delivery_time INT,
            quality_assurance TEXT,
            compliance_rating DECIMAL(5,2),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (tender_id) REFERENCES tenders(id) ON DELETE CASCADE,
            FOREIGN KEY (vendor_id) REFERENCES users(id),
            FOREIGN KEY (reviewed_by) REFERENCES users(id),
            INDEX idx_tender_id (tender_id),
            INDEX idx_vendor_id (vendor_id),
            INDEX idx_status (status),
            INDEX idx_amount (amount)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "<p>✓ Bids table created</p>";
    
    // Bid scores table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS bid_scores (
            id INT AUTO_INCREMENT PRIMARY KEY,
            bid_id INT NOT NULL,
            price_score DECIMAL(5,2) NOT NULL,
            compliance_score DECIMAL(5,2) NOT NULL,
            quality_score DECIMAL(5,2) NOT NULL,
            delivery_score DECIMAL(5,2) NOT NULL,
            total_score DECIMAL(5,2) NOT NULL,
            scored_by INT NOT NULL,
            scoring_date DATETIME DEFAULT CURRENT_TIMESTAMP,
            comments TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (bid_id) REFERENCES bids(id) ON DELETE CASCADE,
            FOREIGN KEY (scored_by) REFERENCES users(id),
            INDEX idx_bid_id (bid_id),
            INDEX idx_total_score (total_score)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "<p>✓ Bid scores table created</p>";
    
    // Transport requests table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS transport_requests (
            id INT AUTO_INCREMENT PRIMARY KEY,
            request_type ENUM('farmer', 'vendor', 'consumer') NOT NULL,
            requester_id INT NOT NULL,
            pickup_address TEXT NOT NULL,
            pickup_lat DECIMAL(10,8),
            pickup_lng DECIMAL(11,8),
            delivery_address TEXT NOT NULL,
            delivery_lat DECIMAL(10,8),
            delivery_lng DECIMAL(11,8),
            produce_type VARCHAR(100),
            weight DECIMAL(8,2),
            volume DECIMAL(8,2),
            special_requirements TEXT,
            urgency_level ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
            preferred_date DATE,
            preferred_time TIME,
            budget_max DECIMAL(10,2),
            status ENUM('pending', 'matched', 'accepted', 'in_transit', 'delivered', 'cancelled') DEFAULT 'pending',
            matched_transporter_id INT NULL,
            estimated_cost DECIMAL(10,2),
            actual_cost DECIMAL(10,2),
            estimated_duration INT,
            actual_duration INT,
            distance_km DECIMAL(8,2),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (requester_id) REFERENCES users(id),
            FOREIGN KEY (matched_transporter_id) REFERENCES users(id),
            INDEX idx_status (status),
            INDEX idx_requester (requester_id),
            INDEX idx_transporter (matched_transporter_id),
            INDEX idx_pickup_location (pickup_lat, pickup_lng),
            INDEX idx_delivery_location (delivery_lat, delivery_lng),
            INDEX idx_urgency (urgency_level)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "<p>✓ Transport requests table created</p>";
    
    // GPS tracking table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS gps_tracking (
            id INT AUTO_INCREMENT PRIMARY KEY,
            transport_request_id INT NOT NULL,
            transporter_id INT NOT NULL,
            latitude DECIMAL(10,8) NOT NULL,
            longitude DECIMAL(11,8) NOT NULL,
            speed DECIMAL(5,2),
            heading DECIMAL(5,2),
            accuracy DECIMAL(5,2),
            timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
            is_moving BOOLEAN DEFAULT FALSE,
            battery_level INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (transport_request_id) REFERENCES transport_requests(id) ON DELETE CASCADE,
            FOREIGN KEY (transporter_id) REFERENCES users(id),
            INDEX idx_request_id (transport_request_id),
            INDEX idx_transporter (transporter_id),
            INDEX idx_timestamp (timestamp),
            INDEX idx_location (latitude, longitude)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "<p>✓ GPS tracking table created</p>";
    
    // Mobile money transactions table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS mobile_money_transactions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            transaction_ref VARCHAR(50) UNIQUE NOT NULL,
            user_id INT NOT NULL,
            phone_number VARCHAR(20) NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            provider ENUM('mpesa', 'airtel_money', 'tkash', 'equitel') NOT NULL,
            transaction_type ENUM('payment', 'refund', 'wallet_funding', 'wallet_withdrawal') DEFAULT 'payment',
            status ENUM('pending', 'processing', 'completed', 'failed', 'cancelled') DEFAULT 'pending',
            related_id INT NULL,
            related_type VARCHAR(50) NULL,
            callback_data TEXT,
            failure_reason TEXT,
            processed_at DATETIME NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id),
            INDEX idx_transaction_ref (transaction_ref),
            INDEX idx_user_id (user_id),
            INDEX idx_status (status),
            INDEX idx_provider (provider)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "<p>✓ Mobile money transactions table created</p>";
    
    // User wallets table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS user_wallets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            balance DECIMAL(10,2) DEFAULT 0.00,
            available_balance DECIMAL(10,2) DEFAULT 0.00,
            frozen_balance DECIMAL(10,2) DEFAULT 0.00,
            currency VARCHAR(3) DEFAULT 'KES',
            is_active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_user_id (user_id),
            INDEX idx_balance (balance)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "<p>✓ User wallets table created</p>";
    
    // Quality reports table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS quality_reports (
            id INT AUTO_INCREMENT PRIMARY KEY,
            reporter_id INT NOT NULL,
            related_id INT NOT NULL,
            related_type ENUM('transport_request', 'bid', 'tender') NOT NULL,
            report_type ENUM('damage', 'delay', 'quality_issue', 'missing_items', 'other') NOT NULL,
            severity ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
            description TEXT NOT NULL,
            photos TEXT,
            location_lat DECIMAL(10,8),
            location_lng DECIMAL(11,8),
            status ENUM('open', 'under_investigation', 'resolved', 'closed') DEFAULT 'open',
            resolution TEXT,
            compensation_amount DECIMAL(10,2),
            resolved_by INT NULL,
            resolved_at DATETIME NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (reporter_id) REFERENCES users(id),
            FOREIGN KEY (resolved_by) REFERENCES users(id),
            INDEX idx_related (related_type, related_id),
            INDEX idx_reporter (reporter_id),
            INDEX idx_status (status),
            INDEX idx_severity (severity)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "<p>✓ Quality reports table created</p>";
    
    // Notifications table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            title VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            type ENUM('info', 'success', 'warning', 'error', 'system') DEFAULT 'info',
            related_id INT NULL,
            related_type VARCHAR(50) NULL,
            is_read BOOLEAN DEFAULT FALSE,
            is_push_sent BOOLEAN DEFAULT FALSE,
            push_sent_at DATETIME NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_user_id (user_id),
            INDEX idx_is_read (is_read),
            INDEX idx_type (type),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "<p>✓ Notifications table created</p>";
    
    // Audit log table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS audit_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT,
            action VARCHAR(100) NOT NULL,
            details TEXT,
            ip_address VARCHAR(45),
            user_agent TEXT,
            related_id INT NULL,
            related_type VARCHAR(50) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_user_id (user_id),
            INDEX idx_action (action),
            INDEX idx_created_at (created_at),
            INDEX idx_related (related_type, related_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "<p>✓ Audit log table created</p>";
    
    // Transporter ratings table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS transporter_ratings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            transporter_id INT NOT NULL,
            reviewer_id INT NOT NULL,
            transport_request_id INT NOT NULL,
            rating DECIMAL(2,1) NOT NULL,
            punctuality_rating DECIMAL(2,1),
            communication_rating DECIMAL(2,1),
            vehicle_condition_rating DECIMAL(2,1),
            professionalism_rating DECIMAL(2,1),
            review TEXT,
            response TEXT,
            status ENUM('published', 'hidden', 'flagged') DEFAULT 'published',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (transporter_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (reviewer_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (transport_request_id) REFERENCES transport_requests(id) ON DELETE CASCADE,
            INDEX idx_transporter (transporter_id),
            INDEX idx_reviewer (reviewer_id),
            INDEX idx_rating (rating),
            UNIQUE KEY unique_review (transporter_id, reviewer_id, transport_request_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "<p>✓ Transporter ratings table created</p>";
    
    // Analytics data table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS analytics_data (
            id INT AUTO_INCREMENT PRIMARY KEY,
            metric_name VARCHAR(100) NOT NULL,
            metric_value DECIMAL(15,2) NOT NULL,
            metric_type ENUM('count', 'sum', 'average', 'percentage') DEFAULT 'count',
            period_type ENUM('hourly', 'daily', 'weekly', 'monthly') DEFAULT 'daily',
            period_date DATE NOT NULL,
            period_hour INT NULL,
            dimensions TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_metric_date (metric_name, period_date),
            INDEX idx_period_type (period_type),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "<p>✓ Analytics data table created</p>";
    
    // Emergency reports table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS emergency_reports (
            id INT AUTO_INCREMENT PRIMARY KEY,
            reporter_id INT NOT NULL,
            transport_request_id INT NULL,
            emergency_type ENUM('breakdown', 'accident', 'theft', 'medical', 'road_block', 'other') NOT NULL,
            severity ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
            description TEXT NOT NULL,
            location_lat DECIMAL(10,8),
            location_lng DECIMAL(11,8),
            location_address TEXT,
            photos TEXT,
            status ENUM('active', 'responding', 'resolved', 'closed') DEFAULT 'active',
            response_team VARCHAR(255),
            resolution TEXT,
            resolved_by INT NULL,
            resolved_at DATETIME NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (reporter_id) REFERENCES users(id),
            FOREIGN KEY (resolved_by) REFERENCES users(id),
            FOREIGN KEY (transport_request_id) REFERENCES transport_requests(id) ON DELETE SET NULL,
            INDEX idx_status (status),
            INDEX idx_reporter (reporter_id),
            INDEX idx_severity (severity),
            INDEX idx_emergency_type (emergency_type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "<p>✓ Emergency reports table created</p>";
    
    // Produce categories table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS produce_categories (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            description TEXT,
            packaging_guide TEXT,
            storage_requirements TEXT,
            shelf_life_days INT,
            temperature_range VARCHAR(50),
            humidity_range VARCHAR(50),
            handling_instructions TEXT,
            spoilage_rate DECIMAL(5,2) DEFAULT 0.00,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_name (name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "<p>✓ Produce categories table created</p>";
    
    // Digital receipts table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS digital_receipts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            receipt_number VARCHAR(50) UNIQUE NOT NULL,
            transaction_id INT NOT NULL,
            transaction_type ENUM('payment', 'refund', 'transport_fee', 'service_fee') NOT NULL,
            payer_id INT NOT NULL,
            payee_id INT NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            currency VARCHAR(3) DEFAULT 'KES',
            description TEXT,
            items TEXT,
            tax_amount DECIMAL(10,2) DEFAULT 0.00,
            total_amount DECIMAL(10,2) NOT NULL,
            payment_method VARCHAR(50),
            payment_reference VARCHAR(100),
            status ENUM('draft', 'issued', 'paid', 'cancelled') DEFAULT 'draft',
            pdf_path VARCHAR(255),
            issued_by INT NOT NULL,
            issued_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            paid_at DATETIME NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (payer_id) REFERENCES users(id),
            FOREIGN KEY (payee_id) REFERENCES users(id),
            FOREIGN KEY (issued_by) REFERENCES users(id),
            INDEX idx_receipt_number (receipt_number),
            INDEX idx_transaction (transaction_type, transaction_id),
            INDEX idx_payer (payer_id),
            INDEX idx_status (status),
            INDEX idx_issued_at (issued_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "<p>✓ Digital receipts table created</p>";
    
    // Insert default admin user
    $adminPassword = hashPassword('Admin@123');
    $pdo->exec("
        INSERT INTO users (email, password_hash, full_name, role, status) 
        VALUES ('admin@example.com', '$adminPassword', 'System Administrator', 'admin', 'active')
        ON DUPLICATE KEY UPDATE email = email
    ");
    echo "<p>✓ Default admin user created (Email: admin@example.com, Password: Admin@123)</p>";
    
    // Insert default produce categories
    $categories = [
        ['Vegetables', 'Fresh vegetables from farms', 'Refrigerated containers, proper ventilation', 'Keep cool and dry', 7, '2-8°C', '85-95%', 'Handle gently, avoid bruising', 5.0],
        ['Fruits', 'Fresh seasonal fruits', 'Protective packaging, avoid crushing', 'Cool storage', 5, '4-12°C', '80-90%', 'Handle with care, check ripeness', 8.0],
        ['Grains', 'Dried grains and cereals', 'Moisture-proof bags, airtight containers', 'Dry, cool place', 365, '10-20°C', '60-70%', 'Keep dry, protect from pests', 2.0],
        ['Dairy', 'Milk, cheese, and other dairy products', 'Refrigerated transport, insulated containers', 'Constant refrigeration', 3, '1-4°C', '85-95%', 'Maintain cold chain', 15.0],
        ['Meat', 'Fresh and processed meat products', 'Vacuum sealed, refrigerated', 'Strict temperature control', 2, '0-4°C', '85-95%', 'Maintain hygiene, cold chain', 20.0]
    ];
    
    foreach ($categories as $category) {
        $pdo->exec("
            INSERT INTO produce_categories (name, description, packaging_guide, storage_requirements, shelf_life_days, temperature_range, humidity_range, handling_instructions, spoilage_rate)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE name = name
        ", $category);
    }
    echo "<p>✓ Default produce categories inserted</p>";
    
    echo "<h3>✅ Database initialization completed successfully!</h3>";
    echo "<p><strong>Next steps:</strong></p>";
    echo "<ul>";
    echo "<li>1. Change the default admin password after first login</li>";
    echo "<li>2. Configure your web server to point to the /public directory</li>";
    echo "<li>3. Set up file permissions for uploads directory</li>";
    echo "<li>4. Configure mobile money API keys in config/config.php</li>";
    echo "<li>5. Add Google Maps API key for GPS features</li>";
    echo "</ul>";
    echo "<p><a href='../public/login.php'>Go to Login Page</a></p>";
    
} catch (Exception $e) {
    echo "<h3>❌ Database initialization failed!</h3>";
    echo "<p>Error: " . $e->getMessage() . "</p>";
    echo "<p>Please check your database configuration and try again.</p>";
}
?>

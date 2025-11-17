<?php
require_once __DIR__ . '/../config/config.php';

$pageTitle = 'Terms and Conditions - ' . APP_NAME;
include __DIR__ . '/../includes/header.php';
?>

<div class="container py-5">
    <div class="row">
        <div class="col-lg-8 mx-auto">
            <div class="card shadow">
                <div class="card-body p-5">
                    <h1 class="h2 mb-4 text-primary">Terms and Conditions</h1>
                    <p class="text-muted mb-4">Last updated: <?= date('F j, Y') ?></p>
                    
                    <div class="terms-content">
                        <h3 class="h4 mb-3">1. Acceptance of Terms</h3>
                        <p>By accessing and using <?= APP_NAME ?>, you accept and agree to be bound by the terms and provision of this agreement.</p>
                        
                        <h3 class="h4 mb-3 mt-4">2. Use License</h3>
                        <p>Permission is granted to temporarily download one copy of the materials on <?= APP_NAME ?> for personal, non-commercial transitory viewing only. This is the grant of a license, not a transfer of title, and under this license you may not:</p>
                        <ul>
                            <li>modify or copy the materials</li>
                            <li>use the materials for any commercial purpose or for any public display</li>
                            <li>attempt to reverse engineer any software contained on <?= APP_NAME ?></li>
                            <li>remove any copyright or other proprietary notations from the materials</li>
                        </ul>
                        
                        <h3 class="h4 mb-3 mt-4">3. Account Registration</h3>
                        <p>To access certain features of our service, you must register for an account. You agree to:</p>
                        <ul>
                            <li>Provide accurate, current, and complete information</li>
                            <li>Maintain and update your account information</li>
                            <li>Keep your password secure and confidential</li>
                            <li>Accept responsibility for all activities under your account</li>
                        </ul>
                        
                        <h3 class="h4 mb-3 mt-4">4. User Roles and Responsibilities</h3>
                        
                        <h5 class="h5 mb-2">4.1 Farmers</h5>
                        <ul>
                            <li>Post accurate information about farm produce</li>
                            <li>Fulfill orders in a timely manner</li>
                            <li>Maintain quality standards</li>
                            <li>Arrange for transportation when needed</li>
                        </ul>
                        
                        <h5 class="h5 mb-2">4.2 Vendors</h5>
                        <ul>
                            <li>Submit competitive and accurate bids</li>
                            <li>Honor awarded contracts</li>
                            <li>Maintain professional business practices</li>
                            <li>Provide quality products/services</li>
                        </ul>
                        
                        <h5 class="h5 mb-2">4.3 Transporters</h5>
                        <ul>
                            <li>Provide reliable transportation services</li>
                            <li>Handle goods with care</li>
                            <li>Maintain proper documentation</li>
                            <li>Adhere to delivery schedules</li>
                        </ul>
                        
                        <h3 class="h4 mb-3 mt-4">5. Prohibited Activities</h3>
                        <p>You may not use our service to:</p>
                        <ul>
                            <li>Engage in fraudulent or deceptive practices</li>
                            <li>Post false or misleading information</li>
                            <li>Violate applicable laws or regulations</li>
                            <li>Infringe on intellectual property rights</li>
                            <li>Transmit harmful or offensive content</li>
                        </ul>
                        
                        <h3 class="h4 mb-3 mt-4">6. Payment Terms</h3>
                        <p>Payment terms vary by transaction type and are agreed upon between parties. <?= APP_NAME ?> acts as a platform and is not responsible for payment disputes between users.</p>
                        
                        <h3 class="h4 mb-3 mt-4">7. Privacy</h3>
                        <p>Your privacy is important to us. Please review our Privacy Policy, which also governs your use of our service, to understand our practices.</p>
                        
                        <h3 class="h4 mb-3 mt-4">8. Dispute Resolution</h3>
                        <p>Any disputes arising from the use of our service will be resolved through:</p>
                        <ol>
                            <li>Direct negotiation between parties</li>
                            <li>Mediation by <?= APP_NAME ?> administrators</li>
                            <li>Arbitration if necessary</li>
                        </ol>
                        
                        <h3 class="h4 mb-3 mt-4">9. Limitation of Liability</h3>
                        <p>In no event shall <?= APP_NAME ?>, its directors, employees, partners, agents, suppliers, or affiliates be liable for any indirect, incidental, special, consequential, or punitive damages.</p>
                        
                        <h3 class="h4 mb-3 mt-4">10. Termination</h3>
                        <p>We may terminate or suspend your account immediately, without prior notice or liability, for any reason whatsoever, including without limitation if you breach the Terms.</p>
                        
                        <h3 class="h4 mb-3 mt-4">11. Changes to Terms</h3>
                        <p>We reserve the right to modify or replace these Terms at any time. If a revision is material, we will try to provide at least 30 days notice prior to any new terms taking effect.</p>
                        
                        <h3 class="h4 mb-3 mt-4">12. Contact Information</h3>
                        <p>Questions about the Terms of Service should be sent to us at <?= SUPPORT_EMAIL ?></p>
                    </div>
                    
                    <div class="mt-5">
                        <a href="register.php" class="btn btn-primary">
                            <i class="fas fa-arrow-left me-2"></i>
                            Back to Registration
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

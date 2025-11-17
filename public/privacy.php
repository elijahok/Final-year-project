<?php
require_once __DIR__ . '/../config/config.php';

$pageTitle = 'Privacy Policy - ' . APP_NAME;
include __DIR__ . '/../includes/header.php';
?>

<div class="container py-5">
    <div class="row">
        <div class="col-lg-8 mx-auto">
            <div class="card shadow">
                <div class="card-body p-5">
                    <h1 class="h2 mb-4 text-primary">Privacy Policy</h1>
                    <p class="text-muted mb-4">Last updated: <?= date('F j, Y') ?></p>
                    
                    <div class="privacy-content">
                        <h3 class="h4 mb-3">1. Information We Collect</h3>
                        
                        <h5 class="h5 mb-2">1.1 Personal Information</h5>
                        <p>When you register for an account, we collect:</p>
                        <ul>
                            <li>Name and contact information</li>
                            <li>Email address and phone number</li>
                            <li>Business information (for vendors)</li>
                            <li>Location information (for transporters)</li>
                            <li>Payment information</li>
                        </ul>
                        
                        <h5 class="h5 mb-2">1.2 Transaction Information</h5>
                        <p>We collect information about your transactions, including:</p>
                        <ul>
                            <li>Tender submissions and bids</li>
                            <li>Transport requests</li>
                            <li>Payment history</li>
                            <li>Communication with other users</li>
                        </ul>
                        
                        <h5 class="h5 mb-2">1.3 Technical Information</h5>
                        <p>We automatically collect technical information about your device and usage:</p>
                        <ul>
                            <li>IP address and browser type</li>
                            <li>Device information</li>
                            <li>Pages visited and time spent</li>
                            <li>Access times and dates</li>
                        </ul>
                        
                        <h3 class="h4 mb-3 mt-4">2. How We Use Your Information</h3>
                        <p>We use the information we collect to:</p>
                        <ul>
                            <li>Provide and maintain our service</li>
                            <li>Process transactions and send notifications</li>
                            <li>Improve user experience and platform features</li>
                            <li>Communicate with you about your account</li>
                            <li>Ensure security and prevent fraud</li>
                            <li>Comply with legal obligations</li>
                        </ul>
                        
                        <h3 class="h4 mb-3 mt-4">3. Information Sharing</h3>
                        
                        <h5 class="h5 mb-2">3.1 User-to-User Sharing</h5>
                        <p>Certain information is shared with other users to facilitate transactions:</p>
                        <ul>
                            <li>Contact information for transaction partners</li>
                            <li>Business details for vendors</li>
                            <li>Location information for transporters</li>
                            <li>Transaction history between parties</li>
                        </ul>
                        
                        <h5 class="h5 mb-2">3.2 Third-Party Service Providers</h5>
                        <p>We may share information with trusted third-party service providers who assist us in operating our service.</p>
                        
                        <h5 class="h5 mb-2">3.3 Legal Requirements</h5>
                        <p>We may disclose your information if required by law or to protect our rights, property, or safety.</p>
                        
                        <h3 class="h4 mb-3 mt-4">4. Data Security</h3>
                        <p>We implement appropriate security measures to protect your information:</p>
                        <ul>
                            <li>SSL encryption for data transmission</li>
                            <li>Secure password storage</li>
                            <li>Regular security audits</li>
                            <li>Access controls and authentication</li>
                            <li>Data backup and recovery systems</li>
                        </ul>
                        
                        <h3 class="h4 mb-3 mt-4">5. Your Rights and Choices</h3>
                        
                        <h5 class="h5 mb-2">5.1 Access and Update</h5>
                        <p>You can access and update your personal information through your account settings.</p>
                        
                        <h5 class="h5 mb-2">5.2 Data Deletion</h5>
                        <p>You can request deletion of your account and personal data, subject to legal and contractual obligations.</p>
                        
                        <h5 class="h5 mb-2">5.3 Communication Preferences</h5>
                        <p>You can manage your notification preferences in your account settings.</p>
                        
                        <h3 class="h4 mb-3 mt-4">6. Cookies and Tracking</h3>
                        <p>We use cookies and similar technologies to:</p>
                        <ul>
                            <li>Remember your preferences</li>
                            <li>Authenticate your session</li>
                            <li>Analyze platform usage</li>
                            <li>Provide personalized content</li>
                        </ul>
                        
                        <h3 class="h4 mb-3 mt-4">7. Data Retention</h3>
                        <p>We retain your information for as long as necessary to:</p>
                        <ul>
                            <li>Fulfill the purposes for which it was collected</li>
                            <li>Comply with legal requirements</li>
                            <li>Resolve disputes and enforce agreements</li>
                            <li>Maintain business records</li>
                        </ul>
                        
                        <h3 class="h4 mb-3 mt-4">8. International Data Transfers</h3>
                        <p>Your information may be transferred to and processed in countries other than your own. We ensure appropriate safeguards are in place.</p>
                        
                        <h3 class="h4 mb-3 mt-4">9. Children's Privacy</h3>
                        <p>Our service is not intended for children under 18. We do not knowingly collect personal information from children.</p>
                        
                        <h3 class="h4 mb-3 mt-4">10. Changes to This Policy</h3>
                        <p>We may update this privacy policy from time to time. We will notify you of any changes by posting the new policy on this page.</p>
                        
                        <h3 class="h4 mb-3 mt-4">11. Contact Us</h3>
                        <p>If you have any questions about this Privacy Policy, please contact us at:</p>
                        <ul>
                            <li>Email: <?= SUPPORT_EMAIL ?></li>
                            <li>Address: <?= COMPANY_ADDRESS ?></li>
                            <li>Phone: <?= COMPANY_PHONE ?></li>
                        </ul>
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

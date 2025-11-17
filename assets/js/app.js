// Smart E-Procurement System - Main JavaScript Application

// Application State
const App = {
    user: null,
    notifications: [],
    walletBalance: 0,
    isOnline: navigator.onLine,
    currentLocation: null,
    map: null,
    markers: [],
    
    // Initialize application
    init() {
        this.setupEventListeners();
        this.checkAuthentication();
        this.loadUserData();
        this.startRealTimeUpdates();
        this.initializeMaps();
        this.setupServiceWorker();
    },
    
    // Setup event listeners
    setupEventListeners() {
        // Online/Offline status
        window.addEventListener('online', () => {
            this.isOnline = true;
            this.hideOfflineIndicator();
            this.syncOfflineActions();
        });
        
        window.addEventListener('offline', () => {
            this.isOnline = false;
            this.showOfflineIndicator();
        });
        
        // Form submissions
        document.addEventListener('submit', (e) => {
            const form = e.target;
            if (form.classList.contains('ajax-form')) {
                e.preventDefault();
                this.handleAjaxForm(form);
            }
        });
        
        // Navigation
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('ajax-link')) {
                e.preventDefault();
                this.handleAjaxLink(e.target);
            }
        });
        
        // File uploads
        document.addEventListener('change', (e) => {
            if (e.target.classList.contains('file-input')) {
                this.handleFileUpload(e.target);
            }
        });
        
        // Geolocation
        if ('geolocation' in navigator) {
            this.watchPosition();
        }
    },
    
    // Check authentication status
    checkAuthentication() {
        fetch('/api/auth.php', {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.authenticated) {
                this.user = data.user;
                this.updateUIForAuthenticatedUser();
            } else {
                this.redirectToLogin();
            }
        })
        .catch(error => {
            console.error('Auth check failed:', error);
            this.redirectToLogin();
        });
    },
    
    // Load user data
    loadUserData() {
        if (!this.user) return;
        
        Promise.all([
            this.fetchNotifications(),
            this.fetchWalletBalance(),
            this.fetchUserProfile()
        ])
        .then(([notifications, wallet, profile]) => {
            this.notifications = notifications;
            this.walletBalance = wallet.balance;
            this.updateUI();
        })
        .catch(error => {
            console.error('Failed to load user data:', error);
        });
    },
    
    // Fetch notifications
    async fetchNotifications() {
        try {
            const response = await fetch('/api/notifications.php', {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            const data = await response.json();
            return data.notifications || [];
        } catch (error) {
            console.error('Failed to fetch notifications:', error);
            return [];
        }
    },
    
    // Fetch wallet balance
    async fetchWalletBalance() {
        try {
            const response = await fetch('/api/wallet.php', {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            const data = await response.json();
            return data;
        } catch (error) {
            console.error('Failed to fetch wallet balance:', error);
            return { balance: 0 };
        }
    },
    
    // Fetch user profile
    async fetchUserProfile() {
        try {
            const response = await fetch('/api/profile.php', {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            const data = await response.json();
            return data;
        } catch (error) {
            console.error('Failed to fetch user profile:', error);
            return {};
        }
    },
    
    // Update UI for authenticated user
    updateUIForAuthenticatedUser() {
        // Show user-specific elements
        document.querySelectorAll('.auth-only').forEach(el => {
            el.style.display = 'block';
        });
        
        document.querySelectorAll('.guest-only').forEach(el => {
            el.style.display = 'none';
        });
        
        // Update user name
        const userNameElements = document.querySelectorAll('.user-name');
        userNameElements.forEach(el => {
            el.textContent = this.user.full_name;
        });
        
        // Update user role
        const userRoleElements = document.querySelectorAll('.user-role');
        userRoleElements.forEach(el => {
            el.textContent = this.user.role;
        });
    },
    
    // Update UI
    updateUI() {
        this.updateNotificationBadge();
        this.updateWalletBalance();
        this.updateNotificationsList();
    },
    
    // Update notification badge
    updateNotificationBadge() {
        const badge = document.getElementById('notificationCount');
        if (badge) {
            const unreadCount = this.notifications.filter(n => !n.is_read).length;
            badge.textContent = unreadCount;
            badge.style.display = unreadCount > 0 ? 'inline' : 'none';
        }
    },
    
    // Update wallet balance
    updateWalletBalance() {
        const balanceElement = document.getElementById('walletBalance');
        if (balanceElement) {
            balanceElement.textContent = this.formatCurrency(this.walletBalance);
        }
    },
    
    // Update notifications list
    updateNotificationsList() {
        const list = document.getElementById('notificationsList');
        if (!list) return;
        
        if (this.notifications.length === 0) {
            list.innerHTML = `
                <div class="dropdown-item text-muted text-center">
                    <small>No new notifications</small>
                </div>
            `;
            return;
        }
        
        list.innerHTML = this.notifications.map(notif => `
            <a href="#" class="dropdown-item ${notif.is_read ? '' : 'bg-light'}" onclick="App.markNotificationAsRead(${notif.id})">
                <div class="d-flex">
                    <div class="flex-shrink-0">
                        <i class="fas fa-${this.getNotificationIcon(notif.type)} text-${this.getNotificationColor(notif.type)}"></i>
                    </div>
                    <div class="flex-grow-1 ms-2">
                        <h6 class="mb-1">${notif.title}</h6>
                        <p class="mb-0 small text-muted">${notif.message}</p>
                        <small class="text-muted">${this.timeAgo(notif.created_at)}</small>
                    </div>
                </div>
            </a>
        `).join('');
    },
    
    // Get notification icon
    getNotificationIcon(type) {
        const icons = {
            'info': 'info-circle',
            'success': 'check-circle',
            'warning': 'exclamation-triangle',
            'error': 'times-circle',
            'system': 'cog'
        };
        return icons[type] || 'info-circle';
    },
    
    // Get notification color
    getNotificationColor(type) {
        const colors = {
            'info': 'primary',
            'success': 'success',
            'warning': 'warning',
            'error': 'danger',
            'system': 'secondary'
        };
        return colors[type] || 'primary';
    },
    
    // Mark notification as read
    async markNotificationAsRead(notificationId) {
        try {
            const response = await fetch('/api/notifications.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    action: 'mark_read',
                    notification_id: notificationId
                })
            });
            
            const data = await response.json();
            if (data.success) {
                const notification = this.notifications.find(n => n.id === notificationId);
                if (notification) {
                    notification.is_read = true;
                    this.updateUI();
                }
            }
        } catch (error) {
            console.error('Failed to mark notification as read:', error);
        }
    },
    
    // Start real-time updates
    startRealTimeUpdates() {
        // Update notifications every 30 seconds
        setInterval(() => {
            this.fetchNotifications().then(notifications => {
                this.notifications = notifications;
                this.updateNotificationBadge();
                this.updateNotificationsList();
            });
        }, 30000);
        
        // Update wallet balance every 60 seconds
        setInterval(() => {
            this.fetchWalletBalance().then(wallet => {
                this.walletBalance = wallet.balance;
                this.updateWalletBalance();
            });
        }, 60000);
        
        // Update GPS location every 10 seconds (if applicable)
        if (this.user && (this.user.role === 'transporter' || this.user.role === 'farmer')) {
            setInterval(() => {
                this.updateLocation();
            }, 10000);
        }
    },
    
    // Initialize maps
    initializeMaps() {
        const mapElement = document.getElementById('map');
        if (mapElement && typeof L !== 'undefined') {
            this.map = L.map('map').setView([-1.2921, 36.8219], 13); // Nairobi coordinates
            
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors'
            }).addTo(this.map);
            
            // Add current location marker
            if (this.currentLocation) {
                this.addMarker(this.currentLocation.lat, this.currentLocation.lng, 'Your Location');
            }
        }
    },
    
    // Watch position
    watchPosition() {
        if (!navigator.geolocation) return;
        
        navigator.geolocation.watchPosition(
            position => {
                this.currentLocation = {
                    lat: position.coords.latitude,
                    lng: position.coords.longitude,
                    accuracy: position.coords.accuracy
                };
                
                this.updateLocationMarker();
                this.sendLocationToServer();
            },
            error => {
                console.error('Geolocation error:', error);
            },
            {
                enableHighAccuracy: true,
                timeout: 10000,
                maximumAge: 300000 // 5 minutes
            }
        );
    },
    
    // Update location
    updateLocation() {
        if (!this.currentLocation) return;
        
        navigator.geolocation.getCurrentPosition(
            position => {
                this.currentLocation = {
                    lat: position.coords.latitude,
                    lng: position.coords.longitude,
                    accuracy: position.coords.accuracy
                };
                
                this.updateLocationMarker();
                this.sendLocationToServer();
            },
            error => {
                console.error('Location update failed:', error);
            }
        );
    },
    
    // Update location marker
    updateLocationMarker() {
        if (!this.map || !this.currentLocation) return;
        
        // Remove existing marker
        if (this.locationMarker) {
            this.map.removeLayer(this.locationMarker);
        }
        
        // Add new marker
        this.locationMarker = L.marker([this.currentLocation.lat, this.currentLocation.lng])
            .addTo(this.map)
            .bindPopup('Your Current Location');
        
        // Center map on location
        this.map.setView([this.currentLocation.lat, this.currentLocation.lng], 15);
    },
    
    // Send location to server
    sendLocationToServer() {
        if (!this.currentLocation || !this.user) return;
        
        const action = this.storeOfflineAction({
            url: '/api/location.php',
            method: 'POST',
            body: JSON.stringify({
                latitude: this.currentLocation.lat,
                longitude: this.currentLocation.lng,
                accuracy: this.currentLocation.accuracy
            })
        });
        
        if (this.isOnline) {
            this.syncOfflineActions();
        }
    },
    
    // Add marker to map
    addMarker(lat, lng, popupText) {
        if (!this.map) return;
        
        const marker = L.marker([lat, lng])
            .addTo(this.map)
            .bindPopup(popupText);
        
        this.markers.push(marker);
        return marker;
    },
    
    // Handle AJAX form submission
    handleAjaxForm(form) {
        const formData = new FormData(form);
        const submitButton = form.querySelector('button[type="submit"]');
        
        // Set loading state
        this.setLoading(submitButton, true);
        
        fetch(form.action, {
            method: form.method,
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            this.setLoading(submitButton, false);
            
            if (data.success) {
                this.showNotification(data.message || 'Operation successful', 'success');
                
                // Reset form if specified
                if (data.reset_form) {
                    form.reset();
                }
                
                // Redirect if specified
                if (data.redirect) {
                    setTimeout(() => {
                        window.location.href = data.redirect;
                    }, 1500);
                }
                
                // Update UI if needed
                if (data.update_ui) {
                    this.loadUserData();
                }
            } else {
                this.showNotification(data.error || 'Operation failed', 'danger');
            }
        })
        .catch(error => {
            this.setLoading(submitButton, false);
            console.error('Form submission error:', error);
            this.showNotification('Network error. Please try again.', 'danger');
        });
    },
    
    // Handle AJAX link
    handleAjaxLink(link) {
        fetch(link.href, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                this.showNotification(data.message || 'Operation successful', 'success');
                
                if (data.redirect) {
                    window.location.href = data.redirect;
                }
                
                if (data.update_ui) {
                    this.loadUserData();
                }
            } else {
                this.showNotification(data.error || 'Operation failed', 'danger');
            }
        })
        .catch(error => {
            console.error('AJAX link error:', error);
            this.showNotification('Network error. Please try again.', 'danger');
        });
    },
    
    // Handle file upload
    handleFileUpload(input) {
        const files = input.files;
        if (files.length === 0) return;
        
        const file = files[0];
        const maxSize = 5 * 1024 * 1024; // 5MB
        const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
        
        if (file.size > maxSize) {
            this.showNotification('File size must be less than 5MB', 'danger');
            input.value = '';
            return;
        }
        
        if (!allowedTypes.includes(file.type)) {
            this.showNotification('Invalid file type', 'danger');
            input.value = '';
            return;
        }
        
        // Show preview for images
        if (file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = (e) => {
                const preview = document.getElementById(input.id + '-preview');
                if (preview) {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                }
            };
            reader.readAsDataURL(file);
        }
    },
    
    // Store offline action
    storeOfflineAction(action) {
        const offlineActions = JSON.parse(localStorage.getItem('offlineActions') || '[]');
        offlineActions.push({
            ...action,
            timestamp: new Date().toISOString()
        });
        localStorage.setItem('offlineActions', JSON.stringify(offlineActions));
        return offlineActions.length - 1;
    },
    
    // Sync offline actions
    async syncOfflineActions() {
        const offlineActions = JSON.parse(localStorage.getItem('offlineActions') || '[]');
        
        for (const action of offlineActions) {
            try {
                const response = await fetch(action.url, {
                    method: action.method,
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: action.body
                });
                
                if (response.ok) {
                    // Remove synced action
                    const index = offlineActions.indexOf(action);
                    offlineActions.splice(index, 1);
                }
            } catch (error) {
                console.error('Error syncing offline action:', error);
            }
        }
        
        localStorage.setItem('offlineActions', JSON.stringify(offlineActions));
        
        if (offlineActions.length === 0) {
            this.showNotification('All offline actions synced successfully!', 'success');
        }
    },
    
    // Setup service worker
    setupServiceWorker() {
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('/assets/js/sw.js')
                .then(registration => {
                    console.log('SW registered: ', registration);
                })
                .catch(registrationError => {
                    console.log('SW registration failed: ', registrationError);
                });
        }
    },
    
    // Show notification
    showNotification(message, type = 'info') {
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed top-0 end-0 m-3`;
        alertDiv.style.zIndex = '9999';
        alertDiv.innerHTML = `
            <i class="fas fa-${type === 'success' ? 'check-circle' : (type === 'danger' ? 'exclamation-triangle' : 'info-circle')} me-2"></i>
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        document.body.appendChild(alertDiv);
        
        setTimeout(() => {
            if (alertDiv.parentNode) {
                alertDiv.parentNode.removeChild(alertDiv);
            }
        }, 5000);
    },
    
    // Show offline indicator
    showOfflineIndicator() {
        const indicator = document.getElementById('offlineIndicator');
        if (indicator) {
            indicator.classList.add('show');
        }
    },
    
    // Hide offline indicator
    hideOfflineIndicator() {
        const indicator = document.getElementById('offlineIndicator');
        if (indicator) {
            indicator.classList.remove('show');
        }
    },
    
    // Set loading state
    setLoading(button, loading) {
        if (loading) {
            button.disabled = true;
            button.setAttribute('data-original-text', button.innerHTML);
            button.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Loading...';
        } else {
            button.disabled = false;
            button.innerHTML = button.getAttribute('data-original-text') || 'Submit';
        }
    },
    
    // Format currency
    formatCurrency(amount) {
        return 'KES ' + parseFloat(amount).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    },
    
    // Time ago
    timeAgo(dateString) {
        const date = new Date(dateString);
        const now = new Date();
        const diff = Math.floor((now - date) / 1000);
        
        if (diff < 60) return 'just now';
        if (diff < 3600) return Math.floor(diff / 60) + ' minutes ago';
        if (diff < 86400) return Math.floor(diff / 3600) + ' hours ago';
        if (diff < 2592000) return Math.floor(diff / 86400) + ' days ago';
        
        return date.toLocaleDateString();
    },
    
    // Redirect to login
    redirectToLogin() {
        window.location.href = '/public/login.php';
    }
};

// Initialize application when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    App.init();
});

// Global functions for inline event handlers
window.App = App;

// Utility functions
function confirmAction(message, callback) {
    if (confirm(message)) {
        callback();
    }
}

function printElement(elementId) {
    const element = document.getElementById(elementId);
    if (element) {
        const printWindow = window.open('', '_blank');
        printWindow.document.write(`
            <html>
                <head>
                    <title>Print</title>
                    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
                    <link href="/assets/css/style.css" rel="stylesheet">
                    <style>
                        body { padding: 20px; }
                        .no-print { display: none !important; }
                    </style>
                </head>
                <body>
                    ${element.innerHTML}
                </body>
            </html>
        `);
        printWindow.document.close();
        printWindow.print();
    }
}

function exportToCSV(data, filename) {
    const csv = data.map(row => row.join(',')).join('\n');
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.setAttribute('hidden', '');
    a.setAttribute('href', url);
    a.setAttribute('download', filename);
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
}

// Mobile Money Integration
const MobileMoney = {
    providers: {
        mpesa: {
            name: 'M-Pesa',
            color: '#4CAF50',
            icon: 'fas fa-mobile-alt'
        },
        airtel: {
            name: 'Airtel Money',
            color: '#FF5722',
            icon: 'fas fa-mobile-alt'
        },
        tkash: {
            name: 'T-Kash',
            color: '#2196F3',
            icon: 'fas fa-mobile-alt'
        },
        equitel: {
            name: 'Equitel',
            color: '#9C27B0',
            icon: 'fas fa-mobile-alt'
        }
    },
    
    async initiatePayment(provider, phoneNumber, amount, reference) {
        try {
            const response = await fetch('/api/mobile-money.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    provider,
                    phone_number: phoneNumber,
                    amount,
                    reference
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                App.showNotification('Payment initiated. Please complete on your phone.', 'info');
                this.checkPaymentStatus(data.transaction_id);
            } else {
                App.showNotification(data.error || 'Payment failed', 'danger');
            }
            
            return data;
        } catch (error) {
            console.error('Mobile money payment error:', error);
            App.showNotification('Payment error. Please try again.', 'danger');
            return { success: false, error: 'Network error' };
        }
    },
    
    async checkPaymentStatus(transactionId) {
        const maxAttempts = 30; // 5 minutes max
        let attempts = 0;
        
        const checkStatus = async () => {
            try {
                const response = await fetch(`/api/mobile-money.php?transaction_id=${transactionId}`, {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                
                const data = await response.json();
                
                if (data.status === 'completed') {
                    App.showNotification('Payment completed successfully!', 'success');
                    App.loadUserData(); // Update wallet balance
                } else if (data.status === 'failed') {
                    App.showNotification('Payment failed. Please try again.', 'danger');
                } else if (attempts < maxAttempts) {
                    attempts++;
                    setTimeout(checkStatus, 10000); // Check again in 10 seconds
                }
            } catch (error) {
                console.error('Payment status check error:', error);
            }
        };
        
        setTimeout(checkStatus, 5000); // Start checking after 5 seconds
    }
};

window.MobileMoney = MobileMoney;

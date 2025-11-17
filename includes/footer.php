</main>

    <!-- Footer -->
    <?php if (isLoggedIn()): ?>
    <footer class="bg-light text-center text-muted py-3 mt-5 no-print">
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-6">
                    <p class="mb-0">&copy; <?= date('Y') ?> <?= APP_NAME ?>. All rights reserved.</p>
                </div>
                <div class="col-md-6">
                    <div class="d-flex justify-content-end gap-3">
                        <a href="#" class="text-decoration-none">Help</a>
                        <a href="#" class="text-decoration-none">Privacy</a>
                        <a href="#" class="text-decoration-none">Terms</a>
                        <a href="#" class="text-decoration-none">Contact</a>
                    </div>
                </div>
            </div>
        </div>
    </footer>
    <?php endif; ?>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom JavaScript -->
    <script src="<?= BASE_URL ?>/assets/js/app.js"></script>
    
    <!-- Real-time Updates -->
    <script>
        // CSRF Token for AJAX
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        
        // AJAX Setup
        const headers = {
            'X-CSRF-Token': csrfToken,
            'Content-Type': 'application/json'
        };
        
        // Notification System
        function showNotification(message, type = 'info') {
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
                alertDiv.remove();
            }, 5000);
        }
        
        // Real-time notifications
        async function fetchNotifications() {
            try {
                const response = await fetch('<?= BASE_URL ?>/api/notifications.php', {
                    headers: headers
                });
                const data = await response.json();
                
                if (data.success) {
                    updateNotificationBadge(data.unread_count);
                    updateNotificationsList(data.notifications);
                }
            } catch (error) {
                console.error('Error fetching notifications:', error);
            }
        }
        
        function updateNotificationBadge(count) {
            const badge = document.getElementById('notificationCount');
            if (badge) {
                badge.textContent = count > 0 : count : '';
                badge.style.display = count > 0 ? 'inline' : 'none';
            }
        }
        
        function updateNotificationsList(notifications) {
            const list = document.getElementById('notificationsList');
            if (!list) return;
            
            if (notifications.length === 0) {
                list.innerHTML = `
                    <div class="dropdown-item text-muted text-center">
                        <small>No new notifications</small>
                    </div>
                `;
                return;
            }
            
            list.innerHTML = notifications.map(notif => `
                <a href="#" class="dropdown-item ${notif.is_read ? '' : 'bg-light'}" onclick="markAsRead(${notif.id})">
                    <div class="d-flex">
                        <div class="flex-shrink-0">
                            <i class="fas fa-${getNotificationIcon(notif.type)} text-${getNotificationColor(notif.type)}"></i>
                        </div>
                        <div class="flex-grow-1 ms-2">
                            <h6 class="mb-1">${notif.title}</h6>
                            <p class="mb-0 small text-muted">${notif.message}</p>
                            <small class="text-muted">${timeAgo(notif.created_at)}</small>
                        </div>
                    </div>
                </a>
            `).join('');
        }
        
        function getNotificationIcon(type) {
            const icons = {
                'info': 'info-circle',
                'success': 'check-circle',
                'warning': 'exclamation-triangle',
                'error': 'times-circle',
                'system': 'cog'
            };
            return icons[type] || 'info-circle';
        }
        
        function getNotificationColor(type) {
            const colors = {
                'info': 'primary',
                'success': 'success',
                'warning': 'warning',
                'error': 'danger',
                'system': 'secondary'
            };
            return colors[type] || 'primary';
        }
        
        async function markAsRead(notificationId) {
            try {
                const response = await fetch('<?= BASE_URL ?>/api/notifications.php', {
                    method: 'POST',
                    headers: headers,
                    body: JSON.stringify({
                        action: 'mark_read',
                        notification_id: notificationId
                    })
                });
                
                const data = await response.json();
                if (data.success) {
                    fetchNotifications();
                }
            } catch (error) {
                console.error('Error marking notification as read:', error);
            }
        }
        
        // Wallet balance update
        async function fetchWalletBalance() {
            try {
                const response = await fetch('<?= BASE_URL ?>/api/wallet.php', {
                    headers: headers
                });
                const data = await response.json();
                
                if (data.success) {
                    const balanceElement = document.getElementById('walletBalance');
                    if (balanceElement) {
                        balanceElement.textContent = formatCurrency(data.balance);
                    }
                }
            } catch (error) {
                console.error('Error fetching wallet balance:', error);
            }
        }
        
        function formatCurrency(amount) {
            return 'KES ' + parseFloat(amount).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        }
        
        function timeAgo(dateString) {
            const date = new Date(dateString);
            const now = new Date();
            const diff = Math.floor((now - date) / 1000);
            
            if (diff < 60) return 'just now';
            if (diff < 3600) return Math.floor(diff / 60) + ' minutes ago';
            if (diff < 86400) return Math.floor(diff / 3600) + ' hours ago';
            if (diff < 2592000) return Math.floor(diff / 86400) + ' days ago';
            
            return date.toLocaleDateString();
        }
        
        // Initialize real-time updates
        document.addEventListener('DOMContentLoaded', function() {
            if (document.getElementById('notificationCount')) {
                fetchNotifications();
                fetchWalletBalance();
                
                // Update every 30 seconds
                setInterval(fetchNotifications, 30000);
                setInterval(fetchWalletBalance, 60000);
            }
        });
        
        // Form validation
        function validateForm(form) {
            const inputs = form.querySelectorAll('input[required], select[required], textarea[required]');
            let isValid = true;
            
            inputs.forEach(input => {
                if (!input.value.trim()) {
                    input.classList.add('is-invalid');
                    isValid = false;
                } else {
                    input.classList.remove('is-invalid');
                }
            });
            
            return isValid;
        }
        
        // Loading state
        function setLoading(button, loading = true) {
            if (loading) {
                button.disabled = true;
                button.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Loading...';
            } else {
                button.disabled = false;
                button.innerHTML = button.getAttribute('data-original-text') || 'Submit';
            }
        }
        
        // Confirm dialog
        function confirmAction(message, callback) {
            if (confirm(message)) {
                callback();
            }
        }
        
        // Print function
        function printElement(elementId) {
            const element = document.getElementById(elementId);
            if (element) {
                const printWindow = window.open('', '_blank');
                printWindow.document.write(`
                    <html>
                        <head>
                            <title>Print</title>
                            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
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
        
        // Export to CSV
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
        
        // Geolocation
        function getCurrentLocation() {
            return new Promise((resolve, reject) => {
                if (!navigator.geolocation) {
                    reject(new Error('Geolocation is not supported by this browser'));
                    return;
                }
                
                navigator.geolocation.getCurrentPosition(
                    position => {
                        resolve({
                            lat: position.coords.latitude,
                            lng: position.coords.longitude,
                            accuracy: position.coords.accuracy
                        });
                    },
                    error => {
                        reject(error);
                    },
                    {
                        enableHighAccuracy: true,
                        timeout: 10000,
                        maximumAge: 300000 // 5 minutes
                    }
                );
            });
        }
        
        // Service Worker for PWA (optional)
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('<?= BASE_URL ?>/assets/js/sw.js')
                .then(registration => {
                    console.log('SW registered: ', registration);
                })
                .catch(registrationError => {
                    console.log('SW registration failed: ', registrationError);
                });
        }
    </script>
    
    <!-- Offline Mode Support -->
    <script>
        // Check online status
        function updateOnlineStatus() {
            const isOnline = navigator.onLine;
            if (!isOnline) {
                showNotification('You are currently offline. Some features may not be available.', 'warning');
            }
        }
        
        window.addEventListener('online', updateOnlineStatus);
        window.addEventListener('offline', updateOnlineStatus);
        updateOnlineStatus();
        
        // Store offline actions
        const offlineActions = JSON.parse(localStorage.getItem('offlineActions') || '[]');
        
        function storeOfflineAction(action) {
            offlineActions.push({
                ...action,
                timestamp: new Date().toISOString()
            });
            localStorage.setItem('offlineActions', JSON.stringify(offlineActions));
        }
        
        // Sync offline actions when back online
        window.addEventListener('online', function() {
            if (offlineActions.length > 0) {
                syncOfflineActions();
            }
        });
        
        async function syncOfflineActions() {
            for (const action of offlineActions) {
                try {
                    await fetch(action.url, {
                        method: action.method,
                        headers: headers,
                        body: action.body
                    });
                } catch (error) {
                    console.error('Error syncing offline action:', error);
                }
            }
            
            // Clear synced actions
            localStorage.removeItem('offlineActions');
            showNotification('Offline actions synced successfully!', 'success');
        }
    </script>
</body>
</html>

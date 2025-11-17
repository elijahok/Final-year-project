<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect(BASE_URL . '/public/login.php');
}

$currentUser = getCurrentUser();
$pageTitle = 'Notifications - ' . APP_NAME;
include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3 mb-0">
                    <i class="fas fa-bell me-2"></i>
                    Notifications
                </h1>
                <div class="btn-group">
                    <button type="button" class="btn btn-outline-primary" id="markAllReadBtn">
                        <i class="fas fa-check-double me-1"></i>
                        Mark All Read
                    </button>
                    <button type="button" class="btn btn-outline-secondary" id="clearNotificationsBtn">
                        <i class="fas fa-trash me-1"></i>
                        Clear All
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Notification Settings -->
    <div class="card mb-4">
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-cog me-2"></i>
                    Notification Settings
                </h5>
                <button type="button" class="btn btn-sm btn-outline-primary" id="editSettingsBtn">
                    <i class="fas fa-edit"></i>
                </button>
            </div>
        </div>
        <div class="card-body" id="settingsView">
            <div class="row">
                <div class="col-md-4">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span>Email Notifications</span>
                        <span class="badge bg-success" id="emailBadge">Enabled</span>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span>SMS Notifications</span>
                        <span class="badge bg-secondary" id="smsBadge">Disabled</span>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span>Push Notifications</span>
                        <span class="badge bg-success" id="pushBadge">Enabled</span>
                    </div>
                </div>
            </div>
            <hr>
            <div class="row">
                <div class="col-md-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span>Tender Updates</span>
                        <span class="badge bg-success" id="tenderBadge">Enabled</span>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span>Bid Notifications</span>
                        <span class="badge bg-success" id="bidBadge">Enabled</span>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span>Transport Updates</span>
                        <span class="badge bg-success" id="transportBadge">Enabled</span>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span>Payment Alerts</span>
                        <span class="badge bg-success" id="paymentBadge">Enabled</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="card-body d-none" id="settingsEdit">
            <form id="settingsForm">
                <div class="row">
                    <div class="col-md-6">
                        <h6 class="mb-3">Delivery Methods</h6>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" id="emailNotifications" name="email_notifications">
                            <label class="form-check-label" for="emailNotifications">
                                Email Notifications
                            </label>
                        </div>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" id="smsNotifications" name="sms_notifications">
                            <label class="form-check-label" for="smsNotifications">
                                SMS Notifications
                            </label>
                        </div>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" id="pushNotifications" name="push_notifications">
                            <label class="form-check-label" for="pushNotifications">
                                Push Notifications
                            </label>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <h6 class="mb-3">Notification Types</h6>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" id="tenderNotifications" name="tender_notifications">
                            <label class="form-check-label" for="tenderNotifications">
                                Tender Updates
                            </label>
                        </div>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" id="bidNotifications" name="bid_notifications">
                            <label class="form-check-label" for="bidNotifications">
                                Bid Notifications
                            </label>
                        </div>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" id="transportNotifications" name="transport_notifications">
                            <label class="form-check-label" for="transportNotifications">
                                Transport Updates
                            </label>
                        </div>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" id="paymentNotifications" name="payment_notifications">
                            <label class="form-check-label" for="paymentNotifications">
                                Payment Alerts
                            </label>
                        </div>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" id="qualityNotifications" name="quality_notifications">
                            <label class="form-check-label" for="qualityNotifications">
                                Quality Reports
                            </label>
                        </div>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" id="systemNotifications" name="system_notifications">
                            <label class="form-check-label" for="systemNotifications">
                                System Updates
                            </label>
                        </div>
                    </div>
                </div>
                <div class="mt-3">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i>
                        Save Settings
                    </button>
                    <button type="button" class="btn btn-outline-secondary" id="cancelSettingsBtn">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form id="filterForm" class="row g-3">
                <div class="col-md-3">
                    <label for="statusFilter" class="form-label">Status</label>
                    <select class="form-select" id="statusFilter" name="status">
                        <option value="">All Status</option>
                        <option value="unread">Unread</option>
                        <option value="read">Read</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="typeFilter" class="form-label">Type</label>
                    <select class="form-select" id="typeFilter" name="type">
                        <option value="">All Types</option>
                        <option value="tender">Tender</option>
                        <option value="bid">Bid</option>
                        <option value="transport">Transport</option>
                        <option value="payment">Payment</option>
                        <option value="quality">Quality</option>
                        <option value="system">System</option>
                        <option value="alert">Alert</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="searchFilter" class="form-label">Search</label>
                    <input type="text" class="form-control" id="searchFilter" name="search" placeholder="Search notifications...">
                </div>
                <div class="col-md-2">
                    <label class="form-label">&nbsp;</label><br>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search me-1"></i>
                        Filter
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Unread Notifications Summary -->
    <div class="alert alert-info mb-4" id="unreadSummary" style="display: none;">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <i class="fas fa-info-circle me-2"></i>
                <span id="unreadCount">0</span> unread notifications
            </div>
            <button type="button" class="btn btn-sm btn-outline-primary" id="markUnreadReadBtn">
                Mark All as Read
            </button>
        </div>
    </div>

    <!-- Notifications List -->
    <div class="card">
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-list me-2"></i>
                    Recent Notifications
                </h5>
                <span class="badge bg-primary" id="totalCount">0</span>
            </div>
        </div>
        <div class="card-body">
            <div id="notificationsList">
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">Loading notifications...</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Notification Details Modal -->
<div class="modal fade" id="notificationModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="notificationModalTitle">Notification Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="notificationModalBody">
                <!-- Content will be loaded dynamically -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="viewActionBtn">View Details</button>
            </div>
        </div>
    </div>
</div>

<script>
let currentPage = 1;
let isLoading = false;
let currentNotificationId = null;

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    loadNotifications();
    loadNotificationCount();
    loadSettings();
    
    // Event listeners
    document.getElementById('filterForm').addEventListener('submit', function(e) {
        e.preventDefault();
        currentPage = 1;
        loadNotifications();
    });
    
    document.getElementById('markAllReadBtn').addEventListener('click', markAllAsRead);
    document.getElementById('clearNotificationsBtn').addEventListener('click', clearNotifications);
    document.getElementById('markUnreadReadBtn').addEventListener('click', markUnreadAsRead);
    
    // Settings
    document.getElementById('editSettingsBtn').addEventListener('click', showSettingsEdit);
    document.getElementById('cancelSettingsBtn').addEventListener('click', hideSettingsEdit);
    document.getElementById('settingsForm').addEventListener('submit', saveSettings);
    
    // Auto-refresh notifications every 30 seconds
    setInterval(loadNotificationCount, 30000);
});

// Load notifications
function loadNotifications() {
    if (isLoading) return;
    isLoading = true;
    
    const formData = new FormData(document.getElementById('filterForm'));
    const params = new URLSearchParams(formData);
    params.set('page', currentPage);
    params.set('limit', 20);
    
    fetch(`<?= BASE_URL ?>/api/notifications.php?action=list&${params}`)
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            displayNotifications(data.notifications, data.pagination);
        } else {
            showError(data.error);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showError('Failed to load notifications');
    })
    .finally(() => {
        isLoading = false;
    });
}

// Load notification count
function loadNotificationCount() {
    fetch('<?= BASE_URL ?>/api/notifications.php?action=count')
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            updateNotificationCounts(data.counts);
        }
    })
    .catch(error => {
        console.error('Error:', error);
    });
}

// Load settings
function loadSettings() {
    fetch('<?= BASE_URL ?>/api/notifications.php?action=settings')
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            displaySettings(data.settings);
            populateSettingsForm(data.settings);
        }
    })
    .catch(error => {
        console.error('Error:', error);
    });
}

// Display notifications
function displayNotifications(notifications, pagination) {
    const container = document.getElementById('notificationsList');
    document.getElementById('totalCount').textContent = pagination.total_records;
    
    if (notifications.length === 0) {
        container.innerHTML = `
            <div class="text-center py-4">
                <i class="fas fa-bell-slash fa-3x text-muted mb-3"></i>
                <p class="text-muted">No notifications found</p>
            </div>
        `;
        return;
    }
    
    let html = '';
    notifications.forEach(notification => {
        const unreadClass = !notification.is_read ? 'bg-light' : '';
        const unreadIcon = !notification.is_read ? '<i class="fas fa-circle text-primary fa-xs"></i>' : '';
        
        html += `
            <div class="card mb-2 ${unreadClass} notification-item" data-id="${notification.id}">
                <div class="card-body py-3">
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="flex-grow-1" onclick="viewNotification(${notification.id})" style="cursor: pointer;">
                            <div class="d-flex align-items-center mb-1">
                                ${unreadIcon}
                                <span class="fw-bold me-2">${notification.title}</span>
                                ${notification.type_badge}
                                ${notification.is_read_badge}
                            </div>
                            <div class="text-muted small">${notification.message}</div>
                            <div class="text-muted small mt-1">
                                <i class="fas fa-clock me-1"></i>
                                ${notification.time_ago}
                                ${notification.sender_name ? ` • From: ${notification.sender_name} ${notification.sender_role_badge}` : ''}
                            </div>
                        </div>
                        <div class="btn-group btn-group-sm">
                            ${!notification.is_read ? `
                                <button type="button" class="btn btn-outline-primary" onclick="markAsRead(${notification.id}); event.stopPropagation();" title="Mark as read">
                                    <i class="fas fa-check"></i>
                                </button>
                            ` : `
                                <button type="button" class="btn btn-outline-secondary" onclick="markAsUnread(${notification.id}); event.stopPropagation();" title="Mark as unread">
                                    <i class="fas fa-envelope"></i>
                                </button>
                            `}
                            <button type="button" class="btn btn-outline-danger" onclick="deleteNotification(${notification.id}); event.stopPropagation();" title="Delete">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
    });
    
    // Add pagination
    if (pagination.total_pages > 1) {
        html += '<nav><ul class="pagination justify-content-center">';
        
        if (pagination.has_prev) {
            html += `<li class="page-item"><a class="page-link" href="#" onclick="changePage(${pagination.current_page - 1})">Previous</a></li>`;
        }
        
        for (let i = 1; i <= pagination.total_pages; i++) {
            const active = i === pagination.current_page ? 'active' : '';
            html += `<li class="page-item ${active}"><a class="page-link" href="#" onclick="changePage(${i})">${i}</a></li>`;
        }
        
        if (pagination.has_next) {
            html += `<li class="page-item"><a class="page-link" href="#" onclick="changePage(${pagination.current_page + 1})">Next</a></li>`;
        }
        
        html += '</ul></nav>';
    }
    
    container.innerHTML = html;
}

// Update notification counts
function updateNotificationCounts(counts) {
    const unreadCount = counts.total || 0;
    document.getElementById('unreadCount').textContent = unreadCount;
    
    const summary = document.getElementById('unreadSummary');
    if (unreadCount > 0) {
        summary.style.display = 'block';
    } else {
        summary.style.display = 'none';
    }
}

// Display settings
function displaySettings(settings) {
    const badges = {
        'emailNotifications': 'emailBadge',
        'smsNotifications': 'smsBadge',
        'pushNotifications': 'pushBadge',
        'tenderNotifications': 'tenderBadge',
        'bidNotifications': 'bidBadge',
        'transportNotifications': 'transportBadge',
        'paymentNotifications': 'paymentBadge'
    };
    
    Object.keys(badges).forEach(key => {
        const badge = document.getElementById(badges[key]);
        if (badge && settings[key] !== undefined) {
            badge.textContent = settings[key] ? 'Enabled' : 'Disabled';
            badge.className = settings[key] ? 'badge bg-success' : 'badge bg-secondary';
        }
    });
}

// Populate settings form
function populateSettingsForm(settings) {
    const fields = [
        'email_notifications', 'sms_notifications', 'push_notifications',
        'tender_notifications', 'bid_notifications', 'transport_notifications',
        'payment_notifications', 'quality_notifications', 'system_notifications'
    ];
    
    fields.forEach(field => {
        const checkbox = document.getElementById(field.replace('_', ''));
        if (checkbox && settings[field] !== undefined) {
            checkbox.checked = settings[field] === 1;
        }
    });
}

// Show settings edit
function showSettingsEdit() {
    document.getElementById('settingsView').classList.add('d-none');
    document.getElementById('settingsEdit').classList.remove('d-none');
}

// Hide settings edit
function hideSettingsEdit() {
    document.getElementById('settingsView').classList.remove('d-none');
    document.getElementById('settingsEdit').classList.add('d-none');
}

// Save settings
function saveSettings(e) {
    e.preventDefault();
    
    const formData = new FormData(document.getElementById('settingsForm'));
    formData.append('action', 'settings');
    formData.append('csrf_token', '<?= generateCSRFToken() ?>');
    
    fetch('<?= BASE_URL ?>/api/notifications.php', {
        method: 'PUT',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Notification settings updated successfully!');
            hideSettingsEdit();
            loadSettings();
        } else {
            alert(data.error || 'Failed to update settings');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to update settings');
    });
}

// View notification
function viewNotification(notificationId) {
    currentNotificationId = notificationId;
    
    // Get notification details
    const notificationElement = document.querySelector(`.notification-item[data-id="${notificationId}"]`);
    if (!notificationElement) return;
    
    // Extract data from the element
    const title = notificationElement.querySelector('.fw-bold').textContent.trim();
    const message = notificationElement.querySelector('.text-muted.small').textContent.trim();
    const type = notificationElement.querySelector('.badge').textContent.trim();
    
    // Show modal
    document.getElementById('notificationModalTitle').textContent = title;
    document.getElementById('notificationModalBody').innerHTML = `
        <div class="mb-3">
            <span class="badge bg-primary">${type}</span>
        </div>
        <p>${message}</p>
    `;
    
    const modal = new bootstrap.Modal(document.getElementById('notificationModal'));
    modal.show();
    
    // Mark as read if unread
    if (notificationElement.querySelector('.fa-circle')) {
        markAsRead(notificationId);
    }
}

// Mark as read
function markAsRead(notificationId) {
    fetch(`<?= BASE_URL ?>/api/notifications.php?action=read&id=${notificationId}`)
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            loadNotifications();
            loadNotificationCount();
        }
    })
    .catch(error => {
        console.error('Error:', error);
    });
}

// Mark as unread
function markAsUnread(notificationId) {
    fetch(`<?= BASE_URL ?>/api/notifications.php?action=unread&id=${notificationId}`)
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            loadNotifications();
            loadNotificationCount();
        }
    })
    .catch(error => {
        console.error('Error:', error);
    });
}

// Mark all as read
function markAllAsRead() {
    if (!confirm('Mark all notifications as read?')) return;
    
    const formData = new FormData();
    formData.append('csrf_token', '<?= generateCSRFToken() ?>');
    
    fetch('<?= BASE_URL ?>/api/notifications.php?action=all', {
        method: 'PUT',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            loadNotifications();
            loadNotificationCount();
        } else {
            alert(data.error || 'Failed to mark notifications as read');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to mark notifications as read');
    });
}

// Mark unread as read
function markUnreadAsRead() {
    const formData = new FormData();
    formData.append('csrf_token', '<?= generateCSRFToken() ?>');
    
    fetch('<?= BASE_URL ?>/api/notifications.php?action=all', {
        method: 'PUT',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            loadNotifications();
            loadNotificationCount();
        } else {
            alert(data.error || 'Failed to mark notifications as read');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to mark notifications as read');
    });
}

// Delete notification
function deleteNotification(notificationId) {
    if (!confirm('Delete this notification?')) return;
    
    fetch(`<?= BASE_URL ?>/api/notifications.php?action=delete&id=${notificationId}`)
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            loadNotifications();
            loadNotificationCount();
        } else {
            alert(data.error || 'Failed to delete notification');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to delete notification');
    });
}

// Clear notifications
function clearNotifications() {
    if (!confirm('Delete all notifications? This action cannot be undone.')) return;
    
    const formData = new FormData();
    formData.append('csrf_token', '<?= generateCSRFToken() ?>');
    
    fetch('<?= BASE_URL ?>/api/notifications.php?action=clear', {
        method: 'DELETE',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            loadNotifications();
            loadNotificationCount();
        } else {
            alert(data.error || 'Failed to clear notifications');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to clear notifications');
    });
}

// Change page
function changePage(page) {
    currentPage = page;
    loadNotifications();
}

// Show error
function showError(message) {
    const container = document.getElementById('notificationsList');
    container.innerHTML = `
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-triangle me-2"></i>
            ${message}
        </div>
    `;
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>

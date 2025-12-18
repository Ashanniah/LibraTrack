/**
 * Centralized Notification System
 * Uses the new notification API endpoints
 */

(function() {
  'use strict';

  let notificationUpdateInterval = null;

  /**
   * Update unread notification count badge
   */
  async function updateNotificationBadge() {
    const badge = document.getElementById('notificationBadge');
    if (!badge) return;

    try {
      const res = await fetch('/api/notifications/unread-count', {credentials: 'include'});
      const data = await res.json().catch(() => ({unread: 0}));
      
      const count = data.unread || 0;
      if (count > 0) {
        badge.textContent = count > 99 ? '99+' : count;
        badge.classList.remove('d-none');
      } else {
        badge.classList.add('d-none');
      }
    } catch (err) {
      console.error('Error updating notification badge:', err);
      badge.classList.add('d-none');
    }
  }

  /**
   * Load and display notifications in dropdown
   * Shows latest 5-10 notifications, unread first, newest on top
   */
  async function loadNotificationDropdown() {
    const list = document.getElementById('notificationList');
    if (!list) return;

    try {
      // Get latest 10 notifications (unread first, then newest)
      const res = await fetch('/api/notifications/list?status=all&limit=10', {credentials: 'include'});
      const data = await res.json().catch(() => ({items: []}));
      
      const notifications = data.items || [];
      
      // Filter to show unread first, then limit to 10 total
      const unreadNotifications = notifications.filter(n => !n.is_read);
      const readNotifications = notifications.filter(n => n.is_read);
      const displayNotifications = [...unreadNotifications, ...readNotifications].slice(0, 10);
      
      if (displayNotifications.length === 0) {
        list.innerHTML = `
          <div class="px-3 py-4 text-center">
            <div class="text-muted small mb-1">No new notifications</div>
            <div class="text-muted" style="font-size: 0.7rem;">You're all caught up.</div>
          </div>
        `;
        return;
      }

      // Build notification items HTML
      const itemsHtml = displayNotifications.map(notif => {
        const timeAgo = getTimeAgo(notif.created_at);
        const isUnread = !notif.is_read;
        const unreadClass = isUnread ? 'fw-bold' : '';
        const unreadDot = isUnread ? '<span class="badge bg-primary rounded-pill" style="width: 8px; height: 8px; padding: 0; margin-right: 8px; display: inline-block;"></span>' : '';
        
        return `
          <li class="px-3 py-2 border-bottom notification-item" data-id="${notif.id}" style="cursor: pointer; transition: background-color 0.2s;" onmouseover="this.style.backgroundColor='#f8f9fa'" onmouseout="this.style.backgroundColor=''">
            <div class="d-flex align-items-start">
              <div class="flex-grow-1">
                <div class="small ${unreadClass}" style="line-height: 1.4;">
                  ${unreadDot}${escapeHtml(notif.title)}
                </div>
                <div class="text-muted" style="font-size: 0.75rem; line-height: 1.3; margin-top: 2px;">${escapeHtml(notif.message)}</div>
                <div class="text-muted" style="font-size: 0.7rem; margin-top: 4px;">${timeAgo}</div>
              </div>
            </div>
          </li>
        `;
      }).join('');

      list.innerHTML = itemsHtml + `
        <li class="px-3 py-2 border-top bg-light">
          <a href="notifications.html" class="btn btn-sm btn-outline-primary w-100" onclick="bootstrap.Dropdown.getInstance(document.querySelector('[data-bs-toggle=\"dropdown\"]'))?.hide();">
            View all notifications
          </a>
        </li>
      `;

      // Add click handlers
      list.querySelectorAll('.notification-item').forEach(item => {
        item.addEventListener('click', async function(e) {
          // Don't navigate if clicking the "View all" button
          if (e.target.closest('a')) return;
          
          const notifId = parseInt(this.dataset.id);
          
          // Mark as read
          try {
            await fetch('/api/notifications/mark-read', {
              method: 'POST',
              headers: {'Content-Type': 'application/json'},
              credentials: 'include',
              body: JSON.stringify({notification_id: notifId})
            });
            
            // Update badge count
            if (window.updateNotificationBadge) updateNotificationBadge();
          } catch (e) {
            console.error('Error marking notification as read:', e);
          }
          
          // Navigate to deep link if available
          const notif = displayNotifications.find(n => n.id == notifId);
          if (notif && notif.deep_link && notif.deep_link !== '#') {
            window.location.href = notif.deep_link;
          }
        });
      });
    } catch (err) {
      console.error('Error loading notifications:', err);
      list.innerHTML = '<div class="text-muted small text-center py-2">Error loading notifications</div>';
    }
  }

  /**
   * Get notification icon based on type
   */
  function getNotificationIcon(type) {
    const icons = {
      'BORROW_REQUEST_SUBMITTED': 'bi bi-file-earmark-plus text-primary',
      'BORROW_REQUEST_APPROVED': 'bi bi-check-circle text-success',
      'BORROW_REQUEST_REJECTED': 'bi bi-x-circle text-danger',
      'NEW_BORROW_REQUEST': 'bi bi-bell text-info',
      'DUE_SOON': 'bi bi-clock text-warning',
      'OVERDUE': 'bi bi-exclamation-triangle text-danger',
      'LOW_STOCK': 'bi bi-exclamation-circle text-warning',
      'OVERDUE_SUMMARY': 'bi bi-list-ul text-info',
      'SETTINGS_CHANGED': 'bi bi-gear text-secondary',
      'SECURITY_ALERT': 'bi bi-shield-exclamation text-danger',
      'EMAIL_FAILURE': 'bi bi-envelope-x text-danger',
    };
    return icons[type] || 'bi bi-bell text-secondary';
  }

  /**
   * Get time ago string
   */
  function getTimeAgo(dateString) {
    const date = new Date(dateString);
    const now = new Date();
    const diffMs = now - date;
    const diffMins = Math.floor(diffMs / 60000);
    const diffHours = Math.floor(diffMs / 3600000);
    const diffDays = Math.floor(diffMs / 86400000);

    if (diffMins < 1) return 'Just now';
    if (diffMins < 60) return `${diffMins}m ago`;
    if (diffHours < 24) return `${diffHours}h ago`;
    if (diffDays < 7) return `${diffDays}d ago`;
    return date.toLocaleDateString();
  }

  /**
   * Escape HTML
   */
  function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }

  /**
   * Initialize notification system
   */
  function initNotifications() {
    // Update badge and dropdown
    updateNotificationBadge();
    loadNotificationDropdown();

    // Set up periodic updates (every 30 seconds)
    if (notificationUpdateInterval) {
      clearInterval(notificationUpdateInterval);
    }
    notificationUpdateInterval = setInterval(() => {
      updateNotificationBadge();
      // Only reload dropdown if it's open
      const dropdown = document.querySelector('.notification-dropdown');
      if (dropdown && dropdown.classList.contains('show')) {
        loadNotificationDropdown();
      }
    }, 30000);

    // Reload dropdown when notification button is clicked
    const notificationBtn = document.getElementById('notificationBtn') || document.getElementById('btnNotifications');
    if (notificationBtn) {
      notificationBtn.addEventListener('click', () => {
        loadNotificationDropdown();
      });
    }
  }

  // Initialize when DOM is ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initNotifications);
  } else {
    initNotifications();
  }

  // Export for manual updates
  window.updateNotificationBadge = updateNotificationBadge;
  window.loadNotificationDropdown = loadNotificationDropdown;
  window.initNotifications = initNotifications;
})();

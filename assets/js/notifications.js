/**
 * Centralized Notification System
 * Updates notifications consistently across all pages for a given role
 */

(function() {
  'use strict';

  // Get user role from localStorage or query param
  function getUserRole() {
    const q = new URLSearchParams(location.search).get('role');
    const fromStorage = localStorage.getItem('lt_role');
    const role = (q || fromStorage || '').toLowerCase();
    if (role === 'admin' || role === 'librarian' || role === 'student') return role;
    return null;
  }

  // Update notifications for librarian/admin
  async function updateNotifications() {
    const role = getUserRole();
    if (!role || (role !== 'librarian' && role !== 'admin')) return;

    const notificationList = document.getElementById('notificationList');
    const notificationBadge = document.getElementById('notificationBadge');
    
    if (!notificationList || !notificationBadge) return;

    try {
      const alerts = [];

      // 1. Check pending borrow requests
      try {
        const reqRes = await fetch('/api/borrow-requests/count-pending', {credentials:'include'});
        const reqData = await reqRes.json().catch(()=>({ok:false}));
        if (reqData.ok && reqData.count > 0) {
          alerts.push(`${reqData.count} pending borrow request(s)`);
        }
      } catch(e) {
        console.error('Error checking borrow requests:', e);
      }

      // 2. Check overdue loans
      try {
        const loansRes = await fetch('/api/loans?page=1&pagesize=1000&active_only=1', {credentials:'include'});
        const loansData = await loansRes.json().catch(()=>({ok:false}));
        if (loansData.ok && loansData.items) {
          const today = new Date().toISOString().slice(0,10);
          const overdue = loansData.items.filter(l => {
            const dueDate = l.extended_due_at || l.due_at;
            return dueDate && dueDate < today && l.status !== 'returned' && !l.returned_at;
          }).length;
          if (overdue > 0) {
            alerts.push(`${overdue} book(s) are overdue`);
          }
        }
      } catch(e) {
        console.error('Error checking overdue loans:', e);
      }

      // 3. Check critical/low stock books
      try {
        const booksRes = await fetch('/api/books?pagesize=1000', {credentials:'include'});
        const booksData = await booksRes.json().catch(()=>({ok:false}));
        if (booksData.ok && booksData.items) {
          const critical = booksData.items.filter(b => {
            const available = (b.quantity || 0) - (b.borrowed || 0);
            return available <= 1 && !b.archived;
          }).length;
          if (critical > 0) {
            alerts.push(`${critical} title(s) are critically low`);
          }
        }
      } catch(e) {
        console.error('Error checking low stock:', e);
      }

      // 4. Check books due today
      try {
        const loansRes = await fetch('/api/loans?page=1&pagesize=1000&active_only=1', {credentials:'include'});
        const loansData = await loansRes.json().catch(()=>({ok:false}));
        if (loansData.ok && loansData.items) {
          const today = new Date().toISOString().slice(0,10);
          const dueToday = loansData.items.filter(l => {
            const dueDate = l.extended_due_at || l.due_at;
            return dueDate === today && l.status !== 'returned' && !l.returned_at;
          }).length;
          if (dueToday > 0) {
            alerts.push(`${dueToday} book(s) due today`);
          }
        }
      } catch(e) {
        console.error('Error checking due today:', e);
      }

      // 5. Admin-specific alerts (only for admin role)
      if (role === 'admin') {
        try {
          // Check for disabled accounts
          const usersRes = await fetch('/api/users?role=all', {credentials:'include'});
          const usersData = await usersRes.json().catch(()=>({ok:false}));
          if (usersData.ok && usersData.items) {
            const disabled = usersData.items.filter(u => u.status === 'disabled' || u.disabled === 1).length;
            if (disabled > 0) {
              alerts.push(`${disabled} disabled account(s)`);
            }
          }
        } catch(e) {
          console.error('Error checking admin alerts:', e);
        }
      }

      // Update UI
      if (alerts.length > 0) {
        notificationBadge.textContent = alerts.length;
        notificationBadge.classList.remove('d-none');
        notificationList.innerHTML = alerts.map(a => `
          <div class="d-flex align-items-start gap-2 py-2 border-bottom">
            <i class="bi bi-exclamation-triangle-fill text-warning mt-1"></i>
            <div class="flex-grow-1 small">${a}</div>
          </div>
        `).join('');
      } else {
        notificationBadge.classList.add('d-none');
        notificationList.innerHTML = '<div class="text-muted small text-center py-2">No alerts</div>';
      }
    } catch(err) {
      console.error('Error updating notifications:', err);
      notificationBadge.classList.add('d-none');
      notificationList.innerHTML = '<div class="text-muted small text-center py-2">Error loading notifications</div>';
    }
  }

  // Initialize notifications when DOM is ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
      updateNotifications();
      // Update every 30 seconds
      setInterval(updateNotifications, 30000);
    });
  } else {
    updateNotifications();
    setInterval(updateNotifications, 30000);
  }

  // Export for manual updates if needed
  window.updateNotifications = updateNotifications;
})();


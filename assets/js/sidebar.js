// Role-aware shared sidebar - Unified for Admin, Librarian, and Student
// Version: 2025-11-19-FINAL-LOANS-COMBINED-V4
console.log('Sidebar.js loaded - Version 2025-11-19-FINAL-LOANS-COMBINED-V4');
(function () {
  const APP_BASE = '/';

  const ROUTES = {
    admin: {
      dashboard: 'dashboard.html',
      users: 'admin-users.html',
      categories: 'admin-categories.html',
      settings: 'admin-settings.html',
      logs: 'admin-logs.html',
      emailHistory: 'email-notification-history.html',
      profile: 'admin-profile.html',
      logout: 'login.html',
    },
    librarian: {
      dashboard:'dashboard.html',
      books:'books.html',
      users:'librarian-users.html',
      borrow:'borrow-return.html',
      borrowrequests:'librarian-borrow-requests.html',
      loans:'librarian-active-loans.html',
      lowstock:'librarian-lowstock.html',
      history:'librarian-history.html',
      profile:'librarian-profile.html',
      logout:'login.html'
    },

    student: {
      dashboard: 'dashboard.html',
      books: 'books.html',
      history: 'student-history.html',
      favorites: 'favorites.html',
      emailHistory: 'email-notification-history.html',
      profile: 'student-profile.html',
      logout: 'login.html'
    }
  };

  // Map filename to page identifier
  const PAGE_MAP = {
    'dashboard.html': 'dashboard',
    'admin-users.html': 'users',
    'admin-categories.html': 'categories',
    'admin-settings.html': 'settings',
    'admin-logs.html': 'logs',
    'admin-profile.html': 'profile',
    'books.html': 'books',
    'librarian-users.html': 'users',
    'borrow-return.html': 'borrow',
    'librarian-borrow-requests.html': 'borrowrequests',
    'librarian-active-loans.html': 'loans',
    'librarian-lowstock.html': 'lowstock',
    'librarian-history.html': 'history',
    'librarian-profile.html': 'profile',
    'student-history.html': 'history',
    'email-notification-history.html': 'email-history',
    'student-notifications.html': 'email-history',
    'student-favorite.html': 'favorites',
    'student-favorites.html': 'favorites',
    'favorites.html': 'favorites',
    'student-profile.html': 'profile',
    'profile.html': 'profile'
  };

  const ACTIVE_ALIASES = {
    'members': 'users',
    'librarian-users': 'users',
    'borrowreturn': 'borrow',
    'borrow-return': 'borrow'
  };

  const url  = (page) => APP_BASE + page;
  const link = (page, icon, text, active) =>
    `<a class="sb-link${active ? ' active' : ''}" href="${url(page)}">
       <i class="bi ${icon}"></i><span class="sb-text">${text}</span>
     </a>`;

  function menuFor(role, current) {
    if (role === 'admin') {
      return `
        <div class="sb-label">Main</div>
        ${link(ROUTES.admin.dashboard,'bi-speedometer2','Dashboard',current==='dashboard')}
        ${link(ROUTES.admin.users,'bi-people','Users',current==='users')}

        <div class="sb-label mt-3">Management</div>
        ${link(ROUTES.admin.categories,'bi-tags','Categories',current==='categories')}

        <div class="sb-label mt-3">System</div>
        ${link(ROUTES.admin.settings,'bi-gear','System Settings',current==='settings')}
        ${link(ROUTES.admin.logs,'bi-card-checklist','Logs',current==='logs')}
        ${link(ROUTES.admin.emailHistory,'bi-envelope-check','Email History',current==='email-history')}

        <div class="sb-label mt-3">Account</div>
        ${link(ROUTES.admin.profile,'bi-person-gear','Profile',current==='profile')}
        ${link(ROUTES.admin.logout,'bi-box-arrow-right','Logout',false)}
      `;
    }

    if (role === 'student') {
      return `
            <div class="sb-label">Main</div>
            ${link(ROUTES.student.dashboard,'bi-speedometer2','Dashboard',current==='dashboard')}
            ${link(ROUTES.student.books,'bi-journal-text','Books',current==='books')}
            ${link(ROUTES.student.history,'bi-clock-history','History',current==='history')}
            ${link(ROUTES.student.favorites,'bi-heart','Favorites',current==='favorites')}
            ${link(ROUTES.student.emailHistory,'bi-envelope-check','Email History',current==='email-history')}
            <div class="sb-label mt-3">Account</div>
            ${link(ROUTES.student.profile,'bi-person','Profile',current==='profile')}
            ${link(ROUTES.student.logout,'bi-box-arrow-right','Logout',false)}
          `;
    }

    // librarian
    return `
      <div class="sb-label">Main</div>
      ${link(ROUTES.librarian.dashboard,'bi-speedometer2','Dashboard',current==='dashboard')}
      ${link(ROUTES.librarian.books,'bi-journal-text','Books',current==='books')}
      ${link(ROUTES.librarian.users,'bi-people','Users',current==='users')}
      ${link(ROUTES.librarian.borrowrequests,'bi-inbox','Borrow Requests',current==='borrowrequests')}
      ${link(ROUTES.librarian.loans,'bi-journal-arrow-up','Active Loans',current==='loans')}

      <div class="sb-label mt-3">Reports</div>
      ${link(ROUTES.librarian.lowstock,'bi-box-seam','Low Stock',current==='lowstock')}
      ${link(ROUTES.librarian.history,'bi-clock-history','History',current==='history')}

      <div class="sb-label mt-3">Account</div>
      ${link(ROUTES.librarian.profile,'bi-person','Profile',current==='profile')}
      ${link(ROUTES.librarian.logout,'bi-box-arrow-right','Logout',false)}

      <div class="sb-label mt-3">Quick Actions</div>
      <button id="qaAddBook" class="btn btn-gold w-100 mb-2">
        <i class="bi bi-plus-circle me-2"></i><span class="sb-text">Add Book</span>
      </button>
      <button id="qaRegMember" class="btn btn-outline-light w-100">
        <i class="bi bi-person-plus me-2"></i><span class="sb-text">Register Member</span>
      </button>
    `;
  }

  function patchLegacyLinks() {
    document.querySelectorAll('.sb-link').forEach(a => {
      const href = a.getAttribute('href') || '';
      if (href === APP_BASE + 'members.html') {
        a.setAttribute('href', APP_BASE + ROUTES.librarian.users);
      }
      if (href === APP_BASE + 'borrow-return.html') {
        a.setAttribute('href', APP_BASE + ROUTES.librarian.borrow);
      }
    });
  }

  // Auto-detect current page from filename
  function detectCurrentPage() {
    const pathname = window.location.pathname;
    const filename = pathname.split('/').pop() || 'dashboard.html';
    return PAGE_MAP[filename] || 'dashboard';
  }

  window.renderSidebar = function(current=null, role='student'){
    // Auto-detect current page if not provided
    if (!current) current = detectCurrentPage();
    
    const normalized = ACTIVE_ALIASES[current] || current;

    const root = document.getElementById('sidebar-root');
    if (!root) return;

    role = String(role || '').toLowerCase();
    if (!['admin','librarian','student'].includes(role)) role = 'student';

    root.innerHTML = `
      <nav id="sidebar" class="sb-sidebar d-flex flex-column">
        <a class="sb-brand" href="${url('dashboard.html')}">
          <img src="assets/img/LibraTrack-logo.png" alt="LibraTrack" class="sb-logo">
          <span class="sb-text fw-semibold">LibraTrack</span>
        </a>
        <div class="sb-menu flex-grow-1">${menuFor(role, normalized)}</div>
        <div class="mt-auto small text-secondary px-3 pb-3 sb-text">© <span id="year"></span> LibraTrack</div>
      </nav>
    `;

    patchLegacyLinks();

    // Librarian quick actions
    root.querySelector('#qaAddBook')?.addEventListener('click', () => location.href = url('books.html#add'));
    root.querySelector('#qaRegMember')?.addEventListener('click', () => location.href = url('librarian-users.html'));

    // Logout handler - use confirmation modal if available, otherwise direct logout
    root.querySelectorAll('a[href*="login.html"]').forEach(link => {
      if (link.textContent.trim().toLowerCase().includes('logout')) {
        // Set id for logout.js to handle
        link.id = 'logoutLink';
        link.href = '#';
        // Keep logout link white (don't add text-danger class)
        
        // Always check for confirmation function when clicked (in case logout.js loads after sidebar.js)
        link.addEventListener('click', async (e) => {
          e.preventDefault();
          
          // If logout.js is loaded, use confirmation modal
          if (typeof window.showLogoutConfirmation === 'function') {
            window.showLogoutConfirmation();
          } else {
            // Fallback: direct logout if logout.js not loaded
            try {
              await fetch(APP_BASE + 'backend/logout.php', { method: 'POST', credentials: 'include' });
            } catch {}
            location.href = url('login.html');
          }
        });
      }
    });

    // Set copyright year
    const yearEl = root.querySelector('#year');
    if (yearEl && !yearEl.textContent) {
      yearEl.textContent = new Date().getFullYear();
    }
    
    // Re-initialize logout handlers if logout.js is loaded (for sidebar logout links)
    if (typeof window.initLogoutHandlers === 'function') {
      setTimeout(() => {
        window.initLogoutHandlers();
      }, 100);
    }
  };

  window.toggleSidebar = function(){
    const isDesktop = window.innerWidth >= 992;
    const cls = isDesktop ? 'sb-expanded' : 'sb-toggled';
    document.body.classList.toggle(cls);
    if (isDesktop) document.body.classList.remove('sb-toggled');
  };
  
  window.addEventListener('resize', () => {
    if (window.innerWidth >= 992) document.body.classList.remove('sb-toggled');
  });

  // Auto-initialize sidebar if sidebar-root exists and no manual init
  // This allows pages to just load sidebar.js and it will auto-detect
  if (document.getElementById('sidebar-root') && typeof window.renderSidebar === 'function') {
    // Wait for DOM ready
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', () => {
        // Check if auth endpoint exists and get role
        fetch('/libratrack/backend/check-auth.php', { credentials: 'include', headers: { 'Accept': 'application/json' } })
          .then(res => res.ok ? res.json() : null)
          .then(data => {
            const role = (data?.user?.role || 'student').toLowerCase();
            if (['admin', 'librarian', 'student'].includes(role)) {
              renderSidebar(null, role);
            }
          })
          .catch(() => {
            // Fallback: try to infer from URL or default to student
            const path = window.location.pathname;
            let inferredRole = 'student';
            if (path.includes('admin-')) inferredRole = 'admin';
            else if (path.includes('librarian-') || path === '/libratrack/borrow-return.html') inferredRole = 'librarian';
            renderSidebar(null, inferredRole);
          });
      });
    } else {
      // Already loaded, try auth immediately
      fetch('/libratrack/backend/check-auth.php', { credentials: 'include', headers: { 'Accept': 'application/json' } })
        .then(res => res.ok ? res.json() : null)
        .then(data => {
          const role = (data?.user?.role || 'student').toLowerCase();
          if (['admin', 'librarian', 'student'].includes(role)) {
            renderSidebar(null, role);
          }
        })
        .catch(() => {
          const path = window.location.pathname;
          let inferredRole = 'student';
          if (path.includes('admin-')) inferredRole = 'admin';
          else if (path.includes('librarian-') || path === '/libratrack/borrow-return.html') inferredRole = 'librarian';
          renderSidebar(null, inferredRole);
        });
    }
  }
})();


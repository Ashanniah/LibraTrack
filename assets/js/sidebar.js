// Role-aware shared sidebar
(function () {
  const APP_BASE = '/libratrack/';

  const ROUTES = {
    admin: {
      dashboard: 'dashboard.html',
      books: 'books.html',
      users: 'admin-users.html',
      profile: 'admin-profile.html',
      // settings/reports/audit reserved
    },
    // librarian members now points to librarian-users.html
    librarian: {
      dashboard:'dashboard.html',
      books:'books.html',
      users:'librarian-users.html', 
      borrow:'borrow-return.html',
      overdue:'overdue.html',
      lowstock:'lowstock.html',
      history:'history.html',
      emails:'emails.html',
      regMember:'registered-members.html'
    },

    student: {
      dashboard: 'dashboard.html',
      books: 'books.html',
      search: 'search.html',
      history: 'history.html',
      favorites: 'favorites.html',
      overdue: 'overdue.html',
      notifications: 'notifications.html',
      profile: 'profile.html',
      logout: 'login.html'
    }
  };

  // Allow pages to pass either "members" or "librarian-users" as the active key
  const ACTIVE_ALIASES = {
    'members': 'users',            // ✅ old key points to new one
    'librarian-users': 'users'
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

        <div class="sb-label mt-3">Account</div>
        ${link(ROUTES.admin.profile,'bi-person-gear','Profile',current==='profile')}
      `;
    }

    if (role === 'student') {
      return `
        <div class="sb-label">Main</div>
        ${link(ROUTES.student.dashboard,'bi-speedometer2','Dashboard',current==='dashboard')}
        ${link(ROUTES.student.books,'bi-journal-text','Books',current==='books')}
        ${link(ROUTES.student.search,'bi-search','Search Books',current==='search')}
        ${link(ROUTES.student.history,'bi-clock-history','History',current==='history')}
        ${link(ROUTES.student.favorites,'bi-heart','Favorites',current==='favorites')}
        ${link(ROUTES.student.overdue,'bi-exclamation-triangle','Overdue',current==='overdue')}
        ${link(ROUTES.student.notifications,'bi-envelope-open','Notifications',current==='notifications')}
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
      ${link(ROUTES.librarian.users,'bi-people','Users',current==='users')}   <!-- ✅ label -->
      ${link(ROUTES.librarian.borrow,'bi-arrow-left-right','Borrow/Return',current==='borrow')}

      <div class="sb-label mt-3">Monitoring</div>
      ${link(ROUTES.librarian.overdue,'bi-exclamation-triangle','Overdue',current==='overdue')}
      ${link(ROUTES.librarian.lowstock,'bi-box-seam','Low Stock',current==='lowstock')}
      ${link(ROUTES.librarian.history,'bi-clock-history','History',current==='history')}
      ${link(ROUTES.librarian.emails,'bi-envelope-paper-heart','Emails',current==='emails')}

      <div class="sb-label mt-3">Quick Actions</div>
      <button id="qaAddBook" class="btn btn-gold w-100 mb-2">
        <i class="bi bi-plus-circle me-2"></i><span class="sb-text">Add Book</span>
      </button>
      <button id="qaRegMember" class="btn btn-outline-light w-100">
        <i class="bi bi-person-plus me-2"></i><span class="sb-text">Register Member</span>
      </button>
    `;
  }

  // --- shim: patch any stale "members.html" links to "librarian-users.html"
  function patchOldMembersLink() {
    document.querySelectorAll('.sb-link').forEach(a => {
      const href = a.getAttribute('href') || '';
      if (href === APP_BASE + 'members.html') {
        a.setAttribute('href', APP_BASE + 'librarian-users.html');
      }
    });
  }

  window.renderSidebar = function(current='dashboard', role='student'){
    // normalize active key (so pages can use either alias)
    const normalized = ACTIVE_ALIASES[current] || current;

    const root = document.getElementById('sidebar-root');
    if (!root) return;

    // harden role string
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

    // apply shim after DOM is inserted
    patchOldMembersLink();

    // quick actions
    root.querySelector('#qaAddBook')?.addEventListener('click', () => location.href = url('books.html#add'));
    root.querySelector('#qaRegMember')?.addEventListener('click', () => location.href = url('registered-members.html'));
    root.querySelector('#year')?.append(new Date().getFullYear());
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
})();

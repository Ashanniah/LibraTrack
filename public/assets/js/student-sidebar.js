(function(){
  const LINKS = [
    {id:'dashboard', icon:'bi-speedometer2', text:'Dashboard', href:'dashboard.html'},
    {id:'books', icon:'bi-journal-text', text:'Books', href:'books.html'},
    {id:'history', icon:'bi-clock-history', text:'History', href:'student-history.html'},
    {id:'favorites', icon:'bi-heart', text:'Favorites', href:'student-favorite.html'},
    {id:'overdue', icon:'bi-exclamation-triangle', text:'Overdue', href:'student-overdue.html'},
    {id:'notifications', icon:'bi-bell', text:'Notifications', href:'student-notifications.html'},
  ];

  function renderStudentSidebar(active='dashboard') {
    const root = document.getElementById('sidebar-root');
    if (!root) return;
    root.innerHTML = `
      <nav id="sidebar" class="sb-sidebar d-flex flex-column">
        <a class="sb-brand" href="dashboard.html">
          <img src="assets/img/LibraTrack-logo.png" class="sb-logo" alt="LibraTrack">
          <span class="sb-text fw-semibold">LibraTrack</span>
        </a>
        <div class="sb-menu flex-grow-1">
          <div class="sb-label">Main</div>
          ${LINKS.map(link => `
            <a class="sb-link ${active===link.id?'active':''}" href="${link.href}">
              <i class="bi ${link.icon}"></i><span class="sb-text">${link.text}</span>
            </a>
          `).join('')}
          <div class="sb-label mt-3">Account</div>
          <a class="sb-link" href="student-profile.html"><i class="bi bi-person"></i><span class="sb-text">Profile</span></a>
          <a class="sb-link" href="login.html"><i class="bi bi-box-arrow-right"></i><span class="sb-text">Logout</span></a>
        </div>
        <div class="mt-auto small text-secondary px-3 pb-3 sb-text">© <span id="year"></span> LibraTrack</div>
      </nav>
    `;
    const year = root.querySelector('#year');
    if (year) year.textContent = new Date().getFullYear();
  }

  function toggleSidebar() {
    const desktop = window.innerWidth >= 992;
    if (desktop) {
      document.body.classList.toggle('sb-expanded');
      document.body.classList.remove('sb-toggled');
    } else {
      document.body.classList.toggle('sb-toggled');
      document.body.classList.remove('sb-expanded');
    }
  }

  window.initStudentSidebar = function(active){
    renderStudentSidebar(active);
    document.getElementById('sidebarToggle')?.addEventListener('click', toggleSidebar);
    window.addEventListener('resize', () => {
      if (window.innerWidth >= 992) {
        document.body.classList.remove('sb-toggled');
      }
    });
  };
})();


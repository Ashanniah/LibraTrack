(function(){
  // read role from ?role= OR localStorage.role (set it at login)
  const urlRole = new URLSearchParams(location.search).get('role');
  const role = (urlRole || localStorage.getItem('role') || 'librarian').toLowerCase();

  // persist if provided via query
  if (urlRole) localStorage.setItem('role', role);

  // mount sidebar for this role
  renderSidebar('dashboard', role);

  // toggle button
  document.getElementById('sidebarToggle')?.addEventListener('click', toggleSidebar);

  // show proper view
  const views = {
    admin: document.getElementById('view-admin'),
    librarian: document.getElementById('view-librarian'),
    student: document.getElementById('view-student')
  };
  Object.values(views).forEach(v => v && (v.hidden = true));
  if (views[role]) views[role].hidden = false;

  // topbar role label
  document.getElementById('roleLabel').textContent =
    role.charAt(0).toUpperCase()+role.slice(1);

  // dev role switcher in topbar
  document.querySelectorAll('.js-set-role').forEach(btn=>{
    btn.addEventListener('click', e=>{
      const r = e.currentTarget.dataset.role;
      localStorage.setItem('role', r);
      location.href = 'dashboard.html'; // clean query + reload
    });
  });
})();

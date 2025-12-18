/**
 * Logout Confirmation Utility
 * Provides a reusable logout confirmation dialog for all pages
 */

(function() {
  'use strict';

  /**
   * Create logout confirmation modal if it doesn't exist
   */
  function ensureLogoutModal() {
    let modal = document.getElementById('logoutConfirmModal');
    if (modal) return modal;

    // Create modal HTML
    const modalHTML = `
      <div class="modal fade" id="logoutConfirmModal" tabindex="-1" aria-labelledby="logoutConfirmModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content" style="border-radius: 16px; border: 1px solid #e5e7eb; box-shadow: 0 6px 18px rgba(0,0,0,.12);">
            <div class="modal-header" style="background: #111; color: #fff; border-bottom: 0; padding: 20px 24px; border-radius: 16px 16px 0 0;">
              <h5 class="modal-title" id="logoutConfirmModalLabel" style="color: #fff; font-weight: 600; font-size: 1.125rem;">
                <i class="bi bi-box-arrow-right me-2"></i>Confirm Logout
              </h5>
              <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" style="padding: 24px;">
              <div class="text-center mb-3">
                <div style="width: 64px; height: 64px; margin: 0 auto 16px; display: flex; align-items: center; justify-content: center; background: #fee2e2; border-radius: 50%;">
                  <i class="bi bi-exclamation-triangle-fill" style="font-size: 2rem; color: #dc2626;"></i>
                </div>
                <h6 style="font-weight: 600; color: #212529; margin-bottom: 8px;">Are you sure you want to logout?</h6>
                <p class="text-muted mb-0" style="font-size: 0.875rem; color: #6b7280;">
                  You will need to login again to access your account.
                </p>
              </div>
            </div>
            <div class="modal-footer" style="border-top: 1px solid #e5e7eb; padding: 16px 24px; border-radius: 0 0 16px 16px;">
              <button type="button" class="btn btn-light" data-bs-dismiss="modal" style="border-radius: 8px; padding: 10px 20px; font-weight: 600;">
                <i class="bi bi-x-circle me-2"></i>Cancel
              </button>
              <button type="button" class="btn btn-danger" id="confirmLogoutBtn" style="border-radius: 8px; padding: 10px 20px; font-weight: 600; background: #dc2626; border-color: #dc2626;">
                <i class="bi bi-box-arrow-right me-2"></i>Logout
              </button>
            </div>
          </div>
        </div>
      </div>
    `;

    // Append to body
    document.body.insertAdjacentHTML('beforeend', modalHTML);
    return document.getElementById('logoutConfirmModal');
  }

  /**
   * Show logout confirmation dialog and handle logout
   */
  window.showLogoutConfirmation = function() {
    const modal = ensureLogoutModal();
    const bsModal = new bootstrap.Modal(modal);
    
    // Get confirm button
    const confirmBtn = document.getElementById('confirmLogoutBtn');
    
    // Remove any existing listeners
    const newConfirmBtn = confirmBtn.cloneNode(true);
    confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);
    
    // Add click handler to confirm button
    newConfirmBtn.addEventListener('click', async () => {
      // Disable button during logout
      newConfirmBtn.disabled = true;
      newConfirmBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Logging out...';
      
      try {
        // Perform logout
        await fetch('/api/logout', { 
          method: 'POST', 
          credentials: 'include' 
        });
        
        // Clear local storage
        localStorage.removeItem('userRole');
        
        // Hide modal
        bsModal.hide();
        
        // Redirect to login
        const currentPath = window.location.pathname;
        if (currentPath.includes('/libratrack/')) {
          location.href = '/libratrack/login.html';
        } else if (currentPath.includes('/login')) {
          location.href = '/login.html';
        } else {
          location.href = 'login.html';
        }
      } catch (error) {
        console.error('Logout error:', error);
        // Still redirect even if API call fails
        bsModal.hide();
        const currentPath = window.location.pathname;
        if (currentPath.includes('/libratrack/')) {
          location.href = '/libratrack/login.html';
        } else if (currentPath.includes('/login')) {
          location.href = '/login.html';
        } else {
          location.href = 'login.html';
        }
      }
    });
    
    // Show modal
    bsModal.show();
    
    // Reset button when modal is hidden
    modal.addEventListener('hidden.bs.modal', function resetButton() {
      newConfirmBtn.disabled = false;
      newConfirmBtn.innerHTML = '<i class="bi bi-box-arrow-right me-2"></i>Logout';
      modal.removeEventListener('hidden.bs.modal', resetButton);
    }, { once: true });
  };

  /**
   * Initialize logout handlers for all logout links on the page
   */
  window.initLogoutHandlers = function() {
    const logoutLinks = document.querySelectorAll('#logoutLink, [data-logout]');
    
    logoutLinks.forEach(link => {
      // Skip if already has our handler (check for data attribute)
      if (link.dataset.logoutInitialized === 'true') {
        return;
      }
      
      // Remove existing listeners by cloning
      const newLink = link.cloneNode(true);
      link.parentNode.replaceChild(newLink, link);
      
      // Mark as initialized
      newLink.dataset.logoutInitialized = 'true';
      
      // Add click handler
      newLink.addEventListener('click', (e) => {
        e.preventDefault();
        window.showLogoutConfirmation();
      });
    });
  };

  // Auto-initialize when DOM is ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', window.initLogoutHandlers);
  } else {
    window.initLogoutHandlers();
  }

})();


/**
 * LibraTrack - Reusable Loading State Utilities
 * Provides consistent skeleton loaders and loading state management across all pages
 */

// Global loading state
window.pageLoadingState = {
  isLoading: false,
  activeLoaders: new Set()
};

/**
 * Show page-level loading (skeleton cards/containers)
 * @param {string} containerId - ID of container to show skeleton in
 * @param {Object} options - Configuration options
 */
window.showPageLoading = function(containerId, options = {}) {
  const container = document.getElementById(containerId);
  if (!container) return;
  
  const { skeletonType = 'cards', count = 8 } = options;
  window.pageLoadingState.activeLoaders.add(containerId);
  window.pageLoadingState.isLoading = true;
  
  if (skeletonType === 'cards') {
    container.innerHTML = Array.from({length: count}, () => `
      <div class="skeleton-card">
        <div class="skeleton-line short"></div>
        <div class="skeleton-line long"></div>
      </div>
    `).join('');
  }
};

/**
 * Hide page-level loading
 * @param {string} containerId - ID of container to hide skeleton in
 */
window.hidePageLoading = function(containerId) {
  const container = document.getElementById(containerId);
  if (container) {
    window.pageLoadingState.activeLoaders.delete(containerId);
    if (window.pageLoadingState.activeLoaders.size === 0) {
      window.pageLoadingState.isLoading = false;
    }
  }
};

/**
 * Show table skeleton loading
 * @param {string} tableBodyId - ID of tbody element
 * @param {number} rows - Number of skeleton rows
 * @param {number} cols - Number of columns
 * @param {Object} options - Additional options (includeCheckbox, columnLayout)
 */
window.showTableLoading = function(tableBodyId, rows = 5, cols = null, options = {}) {
  const tbody = document.getElementById(tableBodyId);
  if (!tbody) return;
  
  const { includeCheckbox = false, columnLayout = null } = options;
  
  // If columnLayout is provided, use it; otherwise generate generic skeleton
  if (columnLayout && Array.isArray(columnLayout)) {
    tbody.innerHTML = Array.from({length: rows}, () => {
      const cells = columnLayout.map(col => {
        if (col === 'checkbox') {
          return '<td><div class="skeleton-checkbox"></div></td>';
        } else if (col === 'cover') {
          return '<td><div class="skeleton-cover"></div></td>';
        } else if (col === 'avatar') {
          return '<td><div class="skeleton-avatar"></div></td>';
        } else if (col === 'badge') {
          return '<td><div class="skeleton-badge"></div></td>';
        } else if (col === 'button') {
          return '<td class="text-end"><div class="skeleton-button"></div></td>';
        } else if (col === 'buttons') {
          return '<td class="text-end"><div class="d-flex align-items-center gap-2 justify-content-end"><div class="skeleton-button"></div><div class="skeleton-button"></div><div class="skeleton-button"></div></div></td>';
        } else if (col === 'two-lines') {
          return '<td><div class="skeleton-line medium" style="margin-bottom: 4px;"></div><div class="skeleton-line short"></div></td>';
        } else {
          return `<td><div class="skeleton-line ${col || 'medium'}"></div></td>`;
        }
      });
      return `<tr class="skeleton-table-row">${cells.join('')}</tr>`;
    }).join('');
  } else {
    // Generic skeleton based on cols count
    const colCount = cols || 5;
    tbody.innerHTML = Array.from({length: rows}, () => {
      const cells = Array.from({length: colCount}, () => '<td><div class="skeleton-line medium"></div></td>').join('');
      return `<tr class="skeleton-table-row">${cells}</tr>`;
    }).join('');
  }
};

/**
 * Hide table skeleton loading
 * @param {string} tableBodyId - ID of tbody element
 */
window.hideTableLoading = function(tableBodyId) {
  const tbody = document.getElementById(tableBodyId);
  if (tbody) {
    // Skeletons will be replaced by actual data rendering
    // This function is mainly for cleanup if needed
  }
};

/**
 * Set loading state for form inputs and buttons
 * @param {boolean} isLoading - Whether to enable or disable
 * @param {Object} options - Selectors for elements to disable
 */
window.setFormLoadingState = function(isLoading, options = {}) {
  const {
    inputs = [],
    selects = [],
    buttons = [],
    textareas = []
  } = options;
  
  [...inputs, ...selects, ...textareas].forEach(selector => {
    const elements = typeof selector === 'string' 
      ? document.querySelectorAll(selector)
      : Array.isArray(selector) ? selector : [selector];
    elements.forEach(el => {
      if (el) {
        el.disabled = isLoading;
        if (isLoading) {
          el.classList.add('loading-disabled');
        } else {
          el.classList.remove('loading-disabled');
        }
      }
    });
  });
  
  buttons.forEach(btn => {
    const element = typeof btn === 'string' ? document.getElementById(btn) : btn;
    if (element) {
      element.disabled = isLoading;
      if (isLoading) {
        element.classList.add('btn-loading');
        if (!element.dataset.originalText) {
          element.dataset.originalText = element.innerHTML;
        }
        if (element.dataset.showSpinner !== 'false') {
          element.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>' + (element.dataset.loadingText || 'Loading...');
        }
      } else {
        element.classList.remove('btn-loading');
        if (element.dataset.originalText) {
          element.innerHTML = element.dataset.originalText;
          delete element.dataset.originalText;
        }
      }
    }
  });
};

/**
 * Show skeleton for KPI/summary cards
 * @param {Array<string>} cardIds - Array of card value element IDs
 */
window.showCardSkeletons = function(cardIds) {
  cardIds.forEach(id => {
    const el = document.getElementById(id);
    if (el) {
      el.innerHTML = '<div class="skeleton skeleton-value"></div>';
    }
  });
};

/**
 * Update cards with real values (replaces skeletons)
 * @param {Object} cardData - Object mapping card IDs to values
 */
window.updateCards = function(cardData) {
  Object.entries(cardData).forEach(([id, value]) => {
    const el = document.getElementById(id);
    if (el) {
      el.innerHTML = '';
      el.textContent = value;
      el.classList.add('fade-in');
    }
  });
};




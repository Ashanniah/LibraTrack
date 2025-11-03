/**
 * Template Name: NiceRestaurant
 * Template URL: https://bootstrapmade.com/nice-restaurant-bootstrap-template/
 * Updated: Jun 06 2025 with Bootstrap v5.3.6
 * Author: BootstrapMade.com
 * License: https://bootstrapmade.com/license/
 */

(function () {
  "use strict";

  // Small helpers
  const $ = (sel, scope = document) => scope.querySelector(sel);
  const $$ = (sel, scope = document) => Array.from(scope.querySelectorAll(sel));
  const on = (el, evt, cb, opts) => el && el.addEventListener(evt, cb, opts);
  const throttle = (fn, wait = 100) => {
    let t = 0;
    return (...args) => {
      const now = performance.now();
      if (now - t >= wait) {
        t = now;
        fn(...args);
      }
    };
  };

  /**
   * Apply .scrolled class to body when page is scrolled down
   */
  const header = $("#header");
  const toggleScrolled = throttle(() => {
    if (!header) return;
    const body = document.body;
    const sticky =
      header.classList.contains("scroll-up-sticky") ||
      header.classList.contains("sticky-top") ||
      header.classList.contains("fixed-top");
    if (!sticky) return;
    if (window.scrollY > 100) {
      body.classList.add("scrolled");
    } else {
      body.classList.remove("scrolled");
    }
  }, 100);

  on(document, "scroll", toggleScrolled, { passive: true });
  on(window, "load", toggleScrolled);

  /**
   * Mobile nav toggle
   */
  const mobileNavToggleBtn = $(".mobile-nav-toggle");
  function mobileNavToggle() {
    document.body.classList.toggle("mobile-nav-active");
    mobileNavToggleBtn?.classList.toggle("bi-list");
    mobileNavToggleBtn?.classList.toggle("bi-x");

    // Accessibility
    if (mobileNavToggleBtn) {
      const expanded = mobileNavToggleBtn.getAttribute("aria-expanded") === "true";
      mobileNavToggleBtn.setAttribute("aria-expanded", String(!expanded));
    }
  }
  on(mobileNavToggleBtn, "click", mobileNavToggle);

  // Hide mobile nav on same-page/hash links
  $$("#navmenu a").forEach((link) => {
    on(link, "click", () => {
      if (document.body.classList.contains("mobile-nav-active")) {
        mobileNavToggle();
      }
    });
  });

  /**
   * Toggle mobile nav dropdowns
   */
  $$(".navmenu .toggle-dropdown").forEach((toggle) => {
    on(toggle, "click", (e) => {
      e.preventDefault();
      const parent = toggle.parentNode;
      parent?.classList.toggle("active");
      parent?.nextElementSibling?.classList.toggle("dropdown-active");
      e.stopImmediatePropagation();
    });
  });

  /**
   * Preloader
   */
  const preloader = $("#preloader");
  if (preloader) {
    on(window, "load", () => preloader.remove());
  }

  /**
   * Scroll top button
   */
  const scrollTop = $(".scroll-top");
  const toggleScrollTop = throttle(() => {
    if (!scrollTop) return;
    if (window.scrollY > 100) {
      scrollTop.classList.add("active");
    } else {
      scrollTop.classList.remove("active");
    }
  }, 100);

  if (scrollTop) {
    on(scrollTop, "click", (e) => {
      e.preventDefault();
      window.scrollTo({ top: 0, behavior: "smooth" });
    });
    on(window, "load", toggleScrollTop);
    on(document, "scroll", toggleScrollTop, { passive: true });
  }

  /**
   * AOS init (guarded)
   */
  function aosInit() {
    if (!window.AOS) return;
    AOS.init({
      duration: 600,
      easing: "ease-in-out",
      once: true,
      mirror: false,
      // Respect reduced motion
      disable: window.matchMedia("(prefers-reduced-motion: reduce)").matches,
    });
  }
  on(window, "load", aosInit);

  /**
   * Init Swiper sliders (guard + try/catch)
   */
  function initSwiper() {
    if (!window.Swiper) return;
    $$(".init-swiper").forEach((el) => {
      try {
        const cfgEl = el.querySelector(".swiper-config");
        const config = cfgEl ? JSON.parse(cfgEl.textContent.trim()) : {};
        if (el.classList.contains("swiper-tab") && window.initSwiperWithCustomPagination) {
          window.initSwiperWithCustomPagination(el, config);
        } else {
          new Swiper(el, config);
        }
      } catch (err) {
        console.warn("[Swiper] Invalid config:", err);
      }
    });
  }
  on(window, "load", initSwiper);

  /**
   * Pure Counter (guarded)
   */
  if (window.PureCounter) new PureCounter();

  /**
   * Isotope layout & filters (guarded)
   */
  $$(".isotope-layout").forEach((wrap) => {
    if (!window.Isotope || !window.imagesLoaded) return;

    const layout = wrap.getAttribute("data-layout") ?? "masonry";
    const filter = wrap.getAttribute("data-default-filter") ?? "*";
    const sort = wrap.getAttribute("data-sort") ?? "original-order";

    let iso;
    imagesLoaded(wrap.querySelector(".isotope-container"), () => {
      iso = new Isotope(wrap.querySelector(".isotope-container"), {
        itemSelector: ".isotope-item",
        layoutMode: layout,
        filter,
        sortBy: sort,
      });
    });

    wrap.querySelectorAll(".isotope-filters li").forEach((btn) => {
      on(btn, "click", () => {
        const current = wrap.querySelector(".isotope-filters .filter-active");
        current && current.classList.remove("filter-active");
        btn.classList.add("filter-active");
        iso?.arrange({ filter: btn.getAttribute("data-filter") });
        aosInit?.();
      });
    });
  });

  /**
   * Correct scrolling position on hash URLs (after load)
   */
  on(window, "load", () => {
    if (!window.location.hash) return;
    const section = document.querySelector(window.location.hash);
    if (!section) return;
    setTimeout(() => {
      const mt = parseInt(getComputedStyle(section).scrollMarginTop || "0", 10);
      window.scrollTo({ top: section.offsetTop - mt, behavior: "smooth" });
    }, 100);
  });

  /**
   * Navmenu Scrollspy
   */
  const navmenulinks = $$(".navmenu a");
  const navmenuScrollspy = throttle(() => {
    const pos = window.scrollY + 200;
    navmenulinks.forEach((lnk) => {
      if (!lnk.hash) return;
      const sec = document.querySelector(lnk.hash);
      if (!sec) return;
      if (pos >= sec.offsetTop && pos <= sec.offsetTop + sec.offsetHeight) {
        $$(".navmenu a.active").forEach((a) => a.classList.remove("active"));
        lnk.classList.add("active");
      } else {
        lnk.classList.remove("active");
      }
    });
  }, 100);

  on(window, "load", navmenuScrollspy);
  on(document, "scroll", navmenuScrollspy, { passive: true });
})();

// ===== Optional booking form enhancements (jQuery) =====
(function ($) {
  "use strict";
  if (!$) return; // jQuery not present; safely bail

  // radio active state
  $(".form-radio .radio-item").on("click", function () {
    $(this).siblings(".radio-item").removeClass("active");
    $(this).addClass("active");
  });

  // Guard: only run if selects exist
  const $time = $("#time");
  const $food = $("#food");

  function enhanceSelect($sel, id) {
    if (!$sel.length) return;
    $sel.parent().append('<ul class="list-item" id="new_' + id + '" name="' + id + '"></ul>');
    const $ul = $("#new_" + id);
    $sel.find("option").each(function () {
      $ul.append('<li value="' + $(this).val() + '">' + $(this).text() + "</li>");
    });
    $sel.remove();
    $ul.attr("id", id);
    const $items = $ul.children("li");
    $items.first().addClass("init");

    $ul.on("click", ".init", function () {
      $(this).closest("#" + id).children("li:not(.init)").toggle();
    });

    $ul.on("click", "li:not(.init)", function () {
      $items.removeClass("selected");
      $(this).addClass("selected");
      $ul.children(".init").html($(this).html());
      $items.not(".init").toggle();
    });
  }

  enhanceSelect($time, "time");
  enhanceSelect($food, "food");
})(window.jQuery);

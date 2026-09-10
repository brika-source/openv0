/**
 * Occuo Health — progressive enhancement.
 *
 * No framework, no dependencies, ~6 KB. Every behaviour here is additive:
 * with JavaScript disabled the site still renders, navigates and reads.
 */
(function () {
  'use strict';

  const $ = (sel, ctx = document) => ctx.querySelector(sel);
  const $$ = (sel, ctx = document) => Array.from(ctx.querySelectorAll(sel));
  const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* ------------------------------------------------------------- theme --- */

  const themeToggle = $('#theme-toggle');
  const applyTheme = (mode) => {
    document.documentElement.dataset.theme = mode;
    if (themeToggle) {
      themeToggle.setAttribute(
        'aria-label',
        mode === 'dark' ? 'Switch to light theme' : 'Switch to dark theme'
      );
    }
  };
  if (themeToggle) {
    applyTheme(document.documentElement.dataset.theme || 'light');
    themeToggle.addEventListener('click', () => {
      const next = document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark';
      applyTheme(next);
      try {
        localStorage.setItem('occuo-theme', next);
      } catch (e) {
        /* private mode — the choice simply will not persist */
      }
    });
  }

  /* -------------------------------------------------- mobile navigation --- */

  const navEl = $('#primary-nav');
  const navToggle = $('#nav-toggle');
  const scrim = $('#nav-scrim');
  const isMobileNav = () => window.matchMedia('(max-width: 1080px)').matches;

  const setNav = (open) => {
    if (!navEl || !navToggle) return;
    navEl.classList.toggle('is-open', open);
    navToggle.setAttribute('aria-expanded', String(open));
    navToggle.setAttribute('aria-label', open ? 'Close menu' : 'Open menu');
    document.body.classList.toggle('is-locked', open);
    if (scrim) scrim.hidden = !open;
  };

  if (navToggle) {
    navToggle.addEventListener('click', () => setNav(navToggle.getAttribute('aria-expanded') !== 'true'));
  }
  if (scrim) scrim.addEventListener('click', () => setNav(false));

  /* ----------------------------------------------------------- mega menu --- */

  const menuToggles = $$('.nav__toggle');

  const closeMenus = (except) => {
    menuToggles.forEach((btn) => {
      if (btn === except) return;
      const panel = document.getElementById(btn.getAttribute('aria-controls'));
      btn.setAttribute('aria-expanded', 'false');
      if (panel) panel.hidden = true;
    });
  };

  menuToggles.forEach((btn) => {
    const panel = document.getElementById(btn.getAttribute('aria-controls'));
    if (!panel) return;

    const open = (state) => {
      btn.setAttribute('aria-expanded', String(state));
      panel.hidden = !state;
    };

    // A click that lands on a menu the pointer already opened should pin it
    // open, not toggle it shut — otherwise hovering then clicking dismisses it.
    let openedByHover = false;

    btn.addEventListener('click', () => {
      if (openedByHover) {
        openedByHover = false;
        open(true);
        return;
      }
      const next = btn.getAttribute('aria-expanded') !== 'true';
      closeMenus(btn);
      open(next);
    });

    // Pointer users get hover-to-open on desktop; keyboard users get click.
    const wrap = btn.closest('.nav__item');
    if (wrap) {
      wrap.addEventListener('pointerenter', (e) => {
        if (e.pointerType === 'touch' || isMobileNav()) return;
        closeMenus(btn);
        openedByHover = btn.getAttribute('aria-expanded') !== 'true';
        open(true);
      });
      wrap.addEventListener('pointerleave', (e) => {
        if (e.pointerType === 'touch' || isMobileNav()) return;
        openedByHover = false;
        open(false);
      });
    }
  });

  document.addEventListener('keydown', (e) => {
    if (e.key !== 'Escape') return;
    closeMenus();
    if (navEl && navEl.classList.contains('is-open')) {
      setNav(false);
      navToggle.focus();
    }
  });

  document.addEventListener('click', (e) => {
    if (isMobileNav()) return;
    if (!e.target.closest('.nav__item--has-menu')) closeMenus();
  });

  // Reset nav state when crossing the breakpoint so the desktop layout never
  // inherits a stuck "open" class from the drawer.
  let wasMobile = isMobileNav();
  window.addEventListener('resize', () => {
    const now = isMobileNav();
    if (now !== wasMobile) {
      wasMobile = now;
      setNav(false);
      closeMenus();
    }
  });

  /* --------------------------------------------- sticky header + progress --- */

  const header = $('#header');
  const progress = $('#scroll-progress');

  const onScroll = () => {
    const y = window.scrollY;
    if (header) header.classList.toggle('is-stuck', y > 8);
    if (progress) {
      const max = document.documentElement.scrollHeight - window.innerHeight;
      progress.style.width = max > 0 ? `${Math.min((y / max) * 100, 100)}%` : '0%';
    }
  };
  window.addEventListener('scroll', onScroll, { passive: true });
  onScroll();

  /* ---------------------------------------------------------- accordions --- */

  $$('[data-accordion]').forEach((group) => {
    $$('.accordion__trigger', group).forEach((trigger) => {
      const panel = document.getElementById(trigger.getAttribute('aria-controls'));
      if (!panel) return;
      trigger.addEventListener('click', () => {
        const open = trigger.getAttribute('aria-expanded') === 'true';
        // Single-open behaviour keeps long FAQ lists scannable.
        $$('.accordion__trigger', group).forEach((other) => {
          const otherPanel = document.getElementById(other.getAttribute('aria-controls'));
          other.setAttribute('aria-expanded', 'false');
          if (otherPanel) otherPanel.hidden = true;
        });
        trigger.setAttribute('aria-expanded', String(!open));
        panel.hidden = open;
      });
    });
  });

  /* ------------------------------------------------------ reveal on scroll --- */

  const revealables = $$('[data-reveal]');
  if (reduceMotion || !('IntersectionObserver' in window)) {
    revealables.forEach((el) => el.classList.add('is-visible'));
  } else {
    const io = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (!entry.isIntersecting) return;
          entry.target.classList.add('is-visible');
          io.unobserve(entry.target);
        });
      },
      { rootMargin: '0px 0px -8% 0px', threshold: 0.06 }
    );
    revealables.forEach((el) => io.observe(el));
  }

  /* ----------------------------------------------------------- counters --- */

  // The markup ships the real figure so it is correct without JavaScript.
  // Only once we know we can animate do we reset to zero and count up.
  const counters = $$('[data-count]');
  if (counters.length && !reduceMotion && 'IntersectionObserver' in window) {
    counters.forEach((el) => {
      el.textContent = '0';
    });

    const run = (el) => {
      const target = parseFloat(el.dataset.count);
      const duration = 1100;
      const start = performance.now();
      const tick = (now) => {
        const p = Math.min((now - start) / duration, 1);
        if (p >= 1) {
          // Snap to the exact figure: easing alone lands a whisker short and
          // "11,999" is not the number anyone signed off.
          el.textContent = target.toLocaleString('en-GB');
          return;
        }
        const eased = 1 - Math.pow(1 - p, 3);
        el.textContent = Math.round(target * eased).toLocaleString('en-GB');
        requestAnimationFrame(tick);
      };
      requestAnimationFrame(tick);
    };

    const co = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (!entry.isIntersecting) return;
          run(entry.target);
          co.unobserve(entry.target);
        });
      },
      { threshold: 0.4 }
    );
    counters.forEach((el) => co.observe(el));
  }

  /* -------------------------------------------------------- TOC scrollspy --- */

  const tocLinks = $$('.toc a[href^="#"]');
  if (tocLinks.length && 'IntersectionObserver' in window) {
    const targets = tocLinks
      .map((a) => document.getElementById(decodeURIComponent(a.hash.slice(1))))
      .filter(Boolean);

    const spy = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (!entry.isIntersecting) return;
          tocLinks.forEach((a) =>
            a.classList.toggle('is-current', decodeURIComponent(a.hash.slice(1)) === entry.target.id)
          );
        });
      },
      { rootMargin: '-20% 0px -70% 0px' }
    );
    targets.forEach((t) => spy.observe(t));
  }

  /* --------------------------------------------------- form: client checks --- */

  const form = $('#enquiry-form');
  if (form) {
    const status = $('#form-status', form) || $('#form-status');

    const setError = (field, message) => {
      const wrap = field.closest('.field');
      if (!wrap) return;
      const slot = $('.field__error', wrap);
      if (message) {
        wrap.setAttribute('data-invalid', '');
        field.setAttribute('aria-invalid', 'true');
      } else {
        wrap.removeAttribute('data-invalid');
        field.removeAttribute('aria-invalid');
      }
      if (slot) slot.textContent = message || '';
    };

    const validate = (field) => {
      if (!field.willValidate) return true;
      const ok = field.checkValidity();
      let message = '';
      if (!ok) {
        if (field.validity.valueMissing) message = 'This field is required.';
        else if (field.validity.typeMismatch && field.type === 'email') message = 'Enter a valid email address.';
        else if (field.validity.tooShort) message = `Please enter at least ${field.minLength} characters.`;
        else message = 'Please check this field.';
      }
      setError(field, message);
      return ok;
    };

    $$('input, select, textarea', form).forEach((field) => {
      field.addEventListener('blur', () => validate(field));
      field.addEventListener('input', () => {
        if (field.closest('.field')?.hasAttribute('data-invalid')) validate(field);
      });
    });

    form.addEventListener('submit', (e) => {
      const fields = $$('input, select, textarea', form);
      const invalid = fields.filter((f) => !validate(f));

      // Honeypot: bots fill hidden fields, humans never see them.
      const trap = $('input[name="company_website"]', form);
      if (trap && trap.value) {
        e.preventDefault();
        return;
      }

      if (invalid.length) {
        e.preventDefault();
        invalid[0].focus();
        if (status) {
          status.hidden = false;
          status.classList.add('form__status--error');
          status.textContent = 'Please correct the highlighted fields before sending.';
        }
        return;
      }

      // No backend is wired up yet. Until an endpoint is configured in
      // data-endpoint, fall back to composing an email so enquiries are
      // never silently lost. See README § "Wiring up the contact form".
      if (!form.dataset.endpoint) {
        e.preventDefault();
        const get = (name) => (form.elements[name]?.value || '').trim();
        const body = [
          `Name: ${get('name')}`,
          `Company: ${get('company')}`,
          `Email: ${get('email')}`,
          `Phone: ${get('phone')}`,
          `Employees on site: ${get('headcount')}`,
          `Service of interest: ${get('service')}`,
          '',
          get('message'),
        ].join('\n');
        const to = form.dataset.mailto || 'info@occuohealtheg.com';
        window.location.href =
          `mailto:${to}?subject=${encodeURIComponent('Proposal request — ' + (get('company') || get('name')))}` +
          `&body=${encodeURIComponent(body)}`;
        if (status) {
          status.hidden = false;
          status.classList.remove('form__status--error');
          status.textContent = 'Opening your email client with the enquiry ready to send.';
        }
      }
    });
  }

  /* --------------------------------------------------------------- misc --- */

  $$('[data-current-year]').forEach((el) => {
    el.textContent = String(new Date().getFullYear());
  });

  // Close the drawer after tapping an in-page link.
  $$('.nav a[href]').forEach((a) => {
    a.addEventListener('click', () => {
      if (isMobileNav()) setNav(false);
    });
  });
})();

/* =====================================================================
   Progressive enhancement only.

   The application is fully server-rendered and every screen works with
   JavaScript disabled: forms submit, links navigate, decisions are
   recorded. This file adds convenience on top — it never carries
   business logic and never gates a workflow.

   The Content-Security-Policy forbids inline handlers, so everything
   here binds by data attribute.
===================================================================== */
(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {
    autoDismissToasts();
    wireConfirmations();
    wireFilterCounts();
    wireDecisionPanels();
    wireSubmitGuards();
    wirePrintButtons();
  });

  /* ---- toasts fade out on their own ---- */
  function autoDismissToasts() {
    document.querySelectorAll('.toast-wrap .toast').forEach(function (toast, index) {
      window.setTimeout(function () {
        toast.style.transition = 'opacity .3s ease, transform .3s ease';
        toast.style.opacity = '0';
        toast.style.transform = 'translateX(24px)';
        window.setTimeout(function () { toast.remove(); }, 320);
      }, 4200 + index * 350);
    });
  }

  /* ---- data-confirm="message" on a form or link ---- */
  function wireConfirmations() {
    document.querySelectorAll('[data-confirm]').forEach(function (el) {
      var handler = function (event) {
        if (!window.confirm(el.getAttribute('data-confirm'))) {
          event.preventDefault();
          event.stopPropagation();
        }
      };
      el.addEventListener(el.tagName === 'FORM' ? 'submit' : 'click', handler);
    });
  }

  /* ---- live "n selected" counters beside the report filter lists ---- */
  function wireFilterCounts() {
    document.querySelectorAll('[data-count-group]').forEach(function (group) {
      var name = group.getAttribute('data-count-group');
      var badge = document.getElementById('cnt_' + name);
      if (!badge) { return; }

      var update = function () {
        var checked = group.querySelectorAll('input[type=checkbox]:checked').length;
        badge.textContent = checked > 0 ? String(checked) : '';
      };

      group.addEventListener('change', update);
      update();
    });
  }

  /* ---- reveal the panel a decision button belongs to ---- */
  function wireDecisionPanels() {
    var panels = document.querySelectorAll('[data-panel]');

    document.querySelectorAll('[data-show-panel]').forEach(function (button) {
      button.addEventListener('click', function () {
        var target = button.getAttribute('data-show-panel');

        panels.forEach(function (panel) {
          panel.hidden = panel.getAttribute('data-panel') !== target;
        });

        var shown = document.querySelector('[data-panel="' + target + '"]');
        if (shown) {
          var field = shown.querySelector('textarea, input:not([type=hidden]), select');
          if (field) { field.focus(); }
        }
      });
    });
  }

  /* ---- stop double submits, which would double-post a decision ---- */
  function wireSubmitGuards() {
    document.querySelectorAll('form[data-guard]').forEach(function (form) {
      form.addEventListener('submit', function () {
        var buttons = form.querySelectorAll('button[type=submit]');
        window.setTimeout(function () {
          buttons.forEach(function (button) { button.disabled = true; });
        }, 0);
      });
    });
  }

  function wirePrintButtons() {
    document.querySelectorAll('[data-print]').forEach(function (button) {
      button.addEventListener('click', function () { window.print(); });
    });
  }
})();

/* Mini CRM admin UI behaviour. No build step; plain ES2017. */
(function () {
    'use strict';

    var root = document.documentElement;

    function store(key, value) {
        try {
            if (value === undefined) return localStorage.getItem(key);
            localStorage.setItem(key, value);
        } catch (e) { return null; }
    }

    /* ---------- Theme (light / dark) ---------- */
    function applyTheme(theme) {
        root.setAttribute('data-bs-theme', theme);
        document.querySelectorAll('[data-theme-icon]').forEach(function (el) {
            el.className = theme === 'dark' ? 'bi bi-sun' : 'bi bi-moon-stars';
        });
        document.dispatchEvent(new CustomEvent('crm:themechange', { detail: { theme: theme } }));
    }

    document.addEventListener('click', function (e) {
        if (!e.target.closest('[data-action="toggle-theme"]')) return;
        var next = root.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
        store('crm-theme', next);
        applyTheme(next);
    });

    /* ---------- Sidebar (mini on desktop, off-canvas on mobile) ---------- */
    var desktop = window.matchMedia('(min-width: 992px)');

    document.addEventListener('click', function (e) {
        if (e.target.closest('[data-action="toggle-sidebar"]')) {
            if (desktop.matches) {
                var mini = root.classList.toggle('sidebar-mini');
                store('crm-sidebar-mini', mini ? '1' : '0');
            } else {
                document.body.classList.toggle('sidebar-open');
            }
        }
        if (e.target.closest('.sidebar-backdrop')) {
            document.body.classList.remove('sidebar-open');
        }
    });

    /* ---------- Confirmation modal for forms with data-confirm ---------- */
    var pendingForm = null;

    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!form.dataset.confirm || form.dataset.confirmed === '1') return;

        e.preventDefault();
        var modalEl = document.getElementById('confirmModal');
        if (!modalEl || !window.bootstrap) {
            if (window.confirm(form.dataset.confirm)) { form.dataset.confirmed = '1'; form.submit(); }
            return;
        }

        var variant = form.dataset.confirmVariant || 'danger';
        modalEl.querySelector('[data-confirm-title]').textContent = form.dataset.confirmTitle || 'Are you sure?';
        modalEl.querySelector('[data-confirm-message]').textContent = form.dataset.confirm;
        var icon = modalEl.querySelector('[data-confirm-icon]');
        icon.className = 'modal-icon mx-auto tone-' + variant;
        icon.innerHTML = '<i class="bi ' + (form.dataset.confirmIcon || 'bi-exclamation-triangle') + '"></i>';
        var btn = modalEl.querySelector('[data-confirm-ok]');
        btn.className = 'btn btn-' + (variant === 'danger' ? 'danger' : variant) + ' flex-fill';
        btn.textContent = form.dataset.confirmButton || 'Confirm';

        pendingForm = form;
        bootstrap.Modal.getOrCreateInstance(modalEl).show();
    });

    document.addEventListener('click', function (e) {
        if (!e.target.closest('[data-confirm-ok]') || !pendingForm) return;
        var form = pendingForm;
        pendingForm = null;
        form.dataset.confirmed = '1';
        e.target.closest('[data-confirm-ok]').disabled = true;
        form.submit();
    });

    /* ---------- Prevent double submits ---------- */
    document.addEventListener('submit', function (e) {
        if (e.defaultPrevented || e.target.method.toLowerCase() === 'get') return;
        var btn = e.target.querySelector('button[type="submit"]:not([data-no-loading])');
        if (btn) {
            setTimeout(function () {
                btn.disabled = true;
                btn.insertAdjacentHTML('afterbegin', '<span class="spinner-border spinner-border-sm" aria-hidden="true"></span>');
            }, 0);
        }
    });

    // Re-enable buttons when the page is restored from the back/forward cache.
    window.addEventListener('pageshow', function (e) {
        if (!e.persisted) return;
        document.querySelectorAll('button[type="submit"][disabled]').forEach(function (btn) {
            btn.disabled = false;
            var spinner = btn.querySelector('.spinner-border');
            if (spinner) spinner.remove();
        });
    });

    /* ---------- Login helpers (no inline handlers: the CSP forbids them) ---------- */
    document.addEventListener('click', function (e) {
        var toggle = e.target.closest('[data-action="toggle-password"]');
        if (toggle) {
            var input = document.getElementById(toggle.dataset.target);
            var show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            toggle.querySelector('i').className = show ? 'bi bi-eye-slash' : 'bi bi-eye';
            toggle.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
        }

        var demo = e.target.closest('[data-action="fill-demo"]');
        if (demo) {
            document.getElementById('email').value = demo.dataset.email;
            document.getElementById('password').value = demo.dataset.password;
        }

        if (e.target.closest('[data-action="go-back"]')) {
            e.preventDefault();
            history.length > 1 ? history.back() : (window.location.href = '/dashboard');
        }
    });

    /* ---------- Auto-submit filters ---------- */
    document.addEventListener('change', function (e) {
        if (e.target.matches('[data-autosubmit]')) e.target.form.submit();
    });

    /* ---------- Keyboard: "/" focuses the global search ---------- */
    document.addEventListener('keydown', function (e) {
        if (e.key !== '/' || /input|textarea|select/i.test(document.activeElement.tagName)) return;
        var search = document.getElementById('globalSearch');
        if (search) { e.preventDefault(); search.focus(); }
    });

    /* ---------- Boot ---------- */
    document.addEventListener('DOMContentLoaded', function () {
        applyTheme(root.getAttribute('data-bs-theme') || 'light');

        if (!window.bootstrap) return;
        document.querySelectorAll('.toast').forEach(function (el) {
            bootstrap.Toast.getOrCreateInstance(el, { delay: 5000 }).show();
        });
        document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
            bootstrap.Tooltip.getOrCreateInstance(el);
        });
        // Sidebar labels are hidden in mini mode, so only then show them as tooltips.
        document.querySelectorAll('[data-sidebar-tip]').forEach(function (el) {
            bootstrap.Tooltip.getOrCreateInstance(el, { placement: 'right' });
            el.addEventListener('show.bs.tooltip', function (e) {
                if (!root.classList.contains('sidebar-mini') || !desktop.matches) e.preventDefault();
            });
        });
    });
})();

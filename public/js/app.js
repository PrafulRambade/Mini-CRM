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
        if (!e.target.matches('[data-autosubmit]')) return;
        var form = e.target.form;
        // requestSubmit fires the submit event, so AJAX listings can intercept it.
        if (form.requestSubmit) form.requestSubmit(); else form.submit();
    });

    /* ---------- Toasts created from JS ---------- */
    function showToast(message, tone) {
        var container = document.querySelector('.toast-container');
        if (!container || !window.bootstrap) { window.alert(message); return; }
        var el = document.createElement('div');
        el.className = 'toast';
        el.setAttribute('role', 'alert');
        el.innerHTML = '<div class="toast-body"><span class="toast-icon tone-' + (tone || 'danger') + '"><i class="bi bi-exclamation-lg"></i></span>' +
            '<div class="flex-fill text-2 small"></div><button type="button" class="btn-close btn-sm" data-bs-dismiss="toast" aria-label="Close"></button></div>';
        el.querySelector('.flex-fill').textContent = message; // textContent: never interpreted as HTML
        container.appendChild(el);
        bootstrap.Toast.getOrCreateInstance(el, { delay: 5000 }).show();
        el.addEventListener('hidden.bs.toast', function () { el.remove(); });
    }

    function initTooltips(scope) {
        if (!window.bootstrap) return;
        scope.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
            bootstrap.Tooltip.getOrCreateInstance(el);
        });
    }

    /* ---------- AJAX listings ----------
     * Filters, search, sorting and paging are sent as a CSRF-protected POST body to
     * the region's data-endpoint; the server answers with Blade-rendered (escaped)
     * HTML for the region. Nothing ends up in the address bar or server access logs,
     * and filter state lives in history.state so Back/Forward and Refresh keep it.
     */
    function initListing(root) {
        var endpoint = root.dataset.endpoint;
        var csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        var inflight = null;
        var searchTimer = null;
        var current = {};

        function fromUrl(href) {
            var params = {};
            new URL(href, window.location.href).searchParams.forEach(function (v, k) { if (v !== '') params[k] = v; });
            return params;
        }

        function fromForm(form) {
            var params = {};
            new FormData(form).forEach(function (v, k) { if (typeof v === 'string' && v !== '') params[k] = v; });
            return params;
        }

        function load(params, historyMode) {
            if (inflight) inflight.abort();          // newest request wins; no out-of-order results
            inflight = new AbortController();

            // Remember focus/caret (e.g. while typing in search) to restore after re-render.
            var active = document.activeElement;
            var focusName = active && root.contains(active) && active.name ? active.name : null;
            var caret = focusName && typeof active.selectionStart === 'number' ? active.selectionStart : null;

            var body = new FormData();
            Object.keys(params).forEach(function (k) { body.append(k, params[k]); });

            root.classList.add('is-loading');
            root.setAttribute('aria-busy', 'true');

            fetch(endpoint, {
                method: 'POST',
                body: body,
                credentials: 'same-origin',
                signal: inflight.signal,
                headers: { 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' }
            }).then(function (res) {
                // Session expired / signed out / deactivated: let the server decide where to go.
                if (res.status === 419 || res.status === 401) { window.location.reload(); return null; }
                if (res.redirected) { window.location.href = res.url; return null; }
                if (res.status === 429) throw new Error('Too many requests. Please slow down for a moment.');
                if (!res.ok) throw new Error('Could not load results (error ' + res.status + '). Please try again.');
                return res.text();
            }).then(function (html) {
                if (html === null) return;
                root.innerHTML = html; // server-rendered and escaped; scripts in it would not execute
                current = params;

                var state = { listing: params };
                if (historyMode === 'push') history.pushState(state, '', window.location.pathname);
                else history.replaceState(state, '', window.location.pathname);

                initTooltips(root);
                if (focusName) {
                    var field = root.querySelector('[name="' + focusName + '"]');
                    if (field) {
                        field.focus();
                        if (caret !== null && field.setSelectionRange) { try { field.setSelectionRange(caret, caret); } catch (e) {} }
                    }
                }
                if (historyMode === 'push' && root.getBoundingClientRect().top < 0) root.scrollIntoView({ behavior: 'smooth' });
            }).catch(function (err) {
                if (err.name !== 'AbortError') showToast(err.message || 'Could not load results.');
            }).finally(function () {
                root.classList.remove('is-loading');
                root.removeAttribute('aria-busy');
            });
        }

        // Tabs, sort headers, filter chips, pagination, "Clear": same-page links become AJAX loads.
        root.addEventListener('click', function (e) {
            var link = e.target.closest('a[href]');
            if (!link || e.defaultPrevented || e.button !== 0 || e.ctrlKey || e.metaKey || e.shiftKey || e.altKey) return;
            var url = new URL(link.href, window.location.href);
            if (url.origin !== window.location.origin || url.pathname !== window.location.pathname) return; // row links etc.
            e.preventDefault();
            load(fromUrl(link.href), 'push');
        });

        // Filter bar and rows-per-page forms.
        root.addEventListener('submit', function (e) {
            var form = e.target;
            if ((form.getAttribute('method') || 'get').toLowerCase() !== 'get') return; // delete/toggle forms stay normal
            e.preventDefault();
            clearTimeout(searchTimer);
            load(withPageSize(fromForm(form)), 'push');
        });

        // New filters start from page 1 but keep the chosen rows-per-page.
        function withPageSize(params) {
            delete params.page;
            if (current.per_page && !params.per_page) params.per_page = current.per_page;
            return params;
        }

        // Search as you type (debounced; replaces the history entry instead of adding one per keystroke).
        root.addEventListener('input', function (e) {
            if (!e.target.matches('input[name="search"]')) return;
            clearTimeout(searchTimer);
            var form = e.target.form;
            searchTimer = setTimeout(function () {
                load(withPageSize(fromForm(form)), 'replace');
            }, 350);
        });

        // Back / Forward between filter states.
        window.addEventListener('popstate', function (e) {
            load(e.state && e.state.listing ? e.state.listing : {}, 'none');
        });

        // First load: move any incoming query (dashboard links, top-bar search) out of the URL,
        // or restore the state saved in history (Refresh / coming back from a detail page).
        var initial = fromUrl(window.location.href);
        if (Object.keys(initial).length) {
            current = initial;
            history.replaceState({ listing: initial }, '', window.location.pathname);
        } else if (history.state && history.state.listing && Object.keys(history.state.listing).length) {
            load(history.state.listing, 'none');
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-ajax-listing][data-endpoint]').forEach(initListing);
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

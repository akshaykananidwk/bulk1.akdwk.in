/**
 * Krishna WhatsApp Cloud — core front-end helpers.
 * Vanilla JS + Alpine.js (vendored). No build step.
 */
(function () {
    'use strict';

    // -- CSRF-aware fetch helper ---------------------------------------------
    window.kwc = window.kwc || {};

    kwc.csrf = function () {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    };

    kwc.fetch = function (url, options) {
        options = options || {};
        options.headers = Object.assign({
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-Token': kwc.csrf(),
            'Accept': 'application/json'
        }, options.headers || {});
        if (options.json) {
            options.headers['Content-Type'] = 'application/json';
            options.body = JSON.stringify(options.json);
            options.method = options.method || 'POST';
            delete options.json;
        }
        return fetch(url, options).then(function (response) {
            if (response.status === 419) {
                kwc.toast('Session expired — refreshing…', 'danger');
                setTimeout(function () { window.location.reload(); }, 1200);
            }
            return response.json().catch(function () { return {}; }).then(function (data) {
                return { ok: response.ok, status: response.status, data: data };
            });
        });
    };

    // -- Toasts ---------------------------------------------------------------
    kwc.toast = function (message, type, timeout) {
        var stack = document.querySelector('.toast-stack');
        if (!stack) {
            stack = document.createElement('div');
            stack.className = 'toast-stack';
            document.body.appendChild(stack);
        }
        var el = document.createElement('div');
        el.className = 'toast toast-' + (type || 'info');
        el.setAttribute('role', 'status');
        el.textContent = message;
        stack.appendChild(el);
        setTimeout(function () {
            el.style.opacity = '0';
            el.style.transition = 'opacity .25s';
            setTimeout(function () { el.remove(); }, 300);
        }, timeout || 4000);
    };

    // -- Confirm dialogs (SweetAlert2 when present) ---------------------------
    kwc.confirm = function (title, text) {
        if (window.Swal) {
            return Swal.fire({
                title: title,
                text: text || '',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#0F766E',
                cancelButtonColor: '#94a3b8',
                confirmButtonText: 'Yes, continue'
            }).then(function (r) { return r.isConfirmed; });
        }
        return Promise.resolve(window.confirm(title + (text ? '\n' + text : '')));
    };

    // Forms marked data-confirm ask before submitting
    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (form.matches('form[data-confirm]') && !form.__confirmed) {
            e.preventDefault();
            kwc.confirm(form.getAttribute('data-confirm')).then(function (ok) {
                if (ok) {
                    form.__confirmed = true;
                    form.submit();
                }
            });
        }
    });

    // -- Dark mode -------------------------------------------------------------
    kwc.toggleDark = function () {
        var dark = document.documentElement.classList.toggle('dark');
        try { localStorage.setItem('kwc_dark', dark ? '1' : '0'); } catch (e) { /* private mode */ }
        kwc.fetch('/profile/dark-mode', { method: 'POST', json: { dark: dark ? 1 : 0 } });
    };
    (function initDark() {
        try {
            if (localStorage.getItem('kwc_dark') === '1') {
                document.documentElement.classList.add('dark');
            }
        } catch (e) { /* ignore */ }
    })();

    // -- Sidebar (mobile) -------------------------------------------------------
    document.addEventListener('click', function (e) {
        var toggle = e.target.closest('[data-sidebar-toggle]');
        if (toggle) {
            var sidebar = document.querySelector('.sidebar');
            if (sidebar) { sidebar.classList.toggle('open'); }
        }
    });

    // -- Copy to clipboard ------------------------------------------------------
    document.addEventListener('click', function (e) {
        var button = e.target.closest('[data-copy]');
        if (!button) { return; }
        var value = button.getAttribute('data-copy');
        (navigator.clipboard ? navigator.clipboard.writeText(value) : Promise.reject())
            .then(function () { kwc.toast('Copied to clipboard', 'success', 1600); })
            .catch(function () {
                var input = document.createElement('textarea');
                input.value = value;
                document.body.appendChild(input);
                input.select();
                document.execCommand('copy');
                input.remove();
                kwc.toast('Copied to clipboard', 'success', 1600);
            });
    });

    // -- Auto-dismiss flash alerts ---------------------------------------------
    document.querySelectorAll('.alert[data-auto-dismiss]').forEach(function (el) {
        setTimeout(function () {
            el.style.transition = 'opacity .4s';
            el.style.opacity = '0';
            setTimeout(function () { el.remove(); }, 450);
        }, 6000);
    });

    // -- Relative timestamps (data-ts="unix") -----------------------------------
    kwc.timeAgo = function (unix) {
        var diff = Math.floor(Date.now() / 1000) - unix;
        if (diff < 60) { return 'just now'; }
        if (diff < 3600) { return Math.floor(diff / 60) + 'm ago'; }
        if (diff < 86400) { return Math.floor(diff / 3600) + 'h ago'; }
        return Math.floor(diff / 86400) + 'd ago';
    };

    // -- Keyboard shortcuts ------------------------------------------------------
    document.addEventListener('keydown', function (e) {
        if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
            var search = document.querySelector('[data-global-search]');
            if (search) {
                e.preventDefault();
                search.focus();
            }
        }
    });
})();

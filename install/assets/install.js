/** Installer client logic: AJAX steps, import progress, cron test. */
(function () {
    'use strict';

    function post(action, data) {
        var form = new FormData();
        form.append('_token', window.INSTALL_CSRF);
        Object.keys(data || {}).forEach(function (key) { form.append(key, data[key]); });
        return fetch('./?action=' + action, { method: 'POST', body: form })
            .then(function (r) { return r.json(); });
    }

    function show(id, cls, msg) {
        var el = document.getElementById(id);
        if (!el) { return; }
        el.className = 'alert ' + cls;
        el.textContent = msg;
        el.style.display = 'block';
    }

    // -- Step: database ------------------------------------------------------
    var testBtn = document.getElementById('test-db-btn');
    if (testBtn) {
        testBtn.addEventListener('click', function () {
            testBtn.disabled = true;
            testBtn.textContent = 'Testing…';
            post('test-db', {
                host: document.getElementById('db-host').value,
                port: document.getElementById('db-port').value,
                database: document.getElementById('db-name').value,
                username: document.getElementById('db-user').value,
                password: document.getElementById('db-pass').value,
                prefix: document.getElementById('db-prefix').value
            }).then(function (d) {
                testBtn.disabled = false;
                testBtn.textContent = 'Test Connection';
                if (d.success) {
                    var extra = d.existing_tables > 0
                        ? ' ⚠️ ' + d.existing_tables + ' existing tables found — importing will keep them but may conflict. Use an empty database for a fresh install.'
                        : '';
                    show('db-result', d.existing_tables > 0 ? 'info' : 'success', d.message + extra);
                    document.getElementById('db-continue').style.display = 'inline-flex';
                } else {
                    show('db-result', 'error', d.message);
                    document.getElementById('db-continue').style.display = 'none';
                }
            });
        });
    }

    // -- Step: import ---------------------------------------------------------
    var importBtn = document.getElementById('import-btn');
    if (importBtn) {
        importBtn.addEventListener('click', function () {
            importBtn.disabled = true;
            var bar = document.getElementById('import-bar');
            var label = document.getElementById('import-label');

            function chunk(offset) {
                post('import-chunk', { offset: offset }).then(function (d) {
                    if (!d.success) {
                        show('import-result', 'error', d.message + ' — fix and click Start Import to resume.');
                        importBtn.disabled = false;
                        importBtn.textContent = 'Resume Import';
                        return;
                    }
                    var pct = d.total > 0 ? Math.round(d.offset / d.total * 100) : 100;
                    bar.style.width = pct + '%';
                    label.textContent = d.offset + ' / ' + d.total + ' statements (' + (d.file || '') + ')';
                    if (d.done) {
                        show('import-result', 'success', '✅ Schema and seed data imported successfully.');
                        document.getElementById('import-continue').style.display = 'inline-flex';
                    } else {
                        chunk(d.offset);
                    }
                }).catch(function () {
                    show('import-result', 'error', 'Network error — click Start Import to resume where it stopped.');
                    importBtn.disabled = false;
                });
            }
            chunk(parseInt(importBtn.getAttribute('data-offset') || '0', 10));
        });
    }

    // -- Step: admin ------------------------------------------------------------
    var adminForm = document.getElementById('admin-form');
    if (adminForm) {
        adminForm.addEventListener('submit', function (e) {
            e.preventDefault();
            post('save-admin', {
                name: document.getElementById('admin-name').value,
                email: document.getElementById('admin-email').value,
                phone: document.getElementById('admin-phone').value,
                password: document.getElementById('admin-pass').value,
                timezone: document.getElementById('admin-tz').value
            }).then(function (d) {
                if (d.success) { window.location = './?step=settings'; }
                else { show('admin-result', 'error', d.message); }
            });
        });
        var pw = document.getElementById('admin-pass');
        pw.addEventListener('input', function () {
            var v = pw.value, s = 0;
            if (v.length >= 8) s++;
            if (/[A-Z]/.test(v)) s++;
            if (/[0-9]/.test(v)) s++;
            if (/[^A-Za-z0-9]/.test(v)) s++;
            var bar = document.getElementById('pw-bar');
            bar.style.width = (s * 25) + '%';
            bar.style.background = s < 2 ? '#EF4444' : (s < 3 ? '#F59E0B' : '#10B981');
        });
    }

    // -- Step: settings -----------------------------------------------------------
    var settingsForm = document.getElementById('settings-form');
    if (settingsForm) {
        settingsForm.addEventListener('submit', function (e) {
            e.preventDefault();
            post('save-settings', {
                app_name: document.getElementById('set-name').value,
                app_url: document.getElementById('set-url').value,
                locale: document.getElementById('set-locale').value,
                currency: document.getElementById('set-currency').value,
                date_format: document.getElementById('set-dateformat').value
            }).then(function (d) {
                if (d.success) { window.location = './?step=cron'; }
                else { show('settings-result', 'error', d.message); }
            });
        });
    }

    // -- Step: cron ----------------------------------------------------------------
    var cronBtn = document.getElementById('cron-test-btn');
    if (cronBtn) {
        cronBtn.addEventListener('click', function () {
            cronBtn.disabled = true;
            post('cron-status', {}).then(function (d) {
                cronBtn.disabled = false;
                if (d.running) {
                    show('cron-result', 'success', '✅ Cron is running (last heartbeat ' + d.age + 's ago).');
                } else {
                    show('cron-result', 'info', d.age === null
                        ? 'No heartbeat yet — the scheduler has not run. Add the cron entry and wait up to a minute, then re-test. You can also finish now and set up cron later.'
                        : 'Last heartbeat was ' + d.age + 's ago — cron seems stopped.');
                }
            });
        });
    }

    // -- Step: finish -----------------------------------------------------------------
    var finishBtn = document.getElementById('finish-btn');
    if (finishBtn) {
        finishBtn.addEventListener('click', function () {
            finishBtn.disabled = true;
            finishBtn.textContent = 'Finalizing…';
            var mode = document.querySelector('input[name="worker_mode"]:checked');
            post('finalize', { worker_mode: mode ? mode.value : 'cron' }).then(function (d) {
                if (d.success) {
                    document.getElementById('finish-pre').style.display = 'none';
                    document.getElementById('finish-done').style.display = 'block';
                    document.getElementById('login-link').href = d.login_url;
                    document.getElementById('install-id').textContent = d.installation_id;
                } else {
                    show('finish-result', 'error', d.message);
                    finishBtn.disabled = false;
                    finishBtn.textContent = 'Install Now';
                }
            });
        });
    }

    // -- Copy buttons -------------------------------------------------------------------
    document.querySelectorAll('.copy-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var text = btn.parentElement.textContent.replace('Copy', '').trim();
            (navigator.clipboard ? navigator.clipboard.writeText(text) : Promise.reject()).then(function () {
                btn.textContent = '✓ Copied';
                setTimeout(function () { btn.textContent = 'Copy'; }, 1500);
            });
        });
    });
})();

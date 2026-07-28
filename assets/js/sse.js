/**
 * Real-time client: SSE primary, AJAX polling fallback.
 * Usage:
 *   kwcRealtime.connect(['inbox', 'notifications']);
 *   kwcRealtime.on('message.new', function (payload) { ... });
 */
(function () {
    'use strict';

    var listeners = {};
    var lastEventId = 0;
    var source = null;
    var pollTimer = null;
    var channels = [];
    var mode = null; // 'sse' | 'poll'

    function emit(type, payload) {
        (listeners[type] || []).forEach(function (callback) {
            try { callback(payload); } catch (e) { console.error('kwcRealtime listener error', e); }
        });
        (listeners['*'] || []).forEach(function (callback) {
            try { callback(type, payload); } catch (e) { console.error(e); }
        });
    }

    function handleEvent(raw) {
        var data;
        try { data = JSON.parse(raw); } catch (e) { return; }
        if (data.id && data.id > lastEventId) { lastEventId = data.id; }
        emit(data.type, data.payload || {});
    }

    function startSse() {
        mode = 'sse';
        var url = '/sse/stream?channels=' + encodeURIComponent(channels.join(','))
            + (lastEventId ? '&last_id=' + lastEventId : '');
        source = new EventSource(url);
        source.onmessage = function (e) {
            if (e.lastEventId) { lastEventId = parseInt(e.lastEventId, 10) || lastEventId; }
            handleEvent(e.data);
        };
        source.onerror = function () {
            // EventSource auto-reconnects; if it closes hard, fall back to polling
            if (source.readyState === EventSource.CLOSED) {
                try { localStorage.setItem('kwc_rt_mode', 'poll'); } catch (err) { /* ignore */ }
                source = null;
                startPolling();
            }
        };
    }

    function startPolling() {
        mode = 'poll';
        if (pollTimer) { return; }
        var poll = function () {
            fetch('/sse/poll?channels=' + encodeURIComponent(channels.join(',')) + '&last_id=' + lastEventId, {
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
            }).then(function (r) { return r.json(); }).then(function (data) {
                (data.events || []).forEach(function (event) {
                    if (event.id > lastEventId) { lastEventId = event.id; }
                    emit(event.type, event.payload || {});
                });
            }).catch(function () { /* transient network error — next tick retries */ });
        };
        poll();
        pollTimer = setInterval(poll, 3000);
    }

    window.kwcRealtime = {
        connect: function (channelList) {
            channels = channelList || ['notifications'];
            var preferred = null;
            try { preferred = localStorage.getItem('kwc_rt_mode'); } catch (e) { /* ignore */ }
            if (window.EventSource && preferred !== 'poll') {
                startSse();
            } else {
                startPolling();
            }
            // Presence ping every 60s
            setInterval(function () {
                fetch('/presence/ping', {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-Token': (window.kwc ? kwc.csrf() : '')
                    }
                }).catch(function () { /* offline */ });
            }, 60000);
        },
        on: function (type, callback) {
            (listeners[type] = listeners[type] || []).push(callback);
        },
        mode: function () { return mode; },
        disconnect: function () {
            if (source) { source.close(); source = null; }
            if (pollTimer) { clearInterval(pollTimer); pollTimer = null; }
        }
    };
})();

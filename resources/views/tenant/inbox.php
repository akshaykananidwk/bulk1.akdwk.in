<?php
use App\Core\Layout;
use App\Core\View;

Layout::title(__('inbox.title', 'Team Inbox'));
?>
<?php View::start('content'); ?>
<style>
    /* Inbox takes over the content area completely */
    .content { padding: 0 !important; max-width: none !important; }
</style>

<div class="inbox-shell" id="inbox-app"
     x-data="inboxApp()"
     x-init="init()"
     :class="{ 'chat-open': current !== null }">

    <!-- Pane 1: conversation list -->
    <div class="inbox-list">
        <div class="inbox-list-header">
            <input class="input" type="search" x-model.debounce.400ms="search" x-on:input="loadList()"
                   placeholder="<?= e(__('inbox.search', 'Search…')) ?>" data-global-search>
            <div class="inbox-filters" role="tablist">
                <template x-for="f in filters" :key="f.key">
                    <button class="chip" :class="{ active: filter === f.key }" x-on:click="filter = f.key; loadList()" x-text="f.label"></button>
                </template>
            </div>
        </div>
        <div class="inbox-items" x-on:scroll.passive="maybeLoadMore($event)">
            <template x-for="c in conversations" :key="c.id">
                <a class="conv-item" :class="{ active: current && current.id === c.id }" x-on:click.prevent="open(c.id)" href="#">
                    <span class="avatar" x-text="initials(c.name || c.phone)"></span>
                    <span class="conv-main">
                        <span class="conv-top">
                            <span class="conv-name" x-text="c.name || c.phone"></span>
                            <span class="conv-time" x-text="c.time_label"></span>
                        </span>
                        <span class="conv-top">
                            <span class="conv-preview" x-text="c.last_message_preview || ''"></span>
                            <span class="unread-pill" x-show="c.unread_count > 0" x-text="c.unread_count"></span>
                        </span>
                    </span>
                </a>
            </template>
            <div class="empty-state" x-show="conversations.length === 0">
                <div class="icon">💬</div>
                <p><?= e(__('inbox.empty', 'No conversations yet. They appear here when customers message you.')) ?></p>
            </div>
        </div>
    </div>

    <!-- Pane 2: chat -->
    <div class="chat-pane">
        <template x-if="current === null">
            <div class="empty-state" style="margin:auto">
                <div class="icon">👈</div>
                <p><?= e(__('inbox.no_conversation', 'Select a conversation')) ?></p>
            </div>
        </template>

        <template x-if="current !== null">
            <div style="display:flex;flex-direction:column;height:100%">
                <div class="chat-header">
                    <button class="btn btn-ghost btn-icon mobile-nav-toggle" x-on:click="current = null" aria-label="Back">←</button>
                    <span class="avatar" x-text="initials(current.contact_name)"></span>
                    <div style="min-width:0">
                        <div class="font-semi truncate" x-text="current.contact_name"></div>
                        <div class="text-xs text-muted" x-text="current.contact_phone"></div>
                    </div>
                    <span class="session-timer" :class="windowClass()" x-text="windowLabel()"></span>
                    <div class="spacer" style="flex:1"></div>

                    <select class="input" style="width:auto" x-model="current.assigned_to" x-on:change="assign()" aria-label="<?= e(__('inbox.assign', 'Assign')) ?>">
                        <option value=""><?= e(__('inbox.unassigned', 'Unassigned')) ?></option>
                        <?php foreach ($agents as $agent): ?>
                            <option value="<?= (int) $agent['id'] ?>"><?= e($agent['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button class="btn btn-outline btn-sm" x-on:click="setStatus('resolved')" title="<?= e(__('inbox.resolve', 'Resolve')) ?>">✔</button>
                    <button class="btn btn-ghost btn-icon" x-on:click="toggleFlag('is_starred')" :style="current.is_starred ? 'color:var(--accent)' : ''">★</button>
                    <button class="btn btn-ghost btn-icon" x-on:click="toggleFlag('is_archived')" title="<?= e(__('inbox.archive', 'Archive')) ?>">🗄</button>
                </div>

                <div class="chat-scroll" id="chat-scroll" x-on:scroll.passive="maybeLoadOlder($event)">
                    <template x-for="(m, i) in messages" :key="m.id">
                        <div style="display:contents">
                            <div class="day-divider" x-show="i === 0 || messages[i-1].day !== m.day" x-text="m.day"></div>
                            <div class="bubble" :class="m.is_private_note == 1 ? 'note' : (m.direction === 'out' ? 'bubble-out' : 'bubble-in')">
                                <template x-if="m.media_url && (m.type === 'image' || m.type === 'sticker')">
                                    <img :src="m.media_url" alt="" loading="lazy">
                                </template>
                                <template x-if="m.media_url && m.type === 'video'">
                                    <video :src="m.media_url" controls preload="metadata"></video>
                                </template>
                                <template x-if="m.media_url && (m.type === 'audio' || m.type === 'voice')">
                                    <audio :src="m.media_url" controls preload="none"></audio>
                                </template>
                                <template x-if="m.media_url && m.type === 'document'">
                                    <a :href="m.media_url" target="_blank" rel="noopener">📄 <span x-text="m.body"></span></a>
                                </template>
                                <span x-show="!(m.media_url && m.type === 'document')" x-text="m.body"></span>
                                <span class="meta">
                                    <span x-text="m.time_label"></span>
                                    <span x-show="m.direction === 'out'" x-text="statusTick(m.status)" :style="m.status === 'read' ? 'color:#3B82F6' : ''"></span>
                                </span>
                                <div class="text-xs" style="color:var(--danger)" x-show="m.status === 'failed'" x-text="m.error_title"></div>
                            </div>
                        </div>
                    </template>
                    <div class="text-xs text-muted text-center" x-show="typingLabel" x-text="typingLabel"></div>
                </div>

                <div class="composer">
                    <div class="alert alert-warning" style="margin-bottom:.5rem" x-show="!windowOpen()">
                        <span>⏰</span>
                        <div>
                            <?= e(__('inbox.session_closed', 'Session closed — template only')) ?>
                            <button class="btn btn-sm btn-primary" style="margin-left:.5rem" x-on:click="templatePicker = true"><?= e(__('inbox.send_template', 'Send template')) ?></button>
                        </div>
                    </div>
                    <div class="composer-inner">
                        <label class="btn btn-ghost btn-icon" title="<?= e(__('inbox.attach', 'Attach')) ?>">
                            📎<input type="file" class="hidden" x-ref="file" x-on:change="sendMedia()">
                        </label>
                        <textarea x-model="draft" x-ref="draft" rows="1"
                                  :disabled="!windowOpen() && !noteMode"
                                  x-on:keydown.enter="if (!$event.shiftKey) { $event.preventDefault(); sendText(); }"
                                  x-on:input="typingPing()"
                                  :placeholder="noteMode ? '<?= e(__('inbox.note_placeholder', 'Private note — not sent to the customer')) ?>' : '<?= e(__('inbox.type_message', 'Type a message…')) ?>'"
                                  :style="noteMode ? 'background:var(--accent-100)' : ''"></textarea>
                        <button class="btn btn-ghost btn-icon" x-on:click="noteMode = !noteMode"
                                :style="noteMode ? 'color:var(--accent)' : ''" title="<?= e(__('inbox.note', 'Note')) ?>">📝</button>
                        <button class="btn btn-primary btn-icon" x-on:click="sendText()" aria-label="<?= e(__('inbox.send', 'Send')) ?>">➤</button>
                    </div>
                </div>
            </div>
        </template>
    </div>

    <!-- Template picker modal -->
    <div class="modal-backdrop" x-show="templatePicker" x-cloak x-on:click.self="templatePicker = false">
        <div class="modal">
            <div class="modal-header">
                <h3 style="margin:0"><?= e(__('inbox.send_template', 'Send template')) ?></h3>
                <button class="btn btn-ghost btn-icon" x-on:click="templatePicker = false">✕</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label"><?= e(__('templates.template', 'Template')) ?></label>
                    <select class="input" x-model="selectedTemplate">
                        <option value=""><?= e(__('common.choose', 'Choose…')) ?></option>
                        <?php foreach ($templates as $template): ?>
                            <option value="<?= (int) $template['id'] ?>"><?= e($template['name']) ?> (<?= e($template['language']) ?> · <?= e($template['category']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <p class="text-sm text-muted"><?= e(__('inbox.template_vars_hint', 'Variables are auto-filled from contact data where mapped; unmapped variables send their default values.')) ?></p>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline" x-on:click="templatePicker = false"><?= e(__('common.cancel', 'Cancel')) ?></button>
                <button class="btn btn-primary" x-on:click="sendTemplate()" :disabled="!selectedTemplate"><?= e(__('inbox.send', 'Send')) ?></button>
            </div>
        </div>
    </div>
</div>

<script>
function inboxApp() {
    return {
        conversations: [],
        current: null,
        messages: [],
        filter: 'all',
        search: '',
        draft: '',
        noteMode: false,
        templatePicker: false,
        selectedTemplate: '',
        typingLabel: '',
        typingTimer: null,
        lastTypingSent: 0,
        windowSeconds: 0,
        filters: [
            { key: 'all', label: '<?= e(__('inbox.all', 'All')) ?>' },
            { key: 'mine', label: '<?= e(__('inbox.mine', 'Mine')) ?>' },
            { key: 'unassigned', label: '<?= e(__('inbox.unassigned', 'Unassigned')) ?>' },
            { key: 'starred', label: '★' },
            { key: 'resolved', label: '<?= e(__('inbox.resolved', 'Resolved')) ?>' },
            { key: 'archived', label: '<?= e(__('inbox.archived', 'Archived')) ?>' }
        ],

        init() {
            this.loadList();
            setInterval(() => { if (this.windowSeconds > 0) this.windowSeconds--; }, 1000);

            kwcRealtime.on('message.new', (p) => {
                if (this.current && p.conversation_id === this.current.id) {
                    this.loadMessages(false);
                    if (p.direction === 'in') { this.markRead(); this.refreshWindow(); }
                } else if (p.direction === 'in') {
                    kwc.toast((p.contact_name || '') + ': ' + (p.preview || ''), 'info');
                }
                this.loadList();
            });
            kwcRealtime.on('message.status', (p) => {
                const m = this.messages.find(x => x.id === p.message_id);
                if (m) { m.status = p.status; }
            });
            kwcRealtime.on('message.media_ready', (p) => {
                const m = this.messages.find(x => x.id === p.message_id);
                if (m) { m.media_url = p.media_url; }
            });
            kwcRealtime.on('conversation.assigned', () => this.loadList());
            kwcRealtime.on('typing', (p) => {
                if (this.current && p.conversation_id === this.current.id && p.name) {
                    this.typingLabel = p.name + ' <?= e(__('inbox.is_typing', 'is typing…')) ?>';
                    clearTimeout(this.typingTimer);
                    this.typingTimer = setTimeout(() => { this.typingLabel = ''; }, 3000);
                }
            });

            const params = new URLSearchParams(window.location.search);
            if (params.get('open')) { this.open(parseInt(params.get('open'), 10)); }
        },

        loadList() {
            kwc.fetch('<?= e(url('/tenant/inbox/conversations')) ?>?filter=' + this.filter + '&q=' + encodeURIComponent(this.search))
                .then(r => { if (r.ok) { this.conversations = r.data.conversations; } });
        },

        maybeLoadMore(e) {
            const el = e.target;
            if (el.scrollTop + el.clientHeight >= el.scrollHeight - 60 && this.conversations.length >= 30) {
                const last = this.conversations[this.conversations.length - 1];
                kwc.fetch('<?= e(url('/tenant/inbox/conversations')) ?>?filter=' + this.filter + '&before=' + encodeURIComponent(last.last_message_at || ''))
                    .then(r => { if (r.ok && r.data.conversations.length) { this.conversations = this.conversations.concat(r.data.conversations); } });
            }
        },

        open(id) {
            kwc.fetch('<?= e(url('/tenant/inbox')) ?>/' + id).then(r => {
                if (!r.ok) { return; }
                const c = r.data.conversation;
                this.current = {
                    id: c.id,
                    contact_name: (r.data.contact && (r.data.contact.name || r.data.contact.phone)) || '',
                    contact_phone: (r.data.contact && r.data.contact.phone) || '',
                    assigned_to: c.assigned_to || '',
                    is_starred: c.is_starred, is_archived: c.is_archived, status: c.status
                };
                this.windowSeconds = c.window_seconds || 0;
                this.messages = [];
                this.loadMessages(true);
                this.markRead();
            });
        },

        refreshWindow() {
            if (!this.current) { return; }
            kwc.fetch('<?= e(url('/tenant/inbox')) ?>/' + this.current.id).then(r => {
                if (r.ok) { this.windowSeconds = r.data.conversation.window_seconds || 0; }
            });
        },

        loadMessages(scrollToEnd) {
            if (!this.current) { return; }
            kwc.fetch('<?= e(url('/tenant/inbox')) ?>/' + this.current.id + '/messages').then(r => {
                if (r.ok) {
                    this.messages = r.data.messages;
                    if (scrollToEnd !== false) { this.$nextTick(() => this.scrollToEnd()); }
                    else { this.$nextTick(() => this.scrollToEnd()); }
                }
            });
        },

        maybeLoadOlder(e) {
            if (e.target.scrollTop === 0 && this.messages.length >= 50 && this.current) {
                const first = this.messages[0];
                kwc.fetch('<?= e(url('/tenant/inbox')) ?>/' + this.current.id + '/messages?before_id=' + first.id).then(r => {
                    if (r.ok && r.data.messages.length) { this.messages = r.data.messages.concat(this.messages); }
                });
            }
        },

        scrollToEnd() {
            const el = document.getElementById('chat-scroll');
            if (el) { el.scrollTop = el.scrollHeight; }
        },

        sendText() {
            const body = this.draft.trim();
            if (!body || !this.current) { return; }
            if (this.noteMode) { this.saveNote(body); return; }
            this.draft = '';
            const form = new FormData();
            form.append('kind', 'text');
            form.append('body', body);
            form.append('_token', kwc.csrf());
            fetch('<?= e(url('/tenant/inbox')) ?>/' + this.current.id + '/send', {
                method: 'POST', body: form,
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
            }).then(r => r.json()).then(d => {
                if (d.success) { this.messages.push(d.data.message); this.$nextTick(() => this.scrollToEnd()); this.loadList(); }
                else { this.draft = body; kwc.toast(d.message || 'Send failed', 'danger', 7000); }
            });
        },

        saveNote(body) {
            const form = new FormData();
            form.append('body', body);
            form.append('_token', kwc.csrf());
            fetch('<?= e(url('/tenant/inbox')) ?>/' + this.current.id + '/note', {
                method: 'POST', body: form,
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
            }).then(r => r.json()).then(d => {
                if (d.success) {
                    this.draft = ''; this.noteMode = false;
                    kwc.toast('<?= e(__('inbox.note_saved', 'Note saved')) ?>', 'success', 1800);
                } else { kwc.toast(d.message || 'Failed', 'danger'); }
            });
        },

        sendMedia() {
            const input = this.$refs.file;
            if (!input.files.length || !this.current) { return; }
            const form = new FormData();
            form.append('kind', 'media');
            form.append('file', input.files[0]);
            form.append('caption', this.draft.trim());
            form.append('_token', kwc.csrf());
            kwc.toast('<?= e(__('inbox.uploading', 'Uploading…')) ?>', 'info', 2500);
            fetch('<?= e(url('/tenant/inbox')) ?>/' + this.current.id + '/send', {
                method: 'POST', body: form,
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
            }).then(r => r.json()).then(d => {
                input.value = '';
                if (d.success) { this.draft = ''; this.messages.push(d.data.message); this.$nextTick(() => this.scrollToEnd()); }
                else { kwc.toast(d.message || 'Send failed', 'danger', 7000); }
            });
        },

        sendTemplate() {
            if (!this.selectedTemplate || !this.current) { return; }
            const form = new FormData();
            form.append('kind', 'template');
            form.append('template_id', this.selectedTemplate);
            form.append('_token', kwc.csrf());
            fetch('<?= e(url('/tenant/inbox')) ?>/' + this.current.id + '/send', {
                method: 'POST', body: form,
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
            }).then(r => r.json()).then(d => {
                this.templatePicker = false;
                if (d.success) { this.messages.push(d.data.message); this.$nextTick(() => this.scrollToEnd()); }
                else { kwc.toast(d.message || 'Send failed', 'danger', 7000); }
            });
        },

        assign() {
            const form = new FormData();
            form.append('user_id', this.current.assigned_to || 0);
            form.append('_token', kwc.csrf());
            fetch('<?= e(url('/tenant/inbox')) ?>/' + this.current.id + '/assign', {
                method: 'POST', body: form,
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
            });
        },

        setStatus(status) {
            const form = new FormData();
            form.append('status', status);
            form.append('_token', kwc.csrf());
            fetch('<?= e(url('/tenant/inbox')) ?>/' + this.current.id + '/status', {
                method: 'POST', body: form,
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
            }).then(() => { this.loadList(); kwc.toast('<?= e(__('inbox.updated', 'Updated')) ?>', 'success', 1500); });
        },

        toggleFlag(flag) {
            this.current[flag] = this.current[flag] == 1 ? 0 : 1;
            const form = new FormData();
            form.append(flag, this.current[flag]);
            form.append('_token', kwc.csrf());
            fetch('<?= e(url('/tenant/inbox')) ?>/' + this.current.id + '/status', {
                method: 'POST', body: form,
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
            }).then(() => this.loadList());
        },

        markRead() {
            const form = new FormData();
            form.append('_token', kwc.csrf());
            fetch('<?= e(url('/tenant/inbox')) ?>/' + this.current.id + '/read', {
                method: 'POST', body: form,
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
            }).then(() => {
                const c = this.conversations.find(x => x.id === this.current.id);
                if (c) { c.unread_count = 0; }
            });
        },

        typingPing() {
            const nowMs = Date.now();
            if (nowMs - this.lastTypingSent < 4000 || !this.current || this.noteMode) { return; }
            this.lastTypingSent = nowMs;
            const form = new FormData();
            form.append('_token', kwc.csrf());
            fetch('<?= e(url('/tenant/inbox')) ?>/' + this.current.id + '/typing', {
                method: 'POST', body: form,
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
            });
        },

        windowOpen() { return this.windowSeconds > 0; },
        windowClass() {
            if (this.windowSeconds <= 0) { return 'closed'; }
            return this.windowSeconds < 3600 ? 'closing' : 'open';
        },
        windowLabel() {
            if (this.windowSeconds <= 0) { return '<?= e(__('inbox.session_expired', 'Expired')) ?>'; }
            const h = Math.floor(this.windowSeconds / 3600);
            const m = Math.floor((this.windowSeconds % 3600) / 60);
            return '⏱ ' + h + 'h ' + m + 'm';
        },
        statusTick(status) {
            return { queued: '🕓', sent: '✓', delivered: '✓✓', read: '✓✓', failed: '✗' }[status] || '';
        },
        initials(name) {
            return (name || '?').trim().split(/\s+/).slice(0, 2).map(w => w[0]).join('').toUpperCase();
        }
    };
}
</script>
<?php View::end(); ?>

<?php use App\Core\View; ?>
<?php View::start('content'); ?>
<div class="page-header"><h1><?= e(__('templates.new', 'New template')) ?></h1></div>

<?php
// Form state as JSON (safe for any quotes/newlines in re-filled input)
$formState = [
    'header' => (string) old('header'),
    'body' => (string) old('body'),
    'footer' => (string) old('footer'),
    'headerSample' => (string) old('header_example'),
    'samples' => (object) array_map('strval', (array) old('body_examples', [])),
];
?>
<form method="post" action="<?= e(url('/tenant/templates')) ?>"
      x-data="templateForm(<?= e(json_encode($formState, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP)) ?>)">
    <?= csrf_field() ?>
    <div class="grid-2">
        <div class="card">
            <div class="form-group">
                <label class="form-label">WABA</label>
                <select class="input" name="waba_account_id" required>
                    <?php foreach ($wabas as $waba): ?>
                        <option value="<?= (int) $waba['id'] ?>"><?= e($waba['name'] ?: $waba['waba_id']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label"><?= e(__('templates.name', 'Template name')) ?></label>
                    <input class="input" name="name" value="<?= e(old('name')) ?>" required pattern="[a-z0-9_]+" placeholder="order_update_1">
                    <div class="form-hint"><?= e(__('templates.name_hint', 'Lowercase letters, numbers, underscores only.')) ?></div>
                </div>
                <div class="form-group">
                    <label class="form-label"><?= e(__('templates.language', 'Language')) ?></label>
                    <select class="input" name="language">
                        <option value="en">English (en)</option>
                        <option value="en_US">English US (en_US)</option>
                        <option value="gu">ગુજરાતી (gu)</option>
                        <option value="hi">हिन्दी (hi)</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label"><?= e(__('templates.category', 'Category')) ?></label>
                <select class="input" name="category">
                    <option value="UTILITY">UTILITY — <?= e(__('templates.cat_utility', 'transactional updates')) ?></option>
                    <option value="MARKETING">MARKETING — <?= e(__('templates.cat_marketing', 'promotions & offers')) ?></option>
                    <option value="AUTHENTICATION">AUTHENTICATION — <?= e(__('templates.cat_auth', 'OTP codes')) ?></option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label"><?= e(__('templates.header', 'Header')) ?> <span class="text-muted">(<?= e(__('common.optional', 'optional')) ?>)</span></label>
                <input class="input" name="header" x-model="header" maxlength="60" placeholder="Order {{1}} update">
                <div class="form-hint"><?= e(__('templates.header_hint', 'Max one variable: {{1}}.')) ?></div>
            </div>
            <div class="form-group" x-show="headerHasVar" x-cloak>
                <label class="form-label"><?= e(__('templates.header_sample', 'Header sample value for')) ?> <code>{{1}}</code></label>
                <input class="input" name="header_example" x-model="headerSample" maxlength="200" placeholder="ORD-1234" :required="headerHasVar">
            </div>
            <div class="form-group">
                <label class="form-label"><?= e(__('templates.body', 'Body')) ?></label>
                <textarea class="input" name="body" x-model="body" required rows="5" maxlength="1024"
                          placeholder="Hello {{1}}, your order {{2}} has been shipped! 🚚"></textarea>
                <div class="form-hint"><?= e(__('templates.body_hint', 'Use {{1}}, {{2}}… for variables. Emojis allowed.')) ?></div>
            </div>
            <div class="form-group" x-show="bodyVars.length" x-cloak>
                <label class="form-label"><?= e(__('templates.samples', 'Sample values (required by Meta for review)')) ?></label>
                <template x-for="n in bodyVars" :key="n">
                    <div class="input-group mb-2">
                        <span class="btn btn-outline" style="pointer-events:none;min-width:4rem" x-text="'{{' + n + '}}'"></span>
                        <input class="input" :name="'body_examples[' + n + ']'" x-model="samples[n]" maxlength="200" required
                               :placeholder="n == 1 ? 'Rahul' : (n == 2 ? 'ORD-1234' : '<?= e(__('templates.sample_ph', 'Sample value')) ?>')">
                    </div>
                </template>
                <div class="form-hint"><?= e(__('templates.samples_hint', 'Realistic examples help approval. They are only shown to Meta reviewers, never to customers.')) ?></div>
            </div>
            <div class="form-group">
                <label class="form-label"><?= e(__('templates.footer', 'Footer')) ?> <span class="text-muted">(<?= e(__('common.optional', 'optional')) ?>)</span></label>
                <input class="input" name="footer" x-model="footer" maxlength="60">
            </div>
            <div class="form-group">
                <label class="form-label"><?= e(__('templates.buttons', 'Buttons')) ?> <span class="text-muted">(<?= e(__('common.optional', 'optional')) ?>)</span></label>
                <div class="grid-2">
                    <input class="input" name="buttons[0][text]" placeholder="<?= e(__('templates.btn1', 'Button 1 text (quick reply)')) ?>" maxlength="25">
                    <input class="input" name="buttons[1][text]" placeholder="<?= e(__('templates.btn2', 'Button 2 text (quick reply)')) ?>" maxlength="25">
                </div>
            </div>
            <button class="btn btn-primary btn-lg" type="submit">📤 <?= e(__('templates.submit', 'Submit to Meta for approval')) ?></button>
        </div>

        <!-- Live WhatsApp-style preview -->
        <div>
            <div class="card" style="background:var(--wa-chat-bg)">
                <h3 class="card-title"><?= e(__('templates.live_preview', 'Live preview')) ?></h3>
                <div class="bubble bubble-in" style="max-width:100%">
                    <strong x-show="header" x-text="fill(header, {1: headerSample})" style="display:block"></strong>
                    <span x-text="fill(body, samples) || '<?= e(__('templates.preview_placeholder', 'Your message will appear here…')) ?>'" style="white-space:pre-wrap"></span>
                    <div class="text-xs text-muted" x-show="footer" x-text="footer" style="margin-top:.3rem"></div>
                    <div class="meta"><span><?= e(date('h:i A')) ?></span></div>
                </div>
            </div>
            <div class="alert alert-info mt-4">
                <span>💡</span>
                <div><?= e(__('templates.tips', 'Approval tips: avoid spammy words, keep variables in context, and match the category honestly — miscategorised templates get rejected.')) ?></div>
            </div>
        </div>
    </div>
</form>
<script>
function templateForm(state) {
    var varRe = /\{\{\s*(\d+)\s*\}\}/g;
    return {
        header: state.header, body: state.body, footer: state.footer,
        headerSample: state.headerSample, samples: state.samples || {},
        get bodyVars() {
            var seen = {}, out = [], m;
            varRe.lastIndex = 0;
            while ((m = varRe.exec(this.body || '')) !== null) {
                var n = parseInt(m[1], 10);
                if (!seen[n]) { seen[n] = true; out.push(n); }
            }
            return out.sort(function (a, b) { return a - b; });
        },
        get headerHasVar() { return /\{\{\s*\d+\s*\}\}/.test(this.header || ''); },
        fill: function (text, values) {
            return (text || '').replace(varRe, function (all, n) {
                var v = values && values[n];
                return v ? v : all;
            });
        }
    };
}
</script>
<?php View::end(); ?>

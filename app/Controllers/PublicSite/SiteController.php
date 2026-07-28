<?php

declare(strict_types=1);

namespace App\Controllers\PublicSite;

use App\Core\Controller;
use App\Core\DB;
use App\Core\Layout;
use App\Core\Queue;
use App\Core\Redirect;
use App\Core\Request;
use App\Core\View;

/**
 * Public marketing + legal pages: home, pricing, privacy policy, terms,
 * data deletion, refund policy, contact.
 *
 * Legal page content ships with sensible defaults for a WhatsApp SaaS and
 * can be overridden per page via settings (page_privacy_html etc.) later.
 */
final class SiteController extends Controller
{
    public function home(Request $request): never
    {
        $plans = DB::table('plans')->where('is_active', 1)->orderBy('sort_order')->limit(4)->get();
        $features = [];
        foreach (DB::table('plan_features')->get() as $row) {
            $features[(int) $row['plan_id']][(string) $row['feature']] = $row['value'];
        }

        Layout::title(__('site.home_title', 'WhatsApp Business Platform for growing teams'));
        View::render('public/home', [
            'plans' => $plans,
            'planFeatures' => $features,
        ], 'layouts/public');
    }

    public function pricing(Request $request): never
    {
        $plans = DB::table('plans')->where('is_active', 1)->orderBy('sort_order')->get();
        $features = [];
        foreach (DB::table('plan_features')->get() as $row) {
            $features[(int) $row['plan_id']][(string) $row['feature']] = $row['value'];
        }

        Layout::title(__('site.pricing', 'Pricing'));
        View::render('public/pricing', [
            'plans' => $plans,
            'planFeatures' => $features,
        ], 'layouts/public');
    }

    public function privacy(Request $request): never
    {
        Layout::title(__('site.privacy', 'Privacy Policy'));
        View::render('public/legal', ['page' => self::privacyContent()], 'layouts/public');
    }

    public function terms(Request $request): never
    {
        Layout::title(__('site.terms', 'Terms of Service'));
        View::render('public/legal', ['page' => self::termsContent()], 'layouts/public');
    }

    public function dataDeletion(Request $request): never
    {
        Layout::title(__('site.data_deletion', 'Data Deletion Instructions'));
        View::render('public/legal', ['page' => self::dataDeletionContent()], 'layouts/public');
    }

    public function refund(Request $request): never
    {
        Layout::title(__('site.refund', 'Refund & Cancellation Policy'));
        View::render('public/legal', ['page' => self::refundContent()], 'layouts/public');
    }

    public function contact(Request $request): never
    {
        $a = random_int(2, 9);
        $b = random_int(1, 9);
        $_SESSION['contact_captcha'] = $a + $b;

        Layout::title(__('site.contact', 'Contact Us'));
        View::render('public/contact', [
            'captchaA' => $a,
            'captchaB' => $b,
            'supportEmail' => (string) setting('alert_email', ''),
        ], 'layouts/public');
    }

    public function contactSubmit(Request $request): never
    {
        $data = $this->validate($request, [
            'name' => 'required|string|min:2|max:100',
            'email' => 'required|email',
            'message' => 'required|string|min:10|max:3000',
        ]);

        if ($request->int('captcha', -1) !== (int) ($_SESSION['contact_captcha'] ?? -2)) {
            Redirect::back('/contact')
                ->with('error', __('auth.captcha_failed', 'Captcha answer is incorrect.'))
                ->withInput()
                ->send();
        }
        unset($_SESSION['contact_captcha']);

        // Notify platform admin: in-app notification + email (async)
        DB::table('notifications')->insert([
            'tenant_id' => null,
            'user_id' => null,
            'type' => 'contact.message',
            'title' => __('site.contact_notification', 'Contact form: :name', ['name' => (string) $data['name']]),
            'body' => 'From: ' . $data['name'] . ' <' . $data['email'] . ">\n\n" . $data['message'],
            'created_at' => now(),
        ]);

        $adminEmail = (string) setting('alert_email', '');
        if ($adminEmail !== '') {
            Queue::push(\App\Jobs\SendMailJob::class, [
                'to' => $adminEmail,
                'subject' => '[' . setting('app_name', 'Krishna WhatsApp Cloud') . '] Contact form — ' . $data['name'],
                'body' => "Name: {$data['name']}\nEmail: {$data['email']}\n\n{$data['message']}",
            ], 'mail', 5, 0, null);
        }

        Redirect::to('/contact')->with('success', __('site.contact_sent', 'Thank you! We received your message and will reply within one business day.'))->send();
    }

    // -- Legal content -----------------------------------------------------------
    // Structure: ['title', 'updated', 'intro', 'sections' => [[heading, paragraphs[], bullets[]?], ...]]
    // Rendered fully escaped in public/legal.php.

    private static function app(): string
    {
        return (string) setting('app_name', 'Krishna WhatsApp Cloud');
    }

    private static function domain(): string
    {
        $host = (string) (parse_url((string) config('app.url', ''), PHP_URL_HOST) ?: 'this website');
        return $host;
    }

    private static function contactEmail(): string
    {
        return (string) (setting('alert_email', '') ?: 'the contact form on this website');
    }

    public static function privacyContent(): array
    {
        $app = self::app();
        $domain = self::domain();
        $email = self::contactEmail();

        return [
            'title' => 'Privacy Policy',
            'updated' => 'Last updated: ' . date('d F Y'),
            'intro' => "This Privacy Policy explains how {$app} (\"we\", \"us\", \"our\"), operated at {$domain}, collects, uses, stores and protects information when you use our WhatsApp Business messaging platform (the \"Service\"). By using the Service you agree to this policy.",
            'sections' => [
                ['1. Who we are', [
                    "{$app} is a self-hosted software-as-a-service platform that lets businesses send and receive WhatsApp messages through the official Meta WhatsApp Business Cloud API, manage customer conversations, run broadcast campaigns and automate chats.",
                ]],
                ['2. Information we collect', [
                    'We collect the following categories of information:',
                ], [
                    'Account information — your name, email address, phone number, business name and password (stored only as a secure hash) when you register.',
                    'WhatsApp Business account details — your WhatsApp Business Account (WABA) ID, phone number IDs, display names and access tokens (stored encrypted with AES-256-GCM).',
                    'Contact data you upload — names, phone numbers, email addresses and custom fields of your customers that you import or that are created when customers message you.',
                    'Message content — messages sent and received through your connected WhatsApp number, including media files, so we can display your conversation history to you and your team.',
                    'Billing information — plan, invoices, payment transaction references. Card/UPI details are processed by payment gateways (e.g. Razorpay, Stripe) and never touch our servers.',
                    'Usage and log data — IP address, browser type, pages visited, API calls and actions taken, used for security auditing and troubleshooting.',
                    'Cookies — session cookies required to keep you signed in, and a preference cookie for dark mode/language. We do not use third-party advertising cookies.',
                ]],
                ['3. How we use information', [], [
                    'To provide the Service: routing WhatsApp messages, showing your team inbox, running campaigns and chatbots you configure.',
                    'To bill you for your subscription and messaging usage.',
                    'To secure the platform: login throttling, audit logs, fraud prevention.',
                    'To notify you about service events (template approvals, quality warnings, billing, updates).',
                    'To improve reliability through aggregated, non-identifying statistics.',
                ]],
                ['4. WhatsApp and Meta', [
                    "Messages are transmitted through the official Meta WhatsApp Business Cloud API. When you connect your WhatsApp Business account, message content and phone numbers are processed by Meta Platforms, Inc. under their own terms and privacy policies. We encourage you to review the WhatsApp Business Terms and the Meta Privacy Policy. {$app} is an independent product and is not affiliated with, endorsed by or sponsored by Meta.",
                ]],
                ['5. Your customers\' data (data you control)', [
                    "For contact lists and conversations, you (the workspace owner) are the data controller and we act as a processor on your instructions. You are responsible for obtaining valid opt-in consent from your customers before messaging them, and for honouring opt-out requests. The platform automatically processes STOP/UNSUBSCRIBE keywords and records consent changes.",
                ]],
                ['6. Sharing of information', [
                    'We do not sell personal data. We share information only with:',
                ], [
                    'Meta Platforms (WhatsApp Cloud API) — to deliver your messages.',
                    'Payment gateways — to process subscription and wallet payments.',
                    'Email/SMS providers — to send transactional notifications you request.',
                    'Legal authorities — if required by applicable law or valid legal process.',
                ]],
                ['7. Data storage and security', [], [
                    'All access tokens, API keys and gateway secrets are stored encrypted (AES-256-GCM).',
                    'Passwords are hashed with Argon2id/bcrypt and are never stored in plain text.',
                    'All traffic is served over HTTPS; webhook payloads are signature-verified.',
                    'Role-based access control restricts what each team member can see.',
                    'Uploaded files are validated and are never executable on our servers.',
                ]],
                ['8. Data retention', [], [
                    'Account and workspace data — retained while your account is active.',
                    'Messages and contacts — retained until you delete them or delete your workspace.',
                    'Webhook and API logs — automatically pruned after 14–30 days.',
                    'Backups — rotated automatically; old backups are deleted on a fixed schedule.',
                ]],
                ['9. Your rights', [
                    'Depending on your jurisdiction (including the DPDP Act 2023 in India and the GDPR in the EU), you may have the right to access, correct, export or erase your personal data, and to withdraw consent. You can exercise most of these directly in the app (Profile, Contacts, Settings). For anything else, contact us at ' . $email . '. See also our Data Deletion Instructions page.',
                ]],
                ['10. Children', [
                    'The Service is intended for businesses and is not directed at children under 18. We do not knowingly collect data from children.',
                ]],
                ['11. Changes to this policy', [
                    'We may update this policy from time to time. Material changes will be announced in-app or by email. Continued use of the Service after changes take effect constitutes acceptance.',
                ]],
                ['12. Contact', [
                    "Questions about privacy? Reach us at {$email}, or via the contact page at {$domain}/contact.",
                ]],
            ],
        ];
    }

    public static function termsContent(): array
    {
        $app = self::app();
        $domain = self::domain();
        $email = self::contactEmail();

        return [
            'title' => 'Terms of Service',
            'updated' => 'Last updated: ' . date('d F Y'),
            'intro' => "These Terms of Service (\"Terms\") govern your use of {$app} at {$domain} (the \"Service\"). By creating an account or using the Service you agree to these Terms. If you use the Service on behalf of a business, you represent that you are authorised to bind that business.",
            'sections' => [
                ['1. The Service', [
                    "{$app} provides tools to send and receive WhatsApp messages via the official Meta WhatsApp Business Cloud API, including a shared team inbox, broadcast campaigns, chatbots, contact management and a REST API. You connect your own WhatsApp Business Account; we do not provide phone numbers or WhatsApp accounts.",
                ]],
                ['2. Your account', [], [
                    'You must provide accurate registration information and keep your credentials confidential.',
                    'You are responsible for all activity under your workspace, including your team members\' actions.',
                    'You must be at least 18 years old to use the Service.',
                ]],
                ['3. Acceptable use — messaging rules', [
                    'You agree to comply with the WhatsApp Business Messaging Policy, WhatsApp Commerce Policy and all applicable laws (including TRAI regulations in India and anti-spam laws in your customers\' countries). In particular:',
                ], [
                    'Send marketing messages only to recipients who have given clear opt-in consent.',
                    'Honour every opt-out immediately (the platform automates STOP keyword handling — you must not circumvent it).',
                    'No illegal content, deception, harassment, or prohibited goods and services.',
                    'No attempts to bypass Meta quality controls, rate limits or template review.',
                    'No reselling of the Service except through an authorised reseller arrangement.',
                ], 'Violation may result in suspension or termination without refund, and may also result in Meta restricting your WhatsApp Business Account, over which we have no control.'],
                ['4. Fees, plans and messaging costs', [], [
                    'Subscription fees are charged per plan, in advance, for each billing cycle.',
                    'WhatsApp conversation/message fees set by Meta are separate and are billed per use through your wallet or your own Meta billing, at the rates displayed in the app.',
                    'Plan limits (contacts, messages, agents, etc.) are enforced automatically.',
                    'Prices may change with at least 15 days\' notice; changes apply from your next billing cycle.',
                    'Taxes (including GST) are added where applicable.',
                ]],
                ['5. Trials and cancellation', [
                    'Free trials convert to paid plans only when you choose to subscribe. You may cancel at any time; the Service remains available until the end of the paid period. See our Refund & Cancellation Policy for refund terms.',
                ]],
                ['6. Your data', [
                    'You retain all rights to the contacts, messages and content you process through the Service. You grant us the limited licence needed to operate the Service (storing, transmitting and displaying your data to you and your team). We process personal data as described in the Privacy Policy.',
                ]],
                ['7. Service availability', [
                    'We aim for high availability but the Service is provided "as is" and "as available". Message delivery ultimately depends on Meta\'s WhatsApp infrastructure, your hosting environment, and telecom networks, which are outside our control. Scheduled maintenance will be announced where practical.',
                ]],
                ['8. Intellectual property', [
                    "The Service software, design and branding remain the property of {$app} / Krishna SaaS Suite. WhatsApp and Meta are trademarks of Meta Platforms, Inc.; their use here is for identification only.",
                ]],
                ['9. Termination', [], [
                    'You may delete your workspace at any time.',
                    'We may suspend or terminate accounts that breach these Terms, create legal risk, or remain unpaid after notice.',
                    'On termination we will make your data available for export for 30 days, after which it is deleted per our retention schedule.',
                ]],
                ['10. Limitation of liability', [
                    "To the maximum extent permitted by law, {$app} shall not be liable for indirect, incidental, special or consequential damages, loss of profits, loss of data, or business interruption. Our total aggregate liability for any claim is limited to the fees you paid to us in the three (3) months preceding the claim.",
                ]],
                ['11. Indemnity', [
                    'You agree to indemnify us against claims arising from your content, your messaging practices (including spam complaints and consent violations), or your breach of these Terms.',
                ]],
                ['12. Governing law', [
                    'These Terms are governed by the laws of India. Courts at Ahmedabad, Gujarat shall have exclusive jurisdiction, subject to any mandatory consumer protections in your place of residence.',
                ]],
                ['13. Changes', [
                    'We may update these Terms; material changes will be notified in-app or by email at least 15 days in advance where practical.',
                ]],
                ['14. Contact', [
                    "Questions about these Terms? Contact {$email} or use {$domain}/contact.",
                ]],
            ],
        ];
    }

    public static function dataDeletionContent(): array
    {
        $app = self::app();
        $domain = self::domain();
        $email = self::contactEmail();

        return [
            'title' => 'Data Deletion Instructions',
            'updated' => 'Last updated: ' . date('d F Y'),
            'intro' => "This page explains how to delete your data from {$app}, as required by the Meta Platform Terms for apps using WhatsApp/Facebook services.",
            'sections' => [
                ['If you are a workspace owner (our customer)', [], [
                    'Delete individual contacts and their conversation history: Contacts → open the contact → Delete. This permanently removes the contact, its messages and custom field values.',
                    'Disconnect WhatsApp: Settings → WhatsApp → Disconnect. This deactivates your numbers and stops all processing; stored tokens are invalidated.',
                    "Delete your entire workspace and account: email {$email} from your registered email address with the subject \"Delete my account\". We verify ownership and permanently delete the workspace — users, contacts, messages, media, campaigns, flows and settings — within 30 days, including from rotating backups as they expire.",
                ]],
                ['If you are an end customer (you message a business that uses ' . $app . ')', [
                    "Businesses using {$app} control the data of their own customers. To have your data deleted:",
                ], [
                    'Reply STOP to the business on WhatsApp to opt out of future messages immediately, or',
                    "Ask the business directly to delete your contact record, or",
                    "Contact us at {$email} with the business name and your WhatsApp number — we will identify the workspace and instruct deletion within 30 days.",
                ]],
                ['What gets deleted', [], [
                    'Contact records, custom fields, tags and group memberships.',
                    'All inbound/outbound messages and downloaded media files.',
                    'Conversation notes and automation run history linked to the contact.',
                ]],
                ['Facebook / Meta data deletion callback', [
                    "Where {$app} is connected via Meta Embedded Signup, disconnecting the WhatsApp Business Account from Settings → WhatsApp also removes stored access tokens. Data already processed by Meta is subject to Meta's own deletion procedures (see the WhatsApp Privacy Policy).",
                ]],
                ['Timeline and confirmation', [
                    'Verified deletion requests are completed within 30 days and confirmed by email. Some minimal records (invoices, tax records, audit logs required by law) are retained for the legally mandated period only.',
                ]],
                ['Contact', [
                    "Deletion requests and questions: {$email} · {$domain}/contact",
                ]],
            ],
        ];
    }

    public static function refundContent(): array
    {
        $app = self::app();
        $email = self::contactEmail();

        return [
            'title' => 'Refund & Cancellation Policy',
            'updated' => 'Last updated: ' . date('d F Y'),
            'intro' => "This policy describes cancellations and refunds for {$app} subscriptions and wallet recharges.",
            'sections' => [
                ['1. Free trial', [
                    'Every new workspace starts with a free trial. No payment is required during the trial and nothing is charged automatically when it ends — you choose if and when to subscribe.',
                ]],
                ['2. Subscription cancellation', [], [
                    'You may cancel your subscription at any time from Billing, or by contacting support.',
                    'After cancellation the Service remains fully available until the end of the already-paid billing period.',
                    'Cancellation stops future renewals; it does not by itself trigger a refund of the current period.',
                ]],
                ['3. Subscription refunds', [], [
                    'First purchase of a plan: full refund if requested within 7 days of payment, provided fewer than 500 messages have been sent from the workspace in that time.',
                    'Renewals: refundable within 48 hours of the renewal charge if the workspace has not been used after renewal.',
                    'Duplicate or erroneous charges: always refunded in full.',
                    'Refunds for annual plans after the 7-day window are prorated to unused full months, at our discretion.',
                ]],
                ['4. Wallet recharges and messaging fees', [], [
                    'Wallet balance is used to pay per-message WhatsApp conversation fees charged by Meta.',
                    'Fees for messages already sent are consumed with Meta and are strictly non-refundable.',
                    'Unused wallet balance is refundable on account closure, minus payment-gateway charges.',
                ]],
                ['5. How refunds are processed', [], [
                    'Request a refund by emailing ' . $email . ' from your registered email with your workspace name and payment reference.',
                    'Approved refunds are issued to the original payment method within 7–10 business days.',
                    'Payment gateway processing fees may be deducted where the gateway does not return them.',
                ]],
                ['6. Exceptions', [
                    'No refund is due where an account was suspended or terminated for violating our Terms of Service (including spam or WhatsApp policy violations), or where Meta restricts your WhatsApp Business Account for policy reasons outside our control.',
                ]],
                ['7. Contact', [
                    'Refund questions: ' . $email,
                ]],
            ],
        ];
    }
}

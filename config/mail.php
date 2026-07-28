<?php

/**
 * Mail defaults. Admin-panel SMTP settings (settings table) override these
 * at runtime; see App\Core\Mail::smtpConfig().
 */
return [
    'driver' => 'mail', // mail | smtp
    'host' => '',
    'port' => 587,
    'encryption' => 'tls', // tls | ssl | none
    'username' => '',
    'password' => '',
    'from_email' => 'noreply@localhost',
    'from_name' => 'Krishna WhatsApp Cloud',
];

<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Date/time formatting helpers with tenant timezone awareness.
 */
final class DateHelper
{
    public static function timezone(): string
    {
        $user = null;
        try {
            $user = Auth::user();
        } catch (\Throwable) {
            // CLI or pre-auth context
        }
        $tz = $user['timezone'] ?? null;
        if (!$tz) {
            $tz = Tenant::current()['timezone'] ?? null;
        }
        return is_string($tz) && $tz !== '' ? $tz : (string) config('app.timezone', 'Asia/Kolkata');
    }

    /**
     * Convert a UTC/db datetime to the viewer's timezone and format it.
     */
    public static function display(?string $datetime, string $format = 'd M Y, h:i A'): string
    {
        if ($datetime === null || $datetime === '' || str_starts_with($datetime, '0000')) {
            return '—';
        }
        try {
            $dt = new \DateTime($datetime, new \DateTimeZone(date_default_timezone_get()));
            $dt->setTimezone(new \DateTimeZone(self::timezone()));
            return $dt->format($format);
        } catch (\Throwable) {
            return $datetime;
        }
    }

    public static function ago(string $datetime): string
    {
        $timestamp = strtotime($datetime);
        if ($timestamp === false) {
            return $datetime;
        }
        $diff = time() - $timestamp;
        if ($diff < 0) {
            return self::display($datetime, 'd M, h:i A');
        }
        if ($diff < 60) {
            return __('time.just_now', 'just now');
        }
        if ($diff < 3600) {
            $minutes = (int) floor($diff / 60);
            return __('time.minutes_ago', ':n min ago', ['n' => $minutes]);
        }
        if ($diff < 86400) {
            $hours = (int) floor($diff / 3600);
            return __('time.hours_ago', ':n hr ago', ['n' => $hours]);
        }
        if ($diff < 604800) {
            $days = (int) floor($diff / 86400);
            return __('time.days_ago', ':n days ago', ['n' => $days]);
        }
        return self::display($datetime, 'd M Y');
    }

    /**
     * WhatsApp-style chat timestamp: time today, "Yesterday", weekday, or date.
     */
    public static function chatStamp(string $datetime): string
    {
        $timestamp = strtotime($datetime);
        if ($timestamp === false) {
            return '';
        }
        $date = date('Y-m-d', $timestamp);
        if ($date === date('Y-m-d')) {
            return self::display($datetime, 'h:i A');
        }
        if ($date === date('Y-m-d', strtotime('-1 day'))) {
            return __('time.yesterday', 'Yesterday');
        }
        if ($timestamp > strtotime('-6 days')) {
            return self::display($datetime, 'D');
        }
        return self::display($datetime, 'd/m/Y');
    }

    /**
     * Remaining seconds until a datetime (24h session window countdown).
     */
    public static function secondsUntil(?string $datetime): int
    {
        if ($datetime === null) {
            return 0;
        }
        $timestamp = strtotime($datetime);
        return $timestamp === false ? 0 : max(0, $timestamp - time());
    }
}

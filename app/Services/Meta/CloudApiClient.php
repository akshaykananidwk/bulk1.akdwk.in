<?php

declare(strict_types=1);

namespace App\Services\Meta;

use App\Core\Crypt;
use App\Core\DB;
use App\Core\Http;
use App\Core\HttpResponse;
use App\Core\Logger;

/**
 * Meta WhatsApp Cloud API (Graph) client.
 *
 * One instance per WABA account; token decrypted lazily. All requests go
 * through Http with retries and full logging to the `meta` channel.
 */
final class CloudApiClient
{
    private array $waba;
    private string $token;
    private string $apiVersion;
    private string $graphUrl;

    public function __construct(array $wabaAccount)
    {
        $this->waba = $wabaAccount;
        $token = Crypt::decrypt((string) $wabaAccount['access_token_encrypted']);
        if ($token === null || $token === '') {
            throw new \RuntimeException('WABA access token cannot be decrypted — reconnect the account.');
        }
        $this->token = $token;
        $this->apiVersion = (string) (setting('meta_api_version') ?: config('meta.api_version', 'v21.0'));
        $this->graphUrl = (string) config('meta.graph_url', 'https://graph.facebook.com');
    }

    /**
     * Build a client for a phone_numbers row (joins its WABA).
     */
    public static function forPhoneNumber(array $phoneNumber): self
    {
        $waba = DB::table('waba_accounts')->where('id', $phoneNumber['waba_account_id'])->first();
        if ($waba === null) {
            throw new \RuntimeException('WABA account not found for phone number.');
        }
        return new self($waba);
    }

    public static function forTenantDefault(int $tenantId): ?self
    {
        $phone = DB::table('phone_numbers')
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->orderBy('is_default', 'DESC')
            ->orderBy('id', 'ASC')
            ->first();
        if ($phone === null) {
            return null;
        }
        return self::forPhoneNumber($phone);
    }

    private function http(): Http
    {
        return Http::make()
            ->baseUrl($this->graphUrl . '/' . $this->apiVersion)
            ->withToken($this->token)
            ->timeout(30)
            ->retry(3, [2, 8, 30])
            ->logTo('meta');
    }

    // -- Messages ---------------------------------------------------------------

    /**
     * Send a message payload from a phone_number_id (Meta's id).
     * Returns the decoded response; throws MetaApiException on error.
     */
    public function sendMessage(string $phoneNumberId, array $payload): array
    {
        $payload['messaging_product'] = 'whatsapp';
        $response = $this->http()->post('/' . $phoneNumberId . '/messages', $payload);
        return $this->unwrap($response);
    }

    /**
     * Mark a message read, optionally showing a typing indicator.
     */
    public function markRead(string $phoneNumberId, string $wamid, bool $typingIndicator = false): array
    {
        $payload = [
            'messaging_product' => 'whatsapp',
            'status' => 'read',
            'message_id' => $wamid,
        ];
        if ($typingIndicator) {
            $payload['typing_indicator'] = ['type' => 'text'];
        }
        $response = $this->http()->post('/' . $phoneNumberId . '/messages', $payload);
        return $this->unwrap($response);
    }

    // -- Media -------------------------------------------------------------------

    public function uploadMedia(string $phoneNumberId, string $filePath, string $mime): array
    {
        $fields = [
            'messaging_product' => 'whatsapp',
            'type' => $mime,
            'file' => new \CURLFile($filePath, $mime, basename($filePath)),
        ];
        $response = $this->http()->postMultipart('/' . $phoneNumberId . '/media', $fields);
        return $this->unwrap($response);
    }

    /** Get a temporary media URL for an inbound media id. */
    public function getMediaUrl(string $mediaId): array
    {
        $response = $this->http()->get('/' . $mediaId);
        return $this->unwrap($response);
    }

    /** Download media bytes from the URL returned by getMediaUrl(). */
    public function downloadMedia(string $mediaUrl): string
    {
        $response = Http::make()
            ->withToken($this->token)
            ->timeout(120)
            ->retry(2, [2, 8])
            ->get($mediaUrl);
        if (!$response->ok()) {
            throw new MetaApiException('Media download failed (HTTP ' . $response->status . ')', 0, null);
        }
        return $response->body;
    }

    // -- Templates ----------------------------------------------------------------

    public function listTemplates(?string $after = null): array
    {
        $query = ['limit' => 100, 'fields' => 'id,name,language,category,status,components,rejected_reason'];
        if ($after !== null) {
            $query['after'] = $after;
        }
        $response = $this->http()->get('/' . $this->waba['waba_id'] . '/message_templates', $query);
        return $this->unwrap($response);
    }

    public function createTemplate(array $template): array
    {
        $response = $this->http()->post('/' . $this->waba['waba_id'] . '/message_templates', $template);
        return $this->unwrap($response);
    }

    public function deleteTemplate(string $name): array
    {
        $response = $this->http()->delete('/' . $this->waba['waba_id'] . '/message_templates?name=' . rawurlencode($name));
        return $this->unwrap($response);
    }

    // -- Account / numbers ----------------------------------------------------------

    public function getPhoneNumbers(): array
    {
        $response = $this->http()->get('/' . $this->waba['waba_id'] . '/phone_numbers', [
            'fields' => 'id,display_phone_number,verified_name,quality_rating,code_verification_status,throughput,messaging_limit_tier',
        ]);
        return $this->unwrap($response);
    }

    public function subscribeApp(): array
    {
        $response = $this->http()->post('/' . $this->waba['waba_id'] . '/subscribed_apps', []);
        return $this->unwrap($response);
    }

    public function registerPhone(string $phoneNumberId, string $pin): array
    {
        $response = $this->http()->post('/' . $phoneNumberId . '/register', [
            'messaging_product' => 'whatsapp',
            'pin' => $pin,
        ]);
        return $this->unwrap($response);
    }

    public function getWabaInfo(): array
    {
        $response = $this->http()->get('/' . $this->waba['waba_id'], [
            'fields' => 'id,name,currency,timezone_id',
        ]);
        return $this->unwrap($response);
    }

    // -- Internals -------------------------------------------------------------------

    private function unwrap(HttpResponse $response): array
    {
        $data = $response->json();

        if (!$response->ok() || isset($data['error'])) {
            $error = $data['error'] ?? [];
            $code = (int) ($error['code'] ?? $response->status);
            $subcode = isset($error['error_subcode']) ? (int) $error['error_subcode'] : null;
            $message = (string) ($error['message'] ?? ('HTTP ' . $response->status . ($response->error !== null ? ' ' . $response->error : '')));

            Logger::channel('meta')->error('Graph API error', [
                'code' => $code,
                'subcode' => $subcode,
                'message' => $message,
                'waba' => $this->waba['waba_id'] ?? null,
            ]);

            // Token expiry → flag account for reconnection
            if ($code === 190) {
                DB::table('waba_accounts')->where('id', $this->waba['id'])->update(['status' => 'error']);
            }

            throw new MetaApiException($message, $code, $subcode);
        }

        return $data;
    }
}

/**
 * Graph API error with Meta's numeric code + optional subcode.
 */
final class MetaApiException extends \RuntimeException
{
    public function __construct(string $message, int $code, public readonly ?int $subcode)
    {
        parent::__construct($message, $code);
    }

    public function friendly(): array
    {
        return ErrorCodeMapper::map($this->getCode(), $this->getMessage());
    }
}

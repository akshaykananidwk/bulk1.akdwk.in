<?php

declare(strict_types=1);

namespace App\Services\Updater;

use App\Core\Crypt;
use App\Core\Http;
use App\Core\HttpResponse;
use App\Core\Logger;

/**
 * GitHub REST API client for the auto-updater. The token is stored
 * AES-256-GCM encrypted and NEVER logged or echoed (Logger redacts too).
 */
final class GithubClient
{
    private string $owner;
    private string $repo;
    private string $branch;
    private ?string $token;
    private string $apiBase;

    public function __construct(?string $owner = null, ?string $repo = null, ?string $branch = null, ?string $token = null)
    {
        $this->owner = $owner ?? (string) setting('update_github_owner', '');
        $this->repo = $repo ?? (string) setting('update_github_repo', '');
        $this->branch = $branch ?? ((string) setting('update_github_branch', '') ?: 'main');
        // Overridable for GitHub Enterprise (https://ghe.example.com/api/v3)
        $this->apiBase = rtrim((string) (setting('update_github_api_base', '') ?: 'https://api.github.com'), '/');

        if ($token !== null) {
            $this->token = $token !== '' ? $token : null;
        } else {
            $encrypted = (string) setting('update_github_token', '');
            $this->token = $encrypted !== '' ? Crypt::decrypt($encrypted) : null;
        }
    }

    public function configured(): bool
    {
        return $this->owner !== '' && $this->repo !== '';
    }

    public function branch(): string
    {
        return $this->branch;
    }

    public function repoLabel(): string
    {
        return $this->owner . '/' . $this->repo;
    }

    private function request(): Http
    {
        $http = Http::make()
            ->baseUrl($this->apiBase)
            ->withHeaders([
                'Accept' => 'application/vnd.github+json',
                'X-GitHub-Api-Version' => '2022-11-28',
                'User-Agent' => 'KrishnaWhatsAppCloud/' . app_version(),
            ])
            ->timeout(30)
            ->retry(2, [2, 8])
            ->logTo('update');
        if ($this->token !== null && $this->token !== '') {
            $http->withToken($this->token);
        }
        return $http;
    }

    /** GET /repos/{owner}/{repo} — connection test. */
    public function repoInfo(): array
    {
        return $this->unwrap($this->request()->get('/repos/' . $this->owner . '/' . $this->repo));
    }

    /** Latest commit on the branch. */
    public function latestCommit(): array
    {
        return $this->unwrap($this->request()->get('/repos/' . $this->owner . '/' . $this->repo . '/commits/' . rawurlencode($this->branch)));
    }

    /** Compare installed sha with the branch head. */
    public function compare(string $baseSha): array
    {
        return $this->unwrap($this->request()->get(
            '/repos/' . $this->owner . '/' . $this->repo . '/compare/' . rawurlencode($baseSha) . '...' . rawurlencode($this->branch)
        ));
    }

    /** Fetch a file's content at the branch (base64 decoded). */
    public function fileContents(string $path): ?string
    {
        $response = $this->request()->get(
            '/repos/' . $this->owner . '/' . $this->repo . '/contents/' . str_replace('%2F', '/', rawurlencode($path)),
            ['ref' => $this->branch]
        );
        if ($response->status === 404) {
            return null;
        }
        $data = $this->unwrap($response);
        if (($data['encoding'] ?? '') === 'base64') {
            $decoded = base64_decode(str_replace("\n", '', (string) ($data['content'] ?? '')), true);
            return $decoded === false ? null : $decoded;
        }
        return null;
    }

    /** Latest release (release_tag channel). */
    public function latestRelease(): array
    {
        return $this->unwrap($this->request()->get('/repos/' . $this->owner . '/' . $this->repo . '/releases/latest'));
    }

    /** Full recursive tree at a sha — for the integrity checker. */
    public function tree(string $sha): array
    {
        return $this->unwrap($this->request()->get(
            '/repos/' . $this->owner . '/' . $this->repo . '/git/trees/' . rawurlencode($sha),
            ['recursive' => '1']
        ));
    }

    /**
     * Stream the zipball to a file (never into memory).
     */
    public function downloadZipball(string $ref, string $destination, ?callable $progress = null): void
    {
        $http = Http::make()
            ->withHeaders([
                'Accept' => 'application/vnd.github+json',
                'X-GitHub-Api-Version' => '2022-11-28',
                'User-Agent' => 'KrishnaWhatsAppCloud/' . app_version(),
            ])
            ->timeout(600)
            ->retry(3, [5, 15, 30]);
        if ($this->token !== null && $this->token !== '') {
            $http->withToken($this->token);
        }

        $response = $http->download(
            $this->apiBase . '/repos/' . $this->owner . '/' . $this->repo . '/zipball/' . rawurlencode($ref),
            $destination,
            $progress
        );
        if (!$response->ok()) {
            throw new \RuntimeException('Package download failed: HTTP ' . $response->status . ($response->error !== null ? ' — ' . Logger::redact($response->error) : ''));
        }
    }

    private function unwrap(HttpResponse $response): array
    {
        // Track rate limits for observability
        $remaining = $response->header('x-ratelimit-remaining');
        if ($remaining !== null && (int) $remaining < 10) {
            Logger::channel('update')->warning('GitHub rate limit low', ['remaining' => $remaining]);
        }

        // Network-level failure (curl error, no HTTP status): explain the
        // server-side cause instead of a cryptic "HTTP 0".
        if ($response->status === 0 || $response->error !== null && $response->status === 0) {
            throw new \RuntimeException(self::networkErrorMessage((string) $response->error));
        }

        $data = $response->json();
        if (!$response->ok()) {
            $githubMessage = (string) ($data['message'] ?? '');
            $friendly = match (true) {
                $response->status === 404 => __('update.err_404', 'Repository not found (HTTP 404). Check the owner/repo spelling — and for a PRIVATE repository you MUST save a GitHub token (fine-grained, Contents: Read).'),
                $response->status === 401 => __('update.err_401', 'GitHub token is invalid or expired (HTTP 401). Generate a new fine-grained token with Contents: Read access to this repository and save it again.'),
                $response->status === 403 && stripos($githubMessage, 'rate limit') !== false => __('update.err_rate', 'GitHub rate limit reached (HTTP 403). Save a GitHub token to get a much higher limit, or wait an hour.'),
                $response->status === 403 => __('update.err_403', 'GitHub refused access (HTTP 403).') . ($githubMessage !== '' ? ' — ' . $githubMessage : ''),
                default => 'GitHub API error: ' . ($githubMessage !== '' ? $githubMessage : 'HTTP ' . $response->status) . ' (HTTP ' . $response->status . ')',
            };
            throw new \RuntimeException(Logger::redact($friendly));
        }
        return $data;
    }

    /**
     * Translate a curl-level failure into an actionable server-fix message.
     */
    public static function networkErrorMessage(string $curlError): string
    {
        $lower = strtolower($curlError);
        $hint = __('update.net_generic', 'The server cannot reach api.github.com — check that outbound HTTPS (port 443) is allowed in the firewall.');
        if (str_contains($lower, 'certificate') || str_contains($lower, 'ssl')) {
            $hint = __('update.net_ssl', 'SSL certificate verification failed on this server. Fix: in aaPanel → PHP settings set curl.cainfo = /etc/ssl/certs/ca-certificates.crt (or run "yum/apt install ca-certificates"), then restart PHP.');
        } elseif (str_contains($lower, 'resolve') || str_contains($lower, 'name lookup')) {
            $hint = __('update.net_dns', 'DNS failure — this server cannot resolve api.github.com. Check /etc/resolv.conf or the aaPanel DNS settings.');
        } elseif (str_contains($lower, 'timed out') || str_contains($lower, 'timeout')) {
            $hint = __('update.net_timeout', 'Connection timed out — a firewall is probably blocking outbound HTTPS to GitHub (common on some VPS providers; whitelist api.github.com).');
        }
        return __('update.net_prefix', 'Cannot reach GitHub') . ': ' . $curlError . ' — ' . $hint;
    }
}

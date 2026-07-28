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

        $data = $response->json();
        if (!$response->ok()) {
            $message = (string) ($data['message'] ?? ('HTTP ' . $response->status));
            throw new \RuntimeException('GitHub API error: ' . Logger::redact($message) . ' (HTTP ' . $response->status . ')');
        }
        return $data;
    }
}

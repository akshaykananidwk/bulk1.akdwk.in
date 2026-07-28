<?php

declare(strict_types=1);

namespace App\Services\Updater;

/**
 * "Verify Installation": compares local file hashes with the GitHub tree
 * at the installed commit — lists missing / modified / extra files.
 */
final class IntegrityChecker
{
    /** Paths never compared (runtime/user data). */
    private const IGNORED = [
        'config/config.php', 'installed.lock', '.env',
        'uploads/', 'storage/', 'assets/custom/', 'vendor/', 'node_modules/', '.git/',
        'router-dev.php',
    ];

    public static function verify(): array
    {
        $sha = (string) setting('installed_commit_sha', '');
        if ($sha === '') {
            throw new \RuntimeException('Installed commit SHA unknown — run one update first (or set it in Update settings).');
        }

        $client = new GithubClient();
        $tree = $client->tree($sha);
        if (!empty($tree['truncated'])) {
            // Tree too large for one call — still verify what we received
            \App\Core\Logger::channel('update')->warning('GitHub tree truncated; integrity check partial');
        }

        $remote = [];
        foreach ((array) ($tree['tree'] ?? []) as $entry) {
            if (($entry['type'] ?? '') === 'blob') {
                $remote[(string) $entry['path']] = (string) ($entry['sha'] ?? '');
            }
        }

        $missing = [];
        $modified = [];
        $matched = 0;

        foreach ($remote as $path => $blobSha) {
            if (self::ignored($path)) {
                continue;
            }
            $local = ROOT_PATH . '/' . $path;
            if (!is_file($local)) {
                $missing[] = $path;
                continue;
            }
            if (self::gitBlobSha($local) !== $blobSha) {
                $modified[] = $path;
            } else {
                $matched++;
            }
        }

        // Extra files: local files not in the tree (php/js/css only, to avoid noise)
        $extra = [];
        $rootReal = realpath(ROOT_PATH);
        if ($rootReal !== false) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveCallbackFilterIterator(
                    new \RecursiveDirectoryIterator($rootReal, \FilesystemIterator::SKIP_DOTS),
                    function (\SplFileInfo $file) use ($rootReal): bool {
                        $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($rootReal) + 1));
                        return !self::ignored($relative . ($file->isDir() ? '/' : ''));
                    }
                )
            );
            foreach ($iterator as $file) {
                /** @var \SplFileInfo $file */
                if (!$file->isFile()) {
                    continue;
                }
                $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($rootReal) + 1));
                if (!isset($remote[$relative]) && preg_match('/\.(php|js|css|json|sql|htaccess)$/', $relative)) {
                    $extra[] = $relative;
                }
            }
        }

        return [
            'sha' => $sha,
            'total_remote' => count($remote),
            'matched' => $matched,
            'missing' => $missing,
            'modified' => $modified,
            'extra' => array_slice($extra, 0, 200),
            'clean' => empty($missing) && empty($modified) && empty($extra),
        ];
    }

    private static function ignored(string $path): bool
    {
        foreach (self::IGNORED as $ignored) {
            if (str_ends_with($ignored, '/')) {
                if (str_starts_with($path, $ignored) || $path === rtrim($ignored, '/')) {
                    return true;
                }
            } elseif ($path === $ignored) {
                return true;
            }
        }
        return false;
    }

    /**
     * Git blob SHA-1: sha1("blob {size}\0{content}").
     */
    public static function gitBlobSha(string $file): string
    {
        $contents = (string) file_get_contents($file);
        return sha1('blob ' . strlen($contents) . "\0" . $contents);
    }
}

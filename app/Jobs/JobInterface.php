<?php

declare(strict_types=1);

namespace App\Jobs;

/**
 * Contract for queued jobs. Payload is the decoded JSON stored in the
 * jobs table; tenant context is restored by the worker before handle().
 */
interface JobInterface
{
    public function handle(array $payload): void;
}

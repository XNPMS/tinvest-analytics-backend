<?php

declare(strict_types=1);

namespace System\Helper;

use Throwable;

final class RetryHelper
{
    /**
     * @param callable(Throwable):bool|null $shouldRetry return false to abort retries immediately
     * @throws Throwable
     */
    public static function withRetry(
        callable $fn,
        int $attempts = 3,
        int $delaySeconds = 5,
        ?callable $shouldRetry = null,
    ): mixed {
        $lastException = null;
        for ($i = 0; $i < $attempts; $i++) {
            try {
                return $fn();
            } catch (Throwable $e) {
                if ($shouldRetry !== null && !$shouldRetry($e)) {
                    throw $e;
                }
                $lastException = $e;
                if ($i < $attempts - 1) {
                    sleep($delaySeconds);
                }
            }
        }

        throw $lastException;
    }
}

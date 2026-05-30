<?php

declare(strict_types=1);

namespace Tinvest\Service;

use Tinvest\Enum\LimitTokens;

final class RateLimiter
{
    private const SECONDS_PER_MINUTE = 60.0;
    private const REQUIRED_TOKENS = 1.0;
    private const MICROSECONDS_IN_SECOND = 1_000_000;

    private float $tokens;
    private float $lastRefillTime;
    private int $maxTokensPerMinute;
    private float $refillRatePerSecond;

    public function __construct(LimitTokens $maxTokensPerMinute)
    {
        $this->maxTokensPerMinute = $maxTokensPerMinute->value;
        $this->refillRatePerSecond = $this->maxTokensPerMinute / self::SECONDS_PER_MINUTE;

        $this->tokens = $this->maxTokensPerMinute;
        $this->lastRefillTime = microtime(true);
    }

    public function consume(): void
    {
        $this->refill();

        if ($this->tokens >= self::REQUIRED_TOKENS) {
            $this->tokens -= self::REQUIRED_TOKENS;

            return;
        }

        $this->waitForNextToken();
        $this->refill();

        $this->tokens = max(0.0, $this->tokens - self::REQUIRED_TOKENS);
    }

    private function refill(): void
    {
        $now = microtime(true);
        $elapsed = $now - $this->lastRefillTime;

        if ($elapsed <= 0) {
            return;
        }

        $this->tokens = min(
            $this->maxTokensPerMinute,
            $this->tokens + $elapsed * $this->refillRatePerSecond
        );

        $this->lastRefillTime = $now;
    }

    private function waitForNextToken(): void
    {
        $missingTokens = self::REQUIRED_TOKENS - $this->tokens;
        $waitSeconds = $missingTokens / $this->refillRatePerSecond;

        if ($waitSeconds > 0) {
            usleep((int)($waitSeconds * self::MICROSECONDS_IN_SECOND));
        }
    }
}

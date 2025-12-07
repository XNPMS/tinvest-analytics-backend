<?php

declare(strict_types=1);

namespace Tinvest\Service;

use Tinvest\Enum\LimitTokens;

final class RateLimiter
{
    private float $tokens = 0.0;
    private float $lastTime = 0.0;
    private int $maxTokensPerMinute = 0;

    public function setMaxTokens(LimitTokens $maxTokensPerMinute): self
    {
        $this->maxTokensPerMinute = $maxTokensPerMinute->value;
        $this->tokens = $this->maxTokensPerMinute;
        $this->lastTime = microtime(true);

        return $this;
    }

    public function acquire(): void
    {
        $now = microtime(true);
        $elapsed = $now - $this->lastTime;

        // Пополняем токены
        $this->tokens = min(
            $this->maxTokensPerMinute,
            $this->tokens + $elapsed * ($this->maxTokensPerMinute / 60.0)
        );
        $this->lastTime = $now;

        // Если есть токен - забираем и выходим
        if ($this->tokens >= 1.0) {
            $this->tokens -= 1.0;

            return;
        }

        // Ждем появления токена
        $wait = (1.0 - $this->tokens) / ($this->maxTokensPerMinute / 60.0);
        usleep((int)($wait * 1_000_000));

        // Обновляем состояние после ожидания
        $this->tokens = 0.0;
        $this->lastTime = microtime(true);
    }
}

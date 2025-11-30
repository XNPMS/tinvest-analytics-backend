<?php

declare(strict_types=1);

namespace Auth\Entity;

use Carbon\Carbon;
use DateTime;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use User\Entity\User;

class RefreshToken extends Model
{
    public const TABLE = 'refresh_tokens';

    /** @var string */
    protected $table = self::TABLE;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isExpired(): bool
    {
        return Carbon::now()->gt(Carbon::parse($this->getExpiresAt()));
    }

    public function revoke(): void
    {
        $this->setAttribute(true, 'revoked');
    }

    public function isRevoked(): bool
    {
        return $this->getAttributeFromArray('revoked') === 1;
    }

    public function getExpiresAt(): string
    {
        return (string)$this->getAttributeFromArray('expires_at');
    }

    public function setUserId(int $userId): void
    {
        $this->setAttribute('user_id', $userId);
    }

    public function getUserId(): int
    {
        return (int)$this->getAttributeFromArray('user_id');
    }

    public function setRefreshToken(string $refreshToken): void
    {
        $this->setAttribute('refresh_token', $refreshToken);
    }

    public function getRefreshToken(): string
    {
        return (string)$this->getAttributeFromArray('refresh_token');
    }

    public function setExpiresAt(DateTime $expiresAt): void
    {
        $this->setAttribute('expires_at', $expiresAt);
    }
}

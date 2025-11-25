<?php

declare(strict_types=1);

namespace Auth\Entity;

use Carbon\Carbon;
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

    public function getExpiresAt(): int
    {
        return (int)$this->getAttributeFromArray('expires_at');
    }
}
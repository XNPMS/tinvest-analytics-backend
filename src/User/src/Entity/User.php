<?php

declare(strict_types=1);

namespace User\Entity;

use Auth\Entity\RefreshToken;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Model
{
    public const TABLE = 'users';

    /** @var string */
    protected $table = self::TABLE;

    public function refreshTokens(): HasMany
    {
        return $this->hasMany(RefreshToken::class);
    }

    public function getId(): int
    {
        return (int)$this->getAttributeFromArray('id');
    }

    public function getEmail(): string
    {
        return (string)$this->getAttributeFromArray('email');
    }
}

<?php

declare(strict_types=1);

namespace Auth\Repository;

use Auth\Entity\RefreshToken;
use System\Repository\AbstractEloquentRepository;

readonly class RefreshTokenRepository extends AbstractEloquentRepository
{
    public function getModelClass(): string
    {
        return RefreshToken::class;
    }
}
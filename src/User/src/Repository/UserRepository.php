<?php

declare(strict_types=1);

namespace User\Repository;

use System\Repository\AbstractEloquentRepository;
use User\Entity\User;

readonly class UserRepository extends AbstractEloquentRepository
{
    public function getModelClass(): string
    {
        return User::class;
    }
}
<?php

declare(strict_types=1);

namespace User\Repository;

use System\Repository\AbstractEloquentRepository;
use User\Entity\User;

readonly class UserRepository extends AbstractEloquentRepository
{
    public function getEntityClass(): string
    {
        return User::class;
    }

    public function getUserById(string $userId): ?User
    {
        /** @var User */
        return $this->createQueryBuilder()
            ->where('id', '=', $userId)
            ?->first();
    }

    public function getUserByEmail(string $email): ?User
    {
        /** @var User */
        return $this->createQueryBuilder()
            ->where('email', '=', $email)
            ->first();
    }
}

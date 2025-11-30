<?php

declare(strict_types=1);

namespace User\Service;

use Auth\DTO\RegisterUserData;
use Auth\Exception\UserSearchException;
use User\Entity\User;
use User\Repository\UserRepository;

readonly class UserService
{
    public function __construct(
        private UserRepository $userRepository,
    ) {
    }

    /**
     * @throws UserSearchException
     */
    public function createUser(RegisterUserData $userData): User
    {
        if ($this->userRepository->getUserByEmail($userData->email)) {
            throw new UserSearchException('User with this email address already exists');
        }

        $user = new User();

        $user->setEmail($userData->email);
        $user->setPassword($userData->password);
        $user->save();

        return $user;
    }

    public function getUserById(int $userId): ?User
    {
        return $this->userRepository->getUserById($userId);
    }

    public function getUserByEmail(string $email): ?User
    {
        return $this->userRepository->getUserByEmail($email);
    }
}

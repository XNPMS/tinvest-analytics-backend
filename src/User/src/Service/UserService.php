<?php

declare(strict_types=1);

namespace User\Service;

use Auth\DTO\UserCredentials;
use Auth\Exception\UserRuntimeException;
use User\Entity\User;
use User\Repository\UserRepository;

readonly class UserService
{
    public function __construct(
        private UserRepository $userRepository,
    ) {
    }

    /**
     * @throws UserRuntimeException
     */
    public function createUser(UserCredentials $userData): User
    {
        if ($this->userRepository->getUserByEmail($userData->email)) {
            throw new UserRuntimeException('User with this email address already exists');
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

    public function saveTinvestToken(User $user, string $tToken): User
    {
        $user->setTinvestToken($tToken);
        $user->save();

        return $user;
    }
}

<?php

declare(strict_types=1);

namespace User\Service;

use Auth\DTO\UserCredentials;
use Auth\Exception\UserRuntimeException;
use Ramsey\Uuid\Uuid;
use User\Entity\User;
use User\Enum\OnboardingStep;
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
        $user->setAttribute('id', Uuid::uuid4()->toString());
        $user->setEmail($userData->email);
        $user->setPassword($userData->password);
        $user->save();

        return $user;
    }

    public function getUserById(string $userId): ?User
    {
        return $this->userRepository->getUserById($userId);
    }

    public function getUserByEmail(string $email): ?User
    {
        return $this->userRepository->getUserByEmail($email);
    }

    public function updateOnboardingStep(User $user, OnboardingStep $step): User
    {
        // если ранее уже был пройден полный онбординг, то больше его не изменяем
        if ($user->getOnboardingStep() === OnboardingStep::READY) {
            return $user;
        }

        $user->setOnboardingStep($step);
        $user->save();

        return $user;
    }
}

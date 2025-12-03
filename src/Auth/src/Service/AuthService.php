<?php

declare(strict_types=1);

namespace Auth\Service;

use Auth\DTO\TokenPair;
use Auth\DTO\UserCredentials;
use Auth\Exception\AuthenticationFailedException;
use Auth\Exception\InvalidRefreshTokenException;
use Auth\Exception\UserRuntimeException;
use User\Entity\User;
use User\Service\UserService;

readonly class AuthService
{
    public function __construct(
        private TokenManager $tokenManager,
        private UserService $userService,
    ) {
    }

    public function issueTokenPair(User $user): TokenPair
    {
        return $this->tokenManager->generateTokenPairForUser($user);
    }

    /**
     * @throws InvalidRefreshTokenException
     * @throws UserRuntimeException
     */
    public function refreshWithRaw(string $rawRefresh): TokenPair
    {
        $refreshToken = $this->tokenManager->validateRefreshToken($rawRefresh);
        if (!$user = $this->userService->getUserById($refreshToken->getUserId())) {
            throw new UserRuntimeException('User not found');
        }

        $refreshToken->revoke();

        return $this->issueTokenPair($user);
    }

    /**
     * @throws UserRuntimeException
     * @throws AuthenticationFailedException
     */
    public function authenticate(UserCredentials $userData): User
    {
        if (!$user = $this->userService->getUserByEmail($userData->email)) {
            throw new UserRuntimeException('User not found');
        }

        if (!password_verify($userData->password, $user->getPasswordHash())) {
            throw new AuthenticationFailedException('Email or password is incorrect');
        }

        return $user;
    }
}

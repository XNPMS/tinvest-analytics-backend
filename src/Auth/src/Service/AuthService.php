<?php

declare(strict_types=1);

namespace Auth\Service;

use Auth\DTO\TokenPair;
use Auth\Entity\RefreshToken;
use Auth\Exception\InvalidRefreshTokenException;
use Auth\Exception\UserSearchException;
use User\Entity\User;
use User\Service\UserService;

readonly class AuthService
{
    public function __construct(
        private TokenPairService $tokenPairService,
        private UserService $userService,
        private RefreshTokenService $refreshTokenService,
    ) {
    }

    public function issueTokenPair(User $user): TokenPair
    {
        return $this->generateTokenPair($user);
    }

    /**
     * @throws InvalidRefreshTokenException
     * @throws UserSearchException
     */
    public function refreshWithRaw(string $rawRefresh): TokenPair
    {
        $refreshToken = $this->validateRefreshToken($rawRefresh);
        if (!$user = $this->userService->getUserById($refreshToken->getUserId())) {
            throw new UserSearchException('User not found');
        }

        $refreshToken->revoke();

        return $this->generateTokenPair($user);
    }

    private function generateTokenPair(User $user): TokenPair
    {
        $claims = [
            'sub' => (string)$user->getId(),
            'email' => $user->getEmail(),
        ];

        $accessToken = $this->tokenPairService->issueAccessToken($claims);
        $refreshToken = $this->tokenPairService->createRefreshTokenRaw();
        $accessTtl = $this->tokenPairService->oauthConfig->accessTokenTtl;
        $refreshTtl = $this->tokenPairService->oauthConfig->refreshTokenTtl;

        $this->refreshTokenService->createRefreshToken(
            $user->getId(),
            $refreshToken,
            $refreshTtl
        );

        return new TokenPair($accessToken, $refreshToken, $accessTtl, $refreshTtl);
    }

    /**
     * @throws InvalidRefreshTokenException
     */
    private function validateRefreshToken(string $rawRefresh): RefreshToken
    {
        $token = $this->refreshTokenService->getRefreshTokenByRefreshToken($rawRefresh);

        switch (true) {
            case !$token:
                throw new InvalidRefreshTokenException('Refresh token not found');
            case $token->isRevoked():
                throw new InvalidRefreshTokenException('Refresh token revoked');
            case $token->isExpired():
                throw new InvalidRefreshTokenException('Refresh token expired');
        }

        return $token;
    }
}

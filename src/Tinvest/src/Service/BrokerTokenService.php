<?php

declare(strict_types=1);

namespace Tinvest\Service;

use Random\RandomException;
use RuntimeException;
use Tinvest\Entity\BrokerToken;
use Tinvest\Exception\BrokerTokenException;
use Tinvest\Repository\BrokerTokenRepository;
use User\Entity\User;
use User\Enum\OnboardingStep;
use User\Service\UserService;

readonly class BrokerTokenService
{
    private const CIPHER = 'aes-256-gcm';
    private const GCM_IV_LENGTH = 12;

    public function __construct(
        private BrokerTokenRepository $repository,
        private UserService $userService,
        private string $encryptionKey,
    ) {
    }

    /**
     * @throws RandomException
     */
    public function saveToken(User $user, string $plainToken): BrokerToken
    {
        $brokerToken = $this->repository->findByUserId($userId = $user->getId()) ?? new BrokerToken();
        // активный токен не можем редактировать
        if ($brokerToken && $brokerToken->isActive()) {
            return $brokerToken;
        }

        $iv = random_bytes(self::GCM_IV_LENGTH);
        $tag = '';

        $encrypted = openssl_encrypt(
            $plainToken,
            self::CIPHER,
            $this->encryptionKey,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
        );

        if ($encrypted === false) {
            throw new RuntimeException('Failed to encrypt broker token');
        }

        $brokerToken->setAttribute('user_id', $userId);
        $brokerToken->setAttribute('token_encrypted', base64_encode($encrypted));
        $brokerToken->setAttribute('token_iv', base64_encode($iv));
        $brokerToken->setAttribute('token_tag', bin2hex($tag));
        $brokerToken->setAttribute('status', 'active');
        $brokerToken->setAttribute('last_error', null);
        $brokerToken->save();

        $this->userService->updateOnboardingStep($user, OnboardingStep::ACCOUNTS_PENDING);

        return $brokerToken;
    }

    public function getDecryptedToken(string $userId): string
    {
        $brokerToken = $this->repository->findActiveByUserId($userId);
        if (!$brokerToken) {
            throw new RuntimeException(sprintf('Active broker token not found for user %d', $userId));
        }

        $decrypted = openssl_decrypt(
            base64_decode($brokerToken->getTokenEncrypted()),
            self::CIPHER,
            $this->encryptionKey,
            OPENSSL_RAW_DATA,
            base64_decode($brokerToken->getTokenIv()),
            hex2bin($brokerToken->getTokenTag()),
        );

        if ($decrypted === false) {
            throw new RuntimeException('Failed to decrypt broker token');
        }

        return $decrypted;
    }

    public function hasActiveToken(string $userId): bool
    {
        return $this->repository->findActiveByUserId($userId) !== null;
    }

    public function markInvalid(string $userId, string $error): void
    {
        $brokerToken = $this->repository->findByUserId($userId);

        if (!$brokerToken) {
            return;
        }

        $brokerToken->setAttribute('status', 'invalid');
        $brokerToken->setAttribute('last_error', $error);
        $brokerToken->save();
    }

    /**
     * @throws BrokerTokenException
     */
    public function removeToken(string $userId, string $tokenId): bool
    {
        $brokerToken = $this->repository->findByUserId($userId);
        if (!$brokerToken) {
            throw new BrokerTokenException(sprintf('Broker token not found for user %s', $userId));
        }

        if (!$brokerToken->isActive()) {
            return true;
        }

        $brokerToken->revoke();
        return true;
    }
}

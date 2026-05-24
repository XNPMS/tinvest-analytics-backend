<?php

declare(strict_types=1);

namespace Auth\Service;

use Auth\Config\OAuthConfig;
use Auth\DTO\TokenPair;
use Auth\Entity\RefreshToken;
use Auth\Exception\InvalidAccessTokenException;
use Auth\Exception\InvalidRefreshTokenException;
use Exception;
use Lcobucci\Clock\SystemClock;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Lcobucci\JWT\Token;
use Lcobucci\JWT\Validation\Constraint\LooseValidAt;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use Ramsey\Uuid\Uuid;
use User\Entity\User;

readonly class TokenManager
{
    private const LENGTH_REFRESH_TOKEN = 64;
    private const TOKEN_LIFETIME_REFRESH_RATIO = 0.3;
    private Configuration $jwtConfig;

    public function __construct(
        public OAuthConfig $oauthConfig,
        private RefreshTokenService $refreshTokenService,
    ) {
        $this->jwtConfig = Configuration::forAsymmetricSigner(
            new Sha256(),
            InMemory::base64Encoded($this->oauthConfig->jwtConfig->privateKey),
            InMemory::base64Encoded($this->oauthConfig->jwtConfig->publicKey)
        );
    }

    public function issueAccessToken(string $subject): Token
    {
        $now = new \DateTimeImmutable();
        $exp = $now->modify('+' . $this->oauthConfig->accessTokenTtl . ' seconds');

         return $this->jwtConfig
             ->builder()
             ->issuedBy($this->oauthConfig->jwtConfig->issuer)
             ->permittedFor($this->oauthConfig->jwtConfig->audience)
             ->issuedAt($now)
             ->canOnlyBeUsedAfter($now)
             ->expiresAt($exp)
             ->identifiedBy(Uuid::uuid4()->toString())
             ->relatedTo($subject)
             ->getToken($this->jwtConfig->signer(), $this->jwtConfig->signingKey());
    }

    public function generateTokenPairForUser(User $user): TokenPair
    {
        $accessToken = $this->issueAccessToken((string)$user->getId());
        $refreshToken = $this->createRefreshTokenRaw();
        $accessTtl = $this->oauthConfig->accessTokenTtl;
        $refreshTtl = $this->oauthConfig->refreshTokenTtl;

        $this->refreshTokenService->createRefreshToken(
            $user->getId(),
            $refreshToken,
            $refreshTtl
        );

        return new TokenPair($accessToken->toString(), $refreshToken, $accessTtl, $refreshTtl);
    }

    /**
     * @throws InvalidRefreshTokenException
     */
    public function validateRefreshToken(string $rawRefresh): RefreshToken
    {
        $token = $this->refreshTokenService->getRefreshTokenByRefreshToken($rawRefresh);

        $message = match (true) {
            !$token => 'Refresh token not found',
            $token->isRevoked() => 'Refresh token revoked',
            $token->isExpired() => 'Refresh token expired',
            default => null,
        };

        if ($message !== null) {
            throw new InvalidRefreshTokenException($message);
        }

        return $token;
    }

    /**
     * @throws InvalidAccessTokenException
     */
    public function validateAccessToken(string $jwt): Token
    {
        try {
            $token = $this->jwtConfig->parser()->parse($jwt);

            $constraints = [
                new SignedWith($this->jwtConfig->signer(), $this->jwtConfig->verificationKey()),
                new LooseValidAt(SystemClock::fromSystemTimezone()),
            ];

            if (!$this->jwtConfig->validator()->validate($token, ...$constraints)) {
                throw new InvalidAccessTokenException('Invalid access token');
            }

            return $token;
        } catch (Exception $e) {
            throw new InvalidAccessTokenException('Invalid access token');
        }
    }

    public function createRefreshTokenRaw(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(self::LENGTH_REFRESH_TOKEN)), '+/', '-_'), '=');
    }

    public function shouldRefreshAccessToken(Token $token): bool
    {
        try {
            $expiresAt = $token->claims()->get('exp') ?? 0;
            $now = new \DateTimeImmutable();

            $tokenLifetime = $expiresAt?->getTimestamp() - ($token->claims()->get('iat')->getTimestamp() ?? 0);
            $timeLeft = $expiresAt?->getTimestamp() - $now->getTimestamp();

            return ($timeLeft / $tokenLifetime) < self::TOKEN_LIFETIME_REFRESH_RATIO;
        } catch (\RuntimeException $e) {
            return true;
        }
    }
}

<?php

declare(strict_types=1);

namespace Auth\Service;

use Auth\Config\OAuthConfig;
use Auth\Exception\InvalidAccessTokenException;
use Lcobucci\Clock\SystemClock;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Lcobucci\JWT\Token;
use Lcobucci\JWT\Validation\Constraint\LooseValidAt;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use Ramsey\Uuid\Uuid;
use Random\RandomException;

readonly class TokenPairService
{
    private const LENGTH_REFRESH_TOKEN = 64;
    private const TOKEN_LIFETIME_REFRESH_RATIO = 0.3;
    private Configuration $jwtConfig;

    public function __construct(public OAuthConfig $oauthConfig)
    {
        $this->jwtConfig = Configuration::forAsymmetricSigner(
            new Sha256(),
            InMemory::file($this->oauthConfig->jwtConfig->privateKeyPath),
            InMemory::file($this->oauthConfig->jwtConfig->publicKeyPath)
        );
    }

    public function issueAccessToken(array $claims): string
    {
        $now = new \DateTimeImmutable();
        $exp = $now->modify('+' . $this->oauthConfig->accessTokenTtl . ' seconds');

        $builder = $this->jwtConfig
            ->builder()
            ->issuedBy($this->oauthConfig->jwtConfig->issuer)
            ->permittedFor($this->oauthConfig->jwtConfig->audience)
            ->issuedAt($now)
            ->canOnlyBeUsedAfter($now)
            ->expiresAt($exp)
            ->identifiedBy(Uuid::uuid4()->toString());

        foreach ($claims as $claim) {
            $builder = $builder->relatedTo($claim);
        }

        $token = $builder->getToken($this->jwtConfig->signer(), $this->jwtConfig->signingKey());

        return $token->toString();
    }

    /**
     * @throws InvalidAccessTokenException
     */
    public function validateAccessToken(string $jwt): Token
    {
        $token = $this->jwtConfig->parser()->parse($jwt);

        $constraints = [
            new SignedWith($this->jwtConfig->signer(), $this->jwtConfig->verificationKey()),
            new LooseValidAt(SystemClock::fromSystemTimezone())
        ];

        if (!$this->jwtConfig->validator()->validate($token, ...$constraints)) {
            throw new InvalidAccessTokenException('Invalid access token');
        }

        return $token;
    }

    /**
     * @throws RandomException
     */
    public function createRefreshTokenRaw(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(self::LENGTH_REFRESH_TOKEN)), '+/', '-_'), '=');
    }

    public function shouldRefreshAccessToken(Token $token): bool
    {
        try {
            $expiresAt = $token->claims()->get('exp');
            $now = new \DateTimeImmutable();

            $tokenLifetime = $expiresAt->getTimestamp() - $token->claims()->get('iat')->getTimestamp();
            $timeLeft = $expiresAt->getTimestamp() - $now->getTimestamp();

            return ($timeLeft / $tokenLifetime) < self::TOKEN_LIFETIME_REFRESH_RATIO;
        } catch (\RuntimeException $e) {
            return true;
        }
    }
}

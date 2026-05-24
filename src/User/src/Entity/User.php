<?php

declare(strict_types=1);

namespace User\Entity;

use Auth\Entity\RefreshToken;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use User\Enum\OnboardingStep;

class User extends Model
{
    public const TABLE = 'users';

    /** @var string */
    protected $table = self::TABLE;
    /** @var bool */
    public $incrementing = false;
    /** @var string */
    protected $keyType = 'string';

    public function refreshTokens(): HasMany
    {
        return $this->hasMany(RefreshToken::class);
    }

    public function getId(): string
    {
        return (string)$this->getAttributeFromArray('id');
    }

    public function getEmail(): string
    {
        return (string)$this->getAttributeFromArray('email');
    }

    public function setEmail(string $email): void
    {
        $this->setAttribute('email', $email);
    }

    public function setPassword(string $password): void
    {
        $this->setAttribute('password_hash', password_hash($password, PASSWORD_DEFAULT));
    }

    public function getPasswordHash(): string
    {
        return (string)$this->getAttributeFromArray('password_hash');
    }

    public function getOnboardingStep(): OnboardingStep
    {
        return OnboardingStep::from((string)$this->getAttributeFromArray('onboarding_step'));
    }

    public function setOnboardingStep(OnboardingStep $step): void
    {
        $this->setAttribute('onboarding_step', $step->value);
    }

    public function isActive(): bool
    {
        return (bool)$this->getAttributeFromArray('is_active');
    }

    public function setIsActive(bool $active): void
    {
        $this->setAttribute('is_active', $active ? 1 : 0);
    }

    public function getEmailVerifiedAt(): ?string
    {
        return $this->getAttributeFromArray('email_verified_at') ?: null;
    }

    public function setEmailVerifiedAt(?string $datetime): void
    {
        $this->setAttribute('email_verified_at', $datetime);
    }

    public function isEmailVerified(): bool
    {
        return $this->getEmailVerifiedAt() !== null;
    }
}

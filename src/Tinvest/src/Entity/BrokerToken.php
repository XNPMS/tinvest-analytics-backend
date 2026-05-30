<?php

declare(strict_types=1);

namespace Tinvest\Entity;

use Illuminate\Database\Eloquent\Model;

class BrokerToken extends Model
{
    public const TABLE = 'broker_tokens';

    /** @var string */
    protected $table = self::TABLE;

    public function getUserId(): string
    {
        return (string)$this->getAttributeFromArray('user_id');
    }

    public function getTokenEncrypted(): string
    {
        return (string)$this->getAttributeFromArray('token_encrypted');
    }

    public function getTokenIv(): string
    {
        return (string)$this->getAttributeFromArray('token_iv');
    }

    public function getTokenTag(): string
    {
        return (string)$this->getAttributeFromArray('token_tag');
    }

    public function getStatus(): string
    {
        return (string)$this->getAttributeFromArray('status');
    }

    public function isActive(): bool
    {
        return $this->getStatus() === 'active';
    }

    public function getLastError(): ?string
    {
        return $this->getAttributeFromArray('last_error') ?: null;
    }

    public function revoke(): void
    {
        $this->setAttribute('status', 'revoked');
        $this->save();
    }
}

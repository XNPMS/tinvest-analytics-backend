<?php

declare(strict_types=1);

namespace Tinvest\Entity;

use Illuminate\Database\Eloquent\Model;
use Tinkoff\Invest\V1\AccessLevel;
use Tinkoff\Invest\V1\AccountStatus;
use Tinkoff\Invest\V1\AccountType;

class TinvestAccount extends Model
{
    public const TABLE = 'tinvest_accounts';

    /** @var string */
    protected $table = self::TABLE;

    public function getId(): int
    {
        return (int)$this->getAttributeFromArray('id');
    }

    public function getUserId(): string
    {
        return (string)$this->getAttributeFromArray('user_id');
    }

    public function getAccountId(): string
    {
        return (string)$this->getAttributeFromArray('account_id');
    }

    public function getName(): string
    {
        return (string)$this->getAttributeFromArray('name');
    }

    public function isAnalyticsEnabled(): bool
    {
        return (bool)$this->getAttributeFromArray('analytics_enabled');
    }

    public function isSynced(): bool
    {
        return (bool)$this->getAttributeFromArray('is_synced');
    }

    public function markSynced(): void
    {
        $this->setAttribute('is_synced', true);
    }

    public function getStatus(): string
    {
        return AccountStatus::name((int)$this->getAttributeFromArray('status'));
    }

    public function getType(): string
    {
        return AccountType::name((int)$this->getAttributeFromArray('type'));
    }

    public function getOpenedDate(): string
    {
        return (string)$this->getAttributeFromArray('opened_date');
    }

    public function getAccessLevel(): string
    {
        return AccessLevel::name((int)$this->getAttributeFromArray('access_level'));
    }

    public function getLastSyncCursor(): ?string
    {
        return (string)$this->getAttributeFromArray('last_sync_cursor') ?: null;
    }

    public function setLastSyncCursor(?string $cursor): void
    {
        $this->setAttribute('last_sync_cursor', $cursor);
    }

    public function isClosed(): bool
    {
        return (bool)$this->getAttributeFromArray('is_closed');
    }

    public function getClosedDate(): ?string
    {
        return (string)$this->getAttributeFromArray('closed_date') ?: null;
    }
}

<?php

declare(strict_types=1);

namespace Tinvest\Entity;

use Illuminate\Database\Eloquent\Model;
use Tinvest\Enum\SyncAction;
use Tinvest\Enum\SyncStatus;

class TinvestSyncProcesses extends Model
{
    public const TABLE = 'tinvest_sync_processes';

    /** @var string */
    protected $table = self::TABLE;

    public function setUserId(string $userId): void
    {
        $this->setAttribute('user_id', $userId);
    }

    public function getUserId(): string
    {
        return (string)$this->getAttributeFromArray('user_id');
    }

    public function setAccountId(int $accountId): void
    {
        $this->setAttribute('account_id', $accountId);
    }

    public function getAccountId(): int
    {
        return (int)$this->getAttributeFromArray('account_id');
    }

    public function setJobId(string $jobId): void
    {
        $this->setAttribute('job_id', $jobId);
    }

    public function getJobId(): string
    {
        return (string)$this->getAttributeFromArray('job_id');
    }

    public function setAction(string $action): void
    {
        $this->setAttribute('action', $action);
    }

    public function getAction(): SyncAction
    {
        return SyncAction::from((string)$this->getAttributeFromArray('action'));
    }

    public function setStatus(int $status): void
    {
        $this->setAttribute('status', $status);
    }

    public function getStatus(): SyncStatus
    {
        return SyncStatus::from((int)$this->getAttributeFromArray('status'));
    }

    public function setProgress(int $progress): void
    {
        $this->setAttribute('progress', $progress);
    }

    public function getProgress(): int
    {
        return (int)$this->getAttributeFromArray('progress');
    }

    public function setSyncedCount(int $count): void
    {
        $this->setAttribute('synced_count', $count);
    }

    public function getSyncedCount(): int
    {
        return (int)$this->getAttributeFromArray('synced_count');
    }

    public function setTotalCount(int $count): void
    {
        $this->setAttribute('total_count', $count);
    }

    public function getTotalCount(): int
    {
        return (int)$this->getAttributeFromArray('total_count');
    }

    public function setErrorMessage(?string $errorMessage): void
    {
        $this->setAttribute('error_message', $errorMessage);
    }

    public function getErrorMessage(): ?string
    {
        return $this->getAttributeFromArray('error_message') ?: null;
    }

    public function setStartedAt(\DateTimeImmutable $date): void
    {
        $this->setAttribute('started_at', $date->format('Y-m-d H:i:s'));
    }

    public function getStartedAt(): string
    {
        return (string)$this->getAttributeFromArray('started_at');
    }

    public function setFinishedAt(?\DateTimeImmutable $date): void
    {
        $this->setAttribute('finished_at', $date?->format('Y-m-d H:i:s'));
    }

    public function getFinishedAt(): ?string
    {
        return (string)$this->getAttributeFromArray('finished_at') ?: null;
    }
}

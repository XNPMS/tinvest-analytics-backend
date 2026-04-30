<?php

declare(strict_types=1);

namespace Tinvest\Service;

use Tinvest\Entity\TinvestSyncProcesses;
use Tinvest\Enum\SyncAction;
use Tinvest\Enum\SyncStatus;
use Tinvest\Repository\TinvestSyncProcessesRepository;

readonly class TinvestSyncProcessesService
{
    public function __construct(private TinvestSyncProcessesRepository $repository)
    {
    }

    public function findActiveByAccountId(int $accountId): ?TinvestSyncProcesses
    {
        return $this->repository->findActiveByAccountId($accountId);
    }

    public function createProcess(
        int $userId,
        int $accountId,
        string $jobId,
        SyncAction $action,
    ): TinvestSyncProcesses {
        $process = new TinvestSyncProcesses();
        $process->setUserId($userId);
        $process->setAccountId($accountId);
        $process->setJobId($jobId);
        $process->setAction($action->value);
        $process->setStatus(SyncStatus::RUNNING->value);
        $process->setProgress(0);
        $process->setSyncedCount(0);
        $process->setStartedAt(new \DateTimeImmutable());
        $process->save();

        return $process;
    }

    public function updateSyncedCount(TinvestSyncProcesses $process, int $syncedCount): void
    {
        $process->setSyncedCount($syncedCount);
        $process->save();
    }

    public function markCompleted(TinvestSyncProcesses $process, int $syncedCount): void
    {
        $process->setStatus(SyncStatus::COMPLETED->value);
        $process->setProgress(100);
        $process->setSyncedCount($syncedCount);
        $process->setFinishedAt(new \DateTimeImmutable());
        $process->save();
    }

    public function markFailed(TinvestSyncProcesses $process, string $errorMessage): void
    {
        $process->setStatus(SyncStatus::FAILED->value);
        $process->setErrorMessage($errorMessage);
        $process->setFinishedAt(new \DateTimeImmutable());
        $process->save();
    }
}

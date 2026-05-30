<?php

declare(strict_types=1);

namespace Tinvest\UseCase;

use Psr\Log\LoggerInterface;
use Psr\SimpleCache\InvalidArgumentException;
use Throwable;
use Tinvest\Entity\TinvestAccount;
use Tinvest\Enum\SyncAction;
use Tinvest\Enum\SyncStatus;
use Tinvest\Service\InstrumentService;
use Tinvest\Service\SyncProgressPublisher;
use Tinvest\Service\TinvestApiService;
use Tinvest\Service\TinvestOperationService;
use Tinvest\Service\TinvestSyncProcessesService;

/**
 * Синхронизирует операции для одного брокерского счёта.
 * Управляет всем жизненным циклом процесса синхронизации: создание, прогресс, завершение/ошибка.
 *
 * Прогресс рассчитывается точно: сначала отдельным проходом считается total_count,
 * затем при синхронизации progress = synced_count / total_count * 100.
 */
readonly class SyncAccountOperationsUseCase
{
    private const BATCH_SIZE = 500;
    /**
     * Граница прогресса: операции занимают 0–SYNC_PROGRESS_MAX%, пайплайн начинается с этой отметки
     */
    private const SYNC_PROGRESS_MAX = 30;

    public function __construct(
        private LoggerInterface $logger,
        private TinvestApiService $apiService,
        private TinvestOperationService $operationService,
        private TinvestSyncProcessesService $syncProcessesService,
        private SyncProgressPublisher $progressPublisher,
        private InstrumentService $instrumentService,
    ) {
    }

    /**
     * Возвращает счёт при успешной синхронизации, null — если синхронизация уже идёт или завершилась ошибкой.
     */
    public function execute(string $token, TinvestAccount $account, string $jobId): ?TinvestAccount
    {
        $activeProcess = $this->syncProcessesService->findActiveByAccountId($account->getId(), $account->getUserId());
        if ($activeProcess !== null) {
            $this->logger->info('Sync already running for account', [
                'account_id' => $account->getAccountId(),
                'job_id' => $activeProcess->getJobId(),
            ]);

            return null;
        }

        $existingProcess = $this->syncProcessesService->findByJobAndAccountId($jobId, $account->getId());
        if ($existingProcess !== null) {
            $isCompleted = $existingProcess->getStatus() === SyncStatus::COMPLETED;
            $isFailedButSynced = $existingProcess->getStatus() === SyncStatus::FAILED && $account->isSynced();

            if ($isCompleted || $isFailedButSynced) {
                $this->logger->info('Sync already completed for this job, skipping', [
                    'account_id' => $account->getAccountId(),
                    'job_id' => $jobId,
                ]);

                return $account;
            }

            return null;
        }

        $process = $this->syncProcessesService->createProcess(
            $account->getUserId(),
            $account->getId(),
            $jobId,
            SyncAction::OPERATIONS,
        );

        $totalCount = 0;
        $syncedCount = 0;

        try {
            $totalCount = $this->apiService->countOperations($token, $account);
            $this->syncProcessesService->setTotalCount($process, $totalCount);

            $this->logger->info('Operations counted', [
                'account_id' => $account->getAccountId(),
                'total_count' => $totalCount,
            ]);

            $this->apiService->streamOperations(
                $token,
                $account,
                function (array $batch) use ($account, $process, $jobId, $totalCount, &$syncedCount) {
                    $this->operationService->saveOperationsBatch($account->getId(), $batch);
                    $this->instrumentService->saveFromOperationsBatch($batch);

                    $syncedCount += count($batch);
                    // не даёт достичь 30% во время стриминга, потому что 30% - это финальная отметка,
                    // которая публикуется отдельно после того как streamOperations завершился
                    // и аккаунт помечен синхронизированным
                    $progress = $totalCount > 0
                        ? (int)min(
                            self::SYNC_PROGRESS_MAX - 1,
                            // линейно маппит количество сохраненных операций на диапазон 0–30%
                            round($syncedCount / $totalCount * self::SYNC_PROGRESS_MAX)
                        )
                        : 0;

                    $this->syncProcessesService->updateSyncedCount($process, $syncedCount, $progress);
                    $this->progressPublisher->publish(
                        $jobId,
                        (int)$account->getAccountId(),
                        $syncedCount,
                        SyncStatus::RUNNING,
                        $progress,
                        $totalCount,
                    );
                },
                self::BATCH_SIZE,
            );

            $this->syncProcessesService->markCompleted($process, $syncedCount);

            $account->markSynced();
            $account->save();

            $this->progressPublisher->publish(
                $jobId,
                (int)$account->getAccountId(),
                $syncedCount,
                SyncStatus::RUNNING,
                self::SYNC_PROGRESS_MAX,
                $totalCount,
            );

            $this->logger->info('Sync completed', [
                'account_id' => $account->getAccountId(),
                'synced_count' => $syncedCount,
                'total_count' => $totalCount,
            ]);

            return $account;
        } catch (Throwable | InvalidArgumentException $e) {
            $this->logger->error('Sync failed', [
                'account_id' => $account->getAccountId(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->syncProcessesService->markFailed($process, $e->getMessage());
            $this->progressPublisher->publish(
                $jobId,
                (int)$account->getAccountId(),
                $syncedCount,
                SyncStatus::FAILED,
                0,
                $totalCount,
                $e->getMessage(),
            );

            return null;
        }
    }
}

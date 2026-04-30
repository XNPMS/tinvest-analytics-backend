<?php

declare(strict_types=1);

namespace Tinvest\UseCase;

use Psr\Log\LoggerInterface;
use Tinvest\Enum\SyncAction;
use Tinvest\Enum\SyncStatus;
use Tinvest\Exception\TinvestGrpcException;
use Tinvest\Message\AccountsMessage;
use Tinvest\Service\SyncProgressPublisher;
use Tinvest\Service\TinvestAccountService;
use Tinvest\Service\TinvestApiService;
use Tinvest\Service\TinvestOperationService;
use Tinvest\Service\TinvestSyncProcessesService;
use User\Service\UserService;

readonly class SyncOperationsUseCase
{
    public function __construct(
        private LoggerInterface $logger,
        private TinvestApiService $apiService,
        private TinvestAccountService $accountService,
        private TinvestOperationService $operationService,
        private UserService $userService,
        private TinvestSyncProcessesService $syncProcessesService,
        private SyncProgressPublisher $progressPublisher,
    ) {
    }

    /**
     * @throws TinvestGrpcException
     * @throws \JsonException
     */
    public function execute(AccountsMessage $accountsMessage, string $jobId): void
    {
        $user = $this->userService->getUserById($accountsMessage->userId);

        if (!$user) {
            $this->logger->error('SyncOperationsUseCase: user not found', ['user_id' => $accountsMessage->userId]);

            return;
        }

        foreach ($accountsMessage->accountIds as $tinvestAccountDbId) {
            $tinvestAccountDbId = (int)$tinvestAccountDbId;

            $this->logger->info('Starting sync operations', [
                'account_db_id' => $tinvestAccountDbId,
                'job_id'        => $jobId,
            ]);

            $tinvestAccount = $this->accountService->getTinvestAccountById(
                $tinvestAccountDbId,
                $accountsMessage->userId
            );

            if (!$tinvestAccount) {
                $this->logger->error('Account not found', [
                    'account_db_id' => $tinvestAccountDbId,
                    'user_id'       => $accountsMessage->userId,
                ]);

                continue;
            }

            $activeProcess = $this->syncProcessesService->findActiveByAccountId($tinvestAccount->getId());

            if ($activeProcess !== null) {
                $this->logger->info('Sync already running for account', [
                    'account_id' => $tinvestAccount->getId(),
                    'job_id'     => $activeProcess->getJobId(),
                ]);

                continue;
            }

            $process = $this->syncProcessesService->createProcess(
                $accountsMessage->userId,
                $tinvestAccount->getId(),
                $jobId,
                SyncAction::OPERATIONS,
            );

            $syncedCount = 0;

            try {
                $this->apiService->streamOperations(
                    $user->getTinvestToken(),
                    $tinvestAccount,
                    function (array $batch) use ($tinvestAccount, $process, $jobId, &$syncedCount) {
                        $this->operationService->saveOperationsBatch($tinvestAccount->getId(), $batch);

                        $syncedCount += count($batch);

                        $this->syncProcessesService->updateSyncedCount($process, $syncedCount);

                        $this->progressPublisher->publish(
                            $jobId,
                            $tinvestAccount->getId(),
                            $syncedCount,
                            SyncStatus::RUNNING,
                        );
                    },
                    500
                );

                $this->syncProcessesService->markCompleted($process, $syncedCount);

                $this->progressPublisher->publish(
                    $jobId,
                    $tinvestAccount->getId(),
                    $syncedCount,
                    SyncStatus::COMPLETED,
                );

                $this->logger->info('Sync completed', [
                    'account_id'   => $tinvestAccount->getId(),
                    'synced_count' => $syncedCount,
                ]);
            } catch (\Throwable $e) {
                $this->logger->error('Sync failed', [
                    'account_id' => $tinvestAccount->getId(),
                    'error'      => $e->getMessage(),
                ]);

                $this->syncProcessesService->markFailed($process, $e->getMessage());

                $this->progressPublisher->publish(
                    $jobId,
                    $tinvestAccount->getId(),
                    $syncedCount,
                    SyncStatus::FAILED,
                    $e->getMessage(),
                );
            }
        }
    }
}

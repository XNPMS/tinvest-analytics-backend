<?php

declare(strict_types=1);

namespace Tinvest\Worker;

use Psr\Log\LoggerInterface;
use System\Queue\Client\RabbitMQ;
use System\Queue\Enum\Workers;
use System\Queue\Worker\AbstractWorker;
use Tinvest\DTO\SyncContext;
use Tinvest\Entity\TinvestAccount;
use Tinvest\Message\AccountsMessage;
use Tinvest\Service\BrokerTokenService;
use Tinvest\Service\SyncPipelineService;
use Tinvest\Service\TinvestAccountService;
use Tinvest\UseCase\SyncAccountOperationsUseCase;
use User\Enum\OnboardingStep;
use User\Service\UserService;

class SyncOnboardingAccountWorker extends AbstractWorker
{
    protected Workers $queueName = Workers::SYNC_ONBOARDING_ACCOUNTS;

    public function __construct(
        private readonly RabbitMQ $client,
        private readonly LoggerInterface $logger,
        private readonly UserService $userService,
        private readonly BrokerTokenService $brokerTokenService,
        private readonly TinvestAccountService $accountService,
        private readonly SyncAccountOperationsUseCase $syncAccountOperationsUseCase,
        private readonly SyncPipelineService $pipeline,
    ) {
        parent::__construct($client, $logger);
    }

    public function process(array $payload): void
    {
        $message = AccountsMessage::fromArray($payload);
        $jobId = (string)($payload['message_id'] ?? '');

        $this->logger->info('Started sync process', [
            'message_id' => $jobId,
            'user_id' => $userId = $message->userId,
            'account_ids' => $message->accountIds,
        ]);

        $user = $this->userService->getUserById($userId);
        if (!$user) {
            $this->logger->error('User not found', ['user_id' => $userId]);

            return;
        }

        $token = $this->brokerTokenService->getDecryptedToken($user->getId());
        $syncedAccounts = [];

        foreach (array_unique($message->accountIds) as $accountId) {
            $account = $this->accountService->getTinvestAccountById((int)$accountId, $user->getId());

            if (!$account) {
                $this->logger->error('Account not found', [
                    'account_id' => $accountId,
                    'user_id' => $user->getId(),
                ]);

                continue;
            }

            $synced = $this->syncAccountOperationsUseCase->execute($token, $account, $jobId);
            if ($synced !== null) {
                $syncedAccounts[] = $synced;
            }
        }

        if ($syncedAccounts) {
            $this->pipeline->run(new SyncContext(
                jobId: $jobId,
                token: $token,
                userId: $userId,
                syncedAccounts: $syncedAccounts,
                accountIds: array_map(static fn(TinvestAccount $a) => $a->getId(), $syncedAccounts),
                brokerAccountIds: array_map(static fn(TinvestAccount $a) => (int)$a->getAccountId(), $syncedAccounts),
            ));
        }

        $this->userService->updateOnboardingStep($user, OnboardingStep::READY);
    }
}

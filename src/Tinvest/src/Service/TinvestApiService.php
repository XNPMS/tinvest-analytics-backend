<?php

declare(strict_types=1);

namespace Tinvest\Service;

use DateTimeImmutable;
use Google\Protobuf\Timestamp;
use JsonException;
use Metaseller\TinkoffInvestApi2\TinkoffClientsFactory;
use stdClass;
use Tinkoff\Invest\V1\Account;
use Tinkoff\Invest\V1\AccountStatus;
use Tinkoff\Invest\V1\GetAccountsRequest;
use Tinkoff\Invest\V1\GetAccountsResponse;
use Tinkoff\Invest\V1\GetOperationsByCursorRequest;
use Tinkoff\Invest\V1\GetOperationsByCursorResponse;
use Tinkoff\Invest\V1\OperationItem;
use Tinkoff\Invest\V1\OperationState;
use Tinvest\Entity\TinvestAccount;
use Tinvest\Enum\LimitTokens;
use Tinvest\Exception\TinvestGrpcException;

readonly class TinvestApiService
{
    private const DATE_FORMAT = 'Y-m-d H:i:s';
    private const MAX_LIMIT_OPERATIONS = 1000;

    public function __construct(
        private RateLimiter $rateLimiter,
    ) {
    }

    public function getApiClient(string $token): TinkoffClientsFactory
    {
        return TinkoffClientsFactory::create($token);
    }

    /**
     * @throws TinvestGrpcException
     * @throws JsonException
     */
    public function getAllAccountsTinvest(string $token): array
    {
        $apiClient = $this->getApiClient($token);
        $accounts = [];

        /** @var GetAccountsResponse $response */
        [$response, $status] = $apiClient->usersServiceClient
            ->GetAccounts((new GetAccountsRequest())->setStatus(AccountStatus::ACCOUNT_STATUS_ALL))
            ->wait();

        $this->handleGrpcError($status);
        /** @var Account $account */
        foreach ($response->getAccounts() as $account) {
            $accounts[] = [
                'account_id' => $account->getId(),
                'name' => $account->getName(),
                'status' => $account->getStatus(),
                'type' => $account->getType(),
                'opened_date' => $account->getOpenedDate()
                    ? $account->getOpenedDate()->toDateTime()->format(self::DATE_FORMAT)
                    : null,
                'access_level' => $account->getAccessLevel(),
                'created_at' => date(self::DATE_FORMAT),
                'updated_at' => date(self::DATE_FORMAT),
            ];
        }

        return $accounts;
    }

    /**
     * @throws TinvestGrpcException
     * @throws JsonException
     * @throws \Exception
     */
    public function getAllOperationsTinvest(string $token, TinvestAccount $tinvestAccount): array
    {
        $apiClient = $this->getApiClient($token);
        $operations = [];
        $hasNext = true;
        $cursor = '';

        while ($hasNext) {
            $this->rateLimiter
                ->setMaxTokens(LimitTokens::MAX_TOKENS_SERVICE_OPERATIONS)
                ->acquire();

            /** @var GetOperationsByCursorResponse $response */
            [$response, $status] = $apiClient->operationsServiceClient
                ->GetOperationsByCursor(
                    (new GetOperationsByCursorRequest())
                        ->setCursor($cursor)
                        ->setLimit(self::MAX_LIMIT_OPERATIONS)
                        ->setAccountId($tinvestAccount->getAccountId())
                        ->setFrom(((new Timestamp())->setSeconds(
                            ((new DateTimeImmutable($tinvestAccount->getOpenedDate()))->getTimestamp())
                        )))
                        ->setTo((new Timestamp())->setSeconds(
                            ((new DateTimeImmutable('now'))->getTimestamp())
                        ))
                        ->setState(OperationState::OPERATION_STATE_EXECUTED)
                )
                ->wait();

            $this->handleGrpcError($status);
            /** @var OperationItem $operation */
            foreach ($response->getItems() as $operation) {
                $operations[] = [
                    'operation_id' => $operation->getId(),
                    'name' => $operation->getName(),
                    'date' => date(self::DATE_FORMAT, $operation->getDate()?->getSeconds()),
                    'description' => $operation->getDescription(),
                ];
            }

            $hasNext = $response->getHasNext();
            $cursor = $response->getNextCursor();
        }

        return $operations;
    }

    /**
     * @throws TinvestGrpcException
     * @throws JsonException
     */
    private function handleGrpcError(stdClass $status): void
    {
        if (($status->code ?? null) !== 0 && !empty($status->details)) {
            throw new TinvestGrpcException(
                sprintf(
                    '[%s] Tinvest gRPC error: code=%s details=%s metadata=%s',
                    date(self::DATE_FORMAT),
                    $status->code,
                    $status->details,
                    json_encode($status->metadata, JSON_THROW_ON_ERROR)
                )
            );
        }
    }
}

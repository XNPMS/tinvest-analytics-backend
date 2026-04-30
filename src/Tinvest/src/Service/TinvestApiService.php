<?php

declare(strict_types=1);

namespace Tinvest\Service;

use DateTimeImmutable;
use Exception;
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
use Tinkoff\Invest\V1\PortfolioPosition;
use Tinkoff\Invest\V1\PortfolioRequest;
use Tinkoff\Invest\V1\PortfolioResponse;
use Tinvest\DTO\TinvestOperationDto;
use Tinvest\Entity\TinvestAccount;
use Tinvest\Enum\LimitTokens;
use Tinvest\Exception\TinvestGrpcException;

readonly class TinvestApiService
{
    private const DATE_FORMAT = 'Y-m-d H:i:s';
    private const MAX_LIMIT_OPERATIONS = 1000;

    public function __construct()
    {
    }

    private function getApiClient(string $token): TinkoffClientsFactory
    {
        return TinkoffClientsFactory::create($token);
    }

    /**
     * Исходя из открытах источников, у юзеров может быть до 10 открытых счетов
     *
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
     * @throws Exception
     */
    public function streamOperations(
        string $token,
        TinvestAccount $tinvestAccount,
        callable $callable,
        int $batchSize,
        string $cursor = '',
    ): void {
        $apiClient = $this->getApiClient($token);
        $rateLimiter = new RateLimiter(LimitTokens::MAX_TOKENS_SERVICE_OPERATIONS);

        $hasNext = true;
        $batch = [];

        while ($hasNext) {
            $rateLimiter->consume();
            /** @var GetOperationsByCursorResponse $response */
            [$response, $status] = $apiClient->operationsServiceClient
                ->GetOperationsByCursor(
                    (new GetOperationsByCursorRequest())
                        ->setCursor($cursor)
                        ->setLimit(self::MAX_LIMIT_OPERATIONS)
                        ->setAccountId($tinvestAccount->getAccountId())
                        ->setFrom($this->createTimestamp($tinvestAccount->getOpenedDate()))
                        ->setTo($this->createTimestamp())
                        ->setState(OperationState::OPERATION_STATE_EXECUTED)
                )
                ->wait();

            $this->handleGrpcError($status);
            /** @var OperationItem $operation */
            foreach ($response->getItems() as $operation) {
                // есть свойство brokerAccountId
                $batch[] = new TinvestOperationDto(
                    $operation->getId(),
                    $operation->getParentOperationId(),
                    $operation->getName(),
                    $operation->getPayment()?->getCurrency(),
                    (int)$operation->getPayment()?->getUnits(),
                    $operation->getPayment()?->getNano(),
                    $operation->getPrice()?->serializeToJsonString(),
                    $operation->getState(),
                    (float)$operation->getQuantity(),
                    (float)$operation->getQuantityRest(),
                    $operation->getFigi(),
                    $operation->getInstrumentType(),
                    date(self::DATE_FORMAT, $operation->getDate()?->getSeconds()),
                    $operation->getType(),
                    $operation->getTradesInfo()?->serializeToJsonString(),
                    $operation->getAssetUid(),
                    $operation->getPositionUid(),
                    $operation->getTicker(),
                    $operation->getInstrumentUid(),
                    $operation->getDescription(),
                    json_encode(
                        iterator_to_array($operation->getChildOperations()?->getIterator()),
                        JSON_THROW_ON_ERROR
                    ),
                );

                // Если батч достиг размера, вызываем callback
                if (count($batch) >= $batchSize) {
                    $callable($batch);
                    $batch = [];
                }
            }

            $hasNext = $response->getHasNext();
            $cursor = $response->getNextCursor();
        }

        // Обработка оставшегося батча
        if ($batch) {
            $callable($batch);
        }
    }

    /**
     * @throws TinvestGrpcException
     * @throws JsonException
     */
    public function getPortfolio(string $token, string $accountId): array
    {
        $apiClient = $this->getApiClient($token);

        /** @var PortfolioResponse $response */
        [$response, $status] = $apiClient->operationsServiceClient
            ->GetPortfolio((new PortfolioRequest())->setAccountId($accountId))
            ->wait();

        $this->handleGrpcError($status);

        /** @var PortfolioPosition $position */
        foreach ($response->getPositions() as $position) {
            var_dump($position->getQuantity()->serializeToJsonString());
        }

        die();
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

    /**
     * @throws Exception
     */
    private function createTimestamp(string $dateTime = 'now'): \Google\Protobuf\Timestamp
    {
        return (new Timestamp())->setSeconds(((new DateTimeImmutable($dateTime))->getTimestamp()));
    }
}

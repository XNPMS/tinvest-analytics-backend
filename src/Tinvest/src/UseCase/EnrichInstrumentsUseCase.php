<?php

declare(strict_types=1);

namespace Tinvest\UseCase;

use JsonException;
use Psr\Log\LoggerInterface;
use Tinvest\Enum\LimitTokens;
use Tinvest\Exception\TinvestGrpcException;
use Tinvest\Repository\InstrumentRepository;
use Tinvest\Service\RateLimiter;
use Tinvest\Service\TinvestApiService;

readonly class EnrichInstrumentsUseCase
{
    public function __construct(
        private TinvestApiService $apiService,
        private InstrumentRepository $instrumentRepository,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Обогащает инструменты полными данными (sector, exchange, isin, lot_size, name).
     * Запускается после сохранения инструментов из операций.
     * Пропускает уже обогащённые записи (exchange IS NOT NULL).
     *
     * @param int[] $accountIds
     * @throws JsonException|TinvestGrpcException
     */
    public function execute(string $token, array $accountIds, ?callable $onProgress = null): void
    {
        $instruments = $this->instrumentRepository->findUnenrichedByAccountIds($accountIds);

        if (!$instruments) {
            return;
        }

        $rateLimiter = new RateLimiter(LimitTokens::MAX_TOKENS_SERVICE_INSTRUMENTS);
        $enriched = [];
        $total = count($instruments);
        $done = 0;

        foreach ($instruments as ['figi' => $figi, 'asset_type' => $assetType]) {
            $rateLimiter->consume();

            $details = $this->apiService->getInstrumentDetails($token, $figi, $assetType);

            if ($details === null) {
                $this->logger->warning('EnrichInstrumentsUseCase: instrument not found', [
                    'figi' => $figi,
                    'asset_type' => $assetType,
                ]);

                continue;
            }

            $enriched[] = $details;

            $done++;
            if ($onProgress !== null) {
                $onProgress($done, $total);
            }
        }

        if ($enriched) {
            $this->instrumentRepository->enrichBatch($enriched);
        }
    }
}

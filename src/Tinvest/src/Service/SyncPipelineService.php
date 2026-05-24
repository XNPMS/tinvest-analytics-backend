<?php

declare(strict_types=1);

namespace Tinvest\Service;

use JsonException;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\InvalidArgumentException;
use Throwable;
use Tinvest\DTO\SyncContext;
use Tinvest\Enum\SyncStatus;
use Tinvest\Exception\TinvestGrpcException;
use Tinvest\UseCase\BackfillPortfolioHistoryUseCase;
use Tinvest\UseCase\CaptureCurrentPortfolioUseCase;
use Tinvest\UseCase\EnrichInstrumentsUseCase;
use Tinvest\UseCase\FetchCurrencyRatesUseCase;
use Tinvest\UseCase\RecalculatePaymentRubUseCase;

/**
 * Пайплайн пост-синхронизации: запускает шаги обработки после того,
 * как операции по счетам уже загружены в базу.
 *
 * Каждый шаг объявляет свой диапазон прогресса [start, end] в STEP_RANGES.
 * Добавить новый шаг = добавить запись в STEP_RANGES и приватный метод run*().
 */
class SyncPipelineService
{
    /**
     * Диапазоны прогресса каждого шага [start%, end%].
     * Итого: 30-100 (0-30 занимает SyncAccountOperationsUseCase внутри себя).
     */
    private const STEP_RANGES = [
        'enrich_instruments' => [30, 50],
        'fetch_currency_rates' => [50, 52],
        'recalculate_payments' => [52, 55],
        'backfill_history' => [55, 92],
        'capture_portfolio' => [92, 100],
    ];

    public function __construct(
        private readonly EnrichInstrumentsUseCase $enrichInstrumentsUseCase,
        private readonly FetchCurrencyRatesUseCase $fetchCurrencyRatesUseCase,
        private readonly RecalculatePaymentRubUseCase $recalculatePaymentRubUseCase,
        private readonly BackfillPortfolioHistoryUseCase $backfillPortfolioHistoryUseCase,
        private readonly CaptureCurrentPortfolioUseCase $captureCurrentPortfolioUseCase,
        private readonly SyncProgressPublisher $progressPublisher,
        private readonly TinvestSyncProcessesService $syncProcessesService,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @throws InvalidArgumentException
     * @throws JsonException
     */
    public function run(SyncContext $ctx): void
    {
        try {
            $this->runEnrich($ctx);
            $this->runFetchRates($ctx);
            $this->runRecalculate($ctx);
            $this->runBackfill($ctx);
            $this->runCapture($ctx);
        } catch (Throwable | InvalidArgumentException $e) {
            $this->logger->error('Post-sync pipeline failed', [
                'user_id' => $ctx->userId,
                'error' => $e->getMessage(),
                'trace' => $e->getTrace(),
            ]);
        }

        $this->publishCompleted($ctx);
    }

    /**
     * @throws JsonException
     * @throws InvalidArgumentException
     * @throws TinvestGrpcException
     */
    private function runEnrich(SyncContext $ctx): void
    {
        [$start, $end] = self::STEP_RANGES['enrich_instruments'];
        $this->publishForAll($ctx, 'enrich_instruments', $start);

        $this->enrichInstrumentsUseCase->execute(
            $ctx->token,
            $ctx->accountIds,
            function (int $current, int $total) use ($ctx, $start, $end): void {
                $progress = $total > 0 ? (int)($start + ($current / $total) * ($end - $start)) : $start;
                $this->publishForAll($ctx, 'enrich_instruments', $progress);
            }
        );
    }

    /**
     * @throws InvalidArgumentException
     * @throws JsonException
     */
    private function runFetchRates(SyncContext $ctx): void
    {
        $this->publishForAll($ctx, 'fetch_currency_rates', self::STEP_RANGES['fetch_currency_rates'][0]);
        $this->fetchCurrencyRatesUseCase->execute($ctx->token, $ctx->accountIds);
    }

    /**
     * @throws InvalidArgumentException
     * @throws JsonException
     */
    private function runRecalculate(SyncContext $ctx): void
    {
        $this->publishForAll($ctx, 'recalculate_payments', self::STEP_RANGES['recalculate_payments'][0]);
        $this->recalculatePaymentRubUseCase->execute($ctx->accountIds);
    }

    /**
     * @throws InvalidArgumentException
     * @throws JsonException
     */
    private function runBackfill(SyncContext $ctx): void
    {
        [$rangeStart, $rangeEnd] = self::STEP_RANGES['backfill_history'];
        $rangeSize = $rangeEnd - $rangeStart;
        $accountCount = count($ctx->syncedAccounts);

        foreach ($ctx->syncedAccounts as $i => $account) {
            $accountStart = (int)($rangeStart + ($i / $accountCount) * $rangeSize);
            $accountEnd = (int)($rangeStart + (($i + 1) / $accountCount) * $rangeSize);
            $accountSpan = $accountEnd - $accountStart;
            $accountId = (int)$account->getAccountId();

            $this->progressPublisher->publishStep($ctx->jobId, $accountId, 'backfill_history', $accountStart);

            $this->backfillPortfolioHistoryUseCase->execute(
                $account,
                $ctx->token,
                function (int $current, int $total) use ($ctx, $account, $accountStart, $accountSpan): void {
                    if ($total <= 0) {
                        return;
                    }
                    $this->progressPublisher->publishStep(
                        $ctx->jobId,
                        (int)$account->getAccountId(),
                        'backfill_history',
                        (int)($accountStart + ($current / $total) * $accountSpan),
                    );
                }
            );
        }
    }

    /**
     * @throws InvalidArgumentException
     * @throws JsonException
     */
    private function runCapture(SyncContext $ctx): void
    {
        $this->publishForAll($ctx, 'capture_portfolio', self::STEP_RANGES['capture_portfolio'][0]);
        $this->captureCurrentPortfolioUseCase->execute($ctx->userId, $ctx->token, $ctx->brokerAccountIds);
    }

    /**
     * @throws InvalidArgumentException
     * @throws JsonException
     */
    private function publishCompleted(SyncContext $ctx): void
    {
        foreach ($ctx->syncedAccounts as $account) {
            $process = $this->syncProcessesService->findLatestByAccountId($account->getId());
            $this->progressPublisher->publish(
                $ctx->jobId,
                (int)$account->getAccountId(),
                $process?->getSyncedCount() ?? 0,
                SyncStatus::COMPLETED,
                100,
                $process?->getTotalCount() ?? 0,
            );
        }
    }

    /**
     * @throws InvalidArgumentException
     * @throws JsonException
     */
    private function publishForAll(SyncContext $ctx, string $step, int $progress): void
    {
        foreach ($ctx->syncedAccounts as $account) {
            $this->progressPublisher->publishStep($ctx->jobId, (int)$account->getAccountId(), $step, $progress);
        }
    }
}

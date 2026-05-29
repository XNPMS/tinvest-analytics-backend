<?php

declare(strict_types=1);

namespace Tinvest\UseCase;

use Tinvest\DTO\DividendCalendar;
use Tinvest\DTO\DividendHistoryEntry;
use Tinvest\DTO\UpcomingDividend;
use Tinvest\Entity\TinvestAccount;
use Tinvest\Entity\TinvestOperation;
use Tinvest\Repository\ExpectedDividendRepository;
use Tinvest\Repository\InstrumentRepository;
use Tinvest\Repository\TinvestAccountRepository;
use Tinvest\Repository\TinvestOperationRepository;

readonly class GetDividendCalendarUseCase
{
    public function __construct(
        private TinvestAccountRepository $accountRepository,
        private TinvestOperationRepository $operationRepository,
        private InstrumentRepository $instrumentRepository,
        private ExpectedDividendRepository $dividendRepository,
    ) {
    }

    public function execute(string $userId): ?DividendCalendar
    {
        $accounts = $this->accountRepository->findSyncedByUserId($userId);
        if ($accounts->isEmpty()) {
            return null;
        }

        $accountIds = array_map(
            static fn(TinvestAccount $account) => $account->getId(),
            $accounts->all()
        );

        $tickers = $this->operationRepository->findDistinctTickersByAccountIds($accountIds);
        $instrumentIds = $this->instrumentRepository->findIdsByTickers($tickers);

        $upcoming = $this->dividendRepository
            ->findUpcomingByInstrumentIds($instrumentIds)
            ->map(static fn($row) => new UpcomingDividend(
                $row->ticker,
                $row->name,
                $row->record_date,
                $row->payment_date,
                round((float)$row->amount_per_share, 4),
                strtolower($row->currency),
                $row->amount_rub !== null ? round((float)$row->amount_rub, 2) : null,
                (bool)$row->is_confirmed,
            ))
            ->values()
            ->toArray();

        $history = $this->operationRepository
            ->findDividendsByAccountIds($accountIds)
            ->map(static fn(TinvestOperation $operation) => new DividendHistoryEntry(
                $operation->getTicker(),
                $operation->getName(),
                $operation->getDate(),
                round((float)$operation->getPaymentRub(), 2),
                $operation->getPayment(),
                $operation->getPaymentCurrency() !== null ? strtolower($operation->getPaymentCurrency()) : null,
                $operation->getOperationType(),
            ))
            ->values()
            ->toArray();

        $totalRub = $this->operationRepository->getTotalDividendsRubByAccountIds($accountIds);

        return new DividendCalendar(
            $upcoming,
            $history,
            round($totalRub, 2),
        );
    }
}

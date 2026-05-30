<?php

declare(strict_types=1);

namespace Tinvest\Model\Api;

use Tinvest\DTO\DividendCalendar;
use Tinvest\DTO\DividendHistoryEntry;
use Tinvest\DTO\UpcomingDividend;

use function array_map;

final readonly class DividendCalendarApiResponse
{
    private function __construct(
        private array $upcoming,
        private array $history,
        private float $totalRub,
    ) {
    }

    public static function fromDividendCalendar(DividendCalendar $calendar): self
    {
        return new self(
            array_map(
                static fn(UpcomingDividend $d): array => [
                    'ticker' => $d->ticker,
                    'name' => $d->name,
                    'record_date' => $d->recordDate,
                    'payment_date' => $d->paymentDate,
                    'amount_per_share' => $d->amountPerShare,
                    'currency' => $d->currency,
                    'amount_rub' => $d->amountRub,
                    'is_confirmed' => $d->isConfirmed,
                ],
                $calendar->upcoming,
            ),
            array_map(
                static fn(DividendHistoryEntry $e): array => [
                    'ticker' => $e->ticker,
                    'name' => $e->name,
                    'date' => $e->date,
                    'payment_rub' => $e->paymentRub,
                    'payment' => $e->payment,
                    'payment_currency' => $e->paymentCurrency,
                    'operation_type' => $e->operationType,
                ],
                $calendar->history,
            ),
            $calendar->totalRub,
        );
    }

    public function toApi(): array
    {
        return [
            'upcoming' => $this->upcoming,
            'history' => $this->history,
            'total_rub' => $this->totalRub,
        ];
    }
}

<?php

declare(strict_types=1);

namespace Tinvest\Model\Api;

use Tinvest\DTO\InstrumentPerformance;

use function array_map;

final readonly class InstrumentsPerformanceApiResponse
{
    private function __construct(
        private array $instruments,
    ) {
    }

    /** @param InstrumentPerformance[] $instruments */
    public static function fromInstrumentsList(array $instruments): self
    {
        return new self(
            array_map(
                static fn(InstrumentPerformance $i): array => [
                    'ticker' => $i->ticker,
                    'name' => $i->name,
                    'figi' => $i->figi,
                    'instrument_type' => $i->instrumentType,
                    'is_open' => $i->isOpen,
                    'quantity' => $i->quantity,
                    'avg_price_rub' => $i->avgPriceRub,
                    'current_price_rub' => $i->currentPriceRub,
                    'realized_pnl_rub' => $i->realizedPnlRub,
                    'realized_pnl_percent' => $i->realizedPnlPercent,
                    'unrealized_pnl_rub' => $i->unrealizedPnlRub,
                    'total_pnl_rub' => $i->totalPnlRub,
                    'total_pnl_percent' => $i->totalPnlPercent,
                    'annualized_pnl_percent' => $i->annualizedPnlPercent,
                    'today_pnl_rub' => $i->todayPnlRub,
                    'today_pnl_percent' => $i->todayPnlPercent,
                ],
                $instruments,
            ),
        );
    }

    public function toApi(): array
    {
        return ['instruments' => $this->instruments];
    }
}

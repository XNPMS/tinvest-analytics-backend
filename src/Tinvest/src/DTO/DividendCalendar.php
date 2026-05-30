<?php

declare(strict_types=1);

namespace Tinvest\DTO;

final readonly class DividendCalendar
{
    /**
     * @param UpcomingDividend[] $upcoming
     * @param DividendHistoryEntry[] $history
     */
    public function __construct(
        public array $upcoming,
        public array $history,
        public float $totalRub,
    ) {
    }
}

<?php

declare(strict_types=1);

namespace Tinvest\DTO;

final readonly class DividendHistoryEntry
{
    public function __construct(
        public ?string $ticker,
        public string $name,
        public ?string $date,
        public float $paymentRub,
        public ?float $payment,
        public ?string $paymentCurrency,
        public ?string $operationType,
    ) {
    }
}

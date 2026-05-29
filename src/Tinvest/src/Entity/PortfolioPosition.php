<?php

declare(strict_types=1);

namespace Tinvest\Entity;

readonly class PortfolioPosition
{
    public function __construct(
        public string $ticker,
        public string $instrumentType,
        public float $quantity,
        public float $currentPrice,
        public string $currentPriceCurrency,
        public float $avgPrice,
        public string $avgPriceCurrency,
        public float $expectedYield,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            (string)($data['ticker'] ?? ''),
            (string)($data['instrument_type'] ?? ''),
            (float)($data['quantity'] ?? 0.0),
            (float)($data['current_price'] ?? 0.0),
            strtolower((string)($data['current_price_currency'] ?? 'rub')),
            (float)($data['avg_price'] ?? 0.0),
            strtolower((string)($data['avg_price_currency'] ?? 'rub')),
            (float)($data['expected_yield'] ?? 0.0),
        );
    }
}

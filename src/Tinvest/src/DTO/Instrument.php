<?php

declare(strict_types=1);

namespace Tinvest\DTO;

use Tinvest\Enum\AssetType;

final readonly class Instrument
{
    public function __construct(
        public string $figi,
        // Имя берётся из тикера или FIGI — временная заглушка до полного обогащения через GetInstrumentBy
        public string $name,
        public AssetType $assetType,
        public string $currency,
        public ?string $ticker,
    ) {
    }

    public function toArray(): array
    {
        return [
            'figi' => $this->figi,
            'name' => $this->name,
            'asset_type' => $this->assetType->value,
            'currency' => $this->currency,
            'ticker' => $this->ticker,
        ];
    }
}

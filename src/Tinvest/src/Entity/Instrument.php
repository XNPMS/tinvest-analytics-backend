<?php

declare(strict_types=1);

namespace Tinvest\Entity;

use Illuminate\Database\Eloquent\Model;

class Instrument extends Model
{
    public const TABLE = 'instruments';

    /** @var string */
    protected $table = self::TABLE;

    public const UPDATED_AT = 'updated_at';
    public const CREATED_AT = null;

    public function getId(): int
    {
        return (int)$this->getAttributeFromArray('id');
    }

    public function getFigi(): string
    {
        return (string)$this->getAttributeFromArray('figi');
    }

    public function getTicker(): ?string
    {
        return (string)$this->getAttributeFromArray('ticker') ?: null;
    }

    public function getIsin(): ?string
    {
        return (string)$this->getAttributeFromArray('isin') ?: null;
    }

    public function getName(): string
    {
        return (string)$this->getAttributeFromArray('name');
    }

    public function getAssetType(): string
    {
        return (string)$this->getAttributeFromArray('asset_type');
    }

    public function getSector(): ?string
    {
        return (string)$this->getAttributeFromArray('sector') ?: null;
    }

    public function getCurrency(): string
    {
        return (string)$this->getAttributeFromArray('currency');
    }

    public function getExchange(): ?string
    {
        return $this->getAttributeFromArray('exchange') ?: null;
    }

    public function getLotSize(): int
    {
        return max(1, (int)$this->getAttributeFromArray('lot_size'));
    }

    public function getNominal(): ?float
    {
        $v = $this->getAttributeFromArray('nominal');

        return $v !== null ? (float)$v : null;
    }
}

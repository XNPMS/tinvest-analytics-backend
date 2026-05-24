<?php

declare(strict_types=1);

namespace Tinvest\Entity;

use Illuminate\Database\Eloquent\Model;

class ExpectedDividend extends Model
{
    public const TABLE = 'expected_dividends';

    /** @var string */
    protected $table = self::TABLE;

    public const CREATED_AT = null;

    public function getId(): int
    {
        return (int)$this->getAttributeFromArray('id');
    }

    public function getInstrumentId(): int
    {
        return (int)$this->getAttributeFromArray('instrument_id');
    }

    public function getRecordDate(): string
    {
        return (string)$this->getAttributeFromArray('record_date');
    }

    public function getPaymentDate(): ?string
    {
        return (string)$this->getAttributeFromArray('payment_date') ?: null;
    }

    public function getAmountPerShare(): float
    {
        return (float)$this->getAttributeFromArray('amount_per_share');
    }

    public function getCurrency(): string
    {
        return (string)$this->getAttributeFromArray('currency');
    }

    public function getAmountRub(): ?float
    {
        return $this->getAttributeFromArray('amount_rub') ?: null;
    }

    public function isConfirmed(): bool
    {
        return (bool)$this->getAttributeFromArray('is_confirmed');
    }
}

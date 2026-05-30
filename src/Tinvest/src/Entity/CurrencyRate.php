<?php

declare(strict_types=1);

namespace Tinvest\Entity;

use Illuminate\Database\Eloquent\Model;

class CurrencyRate extends Model
{
    public const TABLE = 'currency_rates';

    /** @var string */
    protected $table = self::TABLE;

    public const CREATED_AT = null;

    public function getRateDate(): string
    {
        return (string)$this->getAttributeFromArray('rate_date');
    }

    public function getCurrency(): string
    {
        return (string)$this->getAttributeFromArray('currency');
    }

    public function getRateToRub(): float
    {
        return (float)$this->getAttributeFromArray('rate_to_rub');
    }
}

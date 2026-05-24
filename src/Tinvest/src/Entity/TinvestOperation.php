<?php

declare(strict_types=1);

namespace Tinvest\Entity;

use Illuminate\Database\Eloquent\Model;

/**
 * @link https://developer.tbank.ru/invest/api/operations-service-get-operations
 */
class TinvestOperation extends Model
{
    public const TABLE = 'tinvest_operations';

    /** @var string */
    protected $table = self::TABLE;

    public function getAccountId(): int
    {
        return (int)$this->getAttributeFromArray('account_id');
    }

    public function getOperationId(): string
    {
        return (string)$this->getAttributeFromArray('operation_id');
    }

    public function getPaymentCurrency(): ?string
    {
        return (string)$this->getAttributeFromArray('payment_currency') ?: null;
    }

    public function getPayment(): ?float
    {
        return (float)$this->getAttributeFromArray('payment') ?: null;
    }

    public function getCommission(): float
    {
        return (float)$this->getAttributeFromArray('commission');
    }

    public function getPaymentRub(): ?float
    {
        $value = $this->getAttributeFromArray('payment_rub');

        return is_numeric($value) ? (float)$value : null;
    }

    public function getCommissionRub(): float
    {
        return (float)$this->getAttributeFromArray('commission_rub');
    }

    public function getFxRate(): float
    {
        return (float)$this->getAttributeFromArray('fx_rate');
    }

    public function getName(): string
    {
        return (string)$this->getAttributeFromArray('name');
    }

    public function getFigi(): ?string
    {
        return (string)$this->getAttributeFromArray('figi') ?: null;
    }

    public function getInstrumentType(): ?string
    {
        return (string)$this->getAttributeFromArray('instrument_type') ?: null;
    }

    public function getQuantity(): float
    {
        return (float)$this->getAttributeFromArray('quantity');
    }

    /**
     * quantity_rest - неисполненный остаток частичной заявки
     */
    public function getQuantityRest(): float
    {
        return (float)$this->getAttributeFromArray('quantity_rest');
    }

    public function getOperationType(): ?string
    {
        return (string)$this->getAttributeFromArray('operation_type') ?: null;
    }

    public function getDate(): ?string
    {
        return (string)$this->getAttributeFromArray('date') ?: null;
    }

    public function getTicker(): ?string
    {
        return (string)$this->getAttributeFromArray('ticker');
    }

    public function getClassCode(): ?string
    {
        return (string)$this->getAttributeFromArray('class_code');
    }

    public function getInstrumentId(): ?string
    {
        return sprintf('%s_%s', $this->getTicker(), $this->getClassCode());
    }

    public function getActualQuantity(): float
    {
        return $this->getQuantity() - $this->getQuantityRest();
    }
}

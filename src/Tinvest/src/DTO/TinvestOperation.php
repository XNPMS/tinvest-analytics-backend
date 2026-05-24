<?php

declare(strict_types=1);

namespace Tinvest\DTO;

final readonly class TinvestOperationDto
{
    private const NANO_DIVISOR = 1_000_000_000;

    public function __construct(
        private string $operationId,
        private ?string $parentOperationId,
        private string $name,
        private ?string $paymentCurrency,
        private ?int $paymentUnits,
        private ?int $paymentNano,
        private ?string $price,
        private int $state,
        private float $quantity,
        private float $quantityRest,
        private ?string $figi,
        private ?string $instrumentType,
        private string $date,
        private int $type,
        private ?string $trades,
        private ?string $assetUid,
        private ?string $positionUid,
        private ?string $ticker,
        private string $classCode,
        private ?string $instrumentUid,
        private ?string $description,
        private ?string $childOperations,
        private float $commission = 0.0,
    ) {
    }

    public function getFigi(): ?string
    {
        return $this->figi ?: null;
    }

    public function getTicker(): ?string
    {
        return $this->ticker ?: null;
    }

    public function getInstrumentType(): ?string
    {
        return $this->instrumentType ?: null;
    }

    public function getPaymentCurrency(): ?string
    {
        return $this->paymentCurrency ?: null;
    }

    public function toArray(): array
    {
        return [
            'operation_id' => $this->operationId,
            'parent_operation_id' => $this->parentOperationId,
            'name' => $this->name,
            'payment_currency' => $this->paymentCurrency,
            'payment_units' => $this->paymentUnits,
            'payment_nano' => $this->paymentNano,
            // Вычисленная сумма из двух gRPC-полей (units + nano/1e9)
            'payment' => $this->paymentUnits !== null
                ? $this->paymentUnits + ($this->paymentNano ?? 0) / self::NANO_DIVISOR
                : null,
            'commission' => $this->commission,
            'price' => $this->price,
            'state' => $this->state,
            'quantity' => $this->quantity,
            'quantity_rest' => $this->quantityRest,
            'figi' => $this->figi,
            'instrument_type' => $this->instrumentType,
            'date' => $this->date,
            'operation_type' => $this->type,
            'trades' => $this->trades,
            'asset_uid' => $this->assetUid,
            'position_uid' => $this->positionUid,
            'ticker' => $this->ticker,
            'class_code' => $this->classCode,
            'instrument_uid' => $this->instrumentUid,
            'description' => $this->description,
            'child_operations' => $this->childOperations,
        ];
    }
}

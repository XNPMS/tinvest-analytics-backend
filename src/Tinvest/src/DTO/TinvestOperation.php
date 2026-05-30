<?php

declare(strict_types=1);

namespace Tinvest\DTO;

final readonly class TinvestOperation
{
    private const NANO_DIVISOR = 1_000_000_000;

    public function __construct(
        public string $operationId,
        public ?string $parentOperationId,
        public string $name,
        public ?string $paymentCurrency,
        public ?int $paymentUnits,
        public ?int $paymentNano,
        public ?string $price,
        public int $state,
        public float $quantity,
        public float $quantityRest,
        public ?string $figi,
        public ?string $instrumentType,
        public string $date,
        public int $type,
        public ?string $trades,
        public ?string $assetUid,
        public ?string $positionUid,
        public ?string $ticker,
        public string $classCode,
        public ?string $instrumentUid,
        public ?string $description,
        public ?string $childOperations,
        public float $commission = 0.0,
    ) {
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

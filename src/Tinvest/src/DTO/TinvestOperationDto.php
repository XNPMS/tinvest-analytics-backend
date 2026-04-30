<?php

declare(strict_types=1);

namespace Tinvest\DTO;

use JsonException;
use Traversable;

final readonly class TinvestOperationDto
{
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
        private ?string $instrumentUid,
        private ?string $description,
        private ?string $childOperations,
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
            'instrument_uid' => $this->instrumentUid,
            'description' => $this->description,
            'child_operations' => $this->childOperations,
        ];
    }
}

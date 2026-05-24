<?php

declare(strict_types=1);

namespace Tinvest\Service;

use Tinvest\DTO\Instrument;
use Tinvest\DTO\TinvestOperation;
use Tinvest\Enum\AssetType;
use Tinvest\Repository\InstrumentRepository;

readonly class InstrumentService
{
    public function __construct(private InstrumentRepository $repository)
    {
    }

    /**
     * Извлекает уникальные инструменты из пачки операций и сохраняет через upsert.
     * Пропускает кассовые операции без figi (пополнения, выводы, налоги).
     *
     * @param TinvestOperation[] $operations
     */
    public function saveFromOperationsBatch(array $operations): void
    {
        $instruments = [];
        foreach ($operations as $operation) {
            $figi = $operation->figi;

            if (!$figi || isset($instruments[$figi])) {
                continue;
            }

            $instrumentType = $operation->instrumentType;
            $currency = $operation->paymentCurrency;

            if (!$instrumentType || !$currency) {
                continue;
            }

            $ticker = $operation->ticker;

            $instruments[$figi] = new Instrument(
                figi: $figi,
                name: $ticker ?: $figi,
                assetType: AssetType::fromTinkoff($instrumentType),
                currency: $currency,
                ticker: $ticker,
            );
        }

        $this->upsertBatch(array_values($instruments));
    }

    /**
     * @param Instrument[] $dtos
     */
    public function upsertBatch(array $dtos): void
    {
        if (!$dtos) {
            return;
        }

        $rows = array_map(static fn(Instrument $dto) => $dto->toArray(), $dtos);
        $this->repository->upsertBatch($rows);
    }
}

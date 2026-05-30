<?php

declare(strict_types=1);

namespace Tinvest\Service;

use Tinvest\DTO\TinvestOperation as TinvestOperationDTO;
use Tinvest\Repository\TinvestOperationRepository;

class TinvestOperationService
{
    private const SAVE_BATCH_SIZE = 500;

    public function __construct(
        private readonly TinvestOperationRepository $repository,
    ) {
    }

    public function saveOperationsBatch(int $accountId, array $batch): void
    {
        if (!$batch) {
            return;
        }

        $rows = array_map(
            static function (TinvestOperationDTO $dto) use ($accountId) {
                return $dto->toArray() + ['account_id' => $accountId];
            },
            $batch
        );

        $this->repository->insertBatch($rows);
    }
}

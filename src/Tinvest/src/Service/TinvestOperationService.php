<?php

declare(strict_types=1);

namespace Tinvest\Service;

use Tinvest\DTO\TinvestOperationDto;
use Tinvest\Entity\TinvestOperation;
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
            static function (TinvestOperationDto $dto) use ($accountId) {
                return $dto->toArray() + ['account_id' => $accountId];
            },
            $batch
        );

        $this->repository->insertBatch($rows);
    }

    public function getOperationsByAccountId(int $accountId): ?TinvestOperation
    {
        return $this->repository->createQueryBuilder()
            ->where($accountId, '=', 'account_id')
            ->first();
    }
}

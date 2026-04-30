<?php

declare(strict_types=1);

namespace Tinvest\Repository;

use System\Repository\AbstractEloquentRepository;
use Tinvest\Entity\TinvestOperation;

readonly class TinvestOperationRepository extends AbstractEloquentRepository
{
    public function getEntityClass(): string
    {
        return TinvestOperation::class;
    }

    public function insertBatch(array $rows): bool
    {
        return $this->createQueryBuilder()->insert($rows);
    }
}

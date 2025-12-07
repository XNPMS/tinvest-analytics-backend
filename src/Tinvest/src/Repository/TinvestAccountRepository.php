<?php

declare(strict_types=1);

namespace Tinvest\Repository;

use System\Repository\AbstractEloquentRepository;
use Tinvest\Entity\TinvestAccount;

readonly class TinvestAccountRepository extends AbstractEloquentRepository
{
    public function getEntityClass(): string
    {
        return TinvestAccount::class;
    }
}

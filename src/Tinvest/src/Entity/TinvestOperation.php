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
}

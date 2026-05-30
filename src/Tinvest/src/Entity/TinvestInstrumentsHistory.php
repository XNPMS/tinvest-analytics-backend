<?php

declare(strict_types=1);

namespace Tinvest\Entity;

use Illuminate\Database\Eloquent\Model;

class TinvestInstrumentsHistory extends Model
{
    public const TABLE = 'tinvest_instruments_history';

    /** @var string */
    protected $table = self::TABLE;
}

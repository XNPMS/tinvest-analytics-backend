<?php

declare(strict_types=1);

namespace Tinvest\Entity;

use Illuminate\Database\Eloquent\Model;

class TinvestSplit extends Model
{
    public const TABLE = 'tinvest_splits';

    /** @var string */
    protected $table = self::TABLE;

    public function getTicker(): string
    {
        return (string)$this->getAttributeFromArray('ticker');
    }

    public function getSplitDate(): string
    {
        return (string)$this->getAttributeFromArray('split_date');
    }

    /** Коэффициент сплита: 10 означает 1 акция → 10 акций */
    public function getRatio(): int
    {
        return (int)$this->getAttributeFromArray('ratio');
    }
}

<?php

declare(strict_types=1);

namespace Tinvest\Entity;

use Illuminate\Database\Eloquent\Model;
use JsonException;
use Tinvest\Collection\PositionsCollection;

class PortfolioSnapshot extends Model
{
    public const TABLE = 'portfolio_snapshots';

    /** @var string */
    protected $table = self::TABLE;

    public const UPDATED_AT = null;

    public function getId(): int
    {
        return (int)$this->getAttributeFromArray('id');
    }

    public function getAccountId(): int
    {
        return (int)$this->getAttributeFromArray('account_id');
    }

    public function getSnapshotDate(): string
    {
        return (string)$this->getAttributeFromArray('snapshot_date');
    }

    public function getTotalValueRub(): float
    {
        return (float)$this->getAttributeFromArray('total_value_rub');
    }

    public function getCashFlowRub(): float
    {
        return (float)$this->getAttributeFromArray('cash_flow_rub');
    }

    public function getExpectedYieldRub(): float
    {
        return (float)$this->getAttributeFromArray('expected_yield_rub');
    }

    public function getTwrFactor(): float
    {
        return (float)$this->getAttributeFromArray('twr_factor');
    }

    public function getCumulativeTwr(): float
    {
        return (float)$this->getAttributeFromArray('cumulative_twr');
    }

    public function getPositions(): PositionsCollection
    {
        $json = (string)$this->getAttributeFromArray('positions_json') ?: null;
        if ($json === null) {
            return new PositionsCollection();
        }

        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            $data = [];
        }

        return PositionsCollection::make($data)->map(static fn(array $item) => PortfolioPosition::fromArray($item));
    }
}

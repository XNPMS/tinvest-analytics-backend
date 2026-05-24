<?php

declare(strict_types=1);

namespace Tinvest\Event;

use Laminas\EventManager\Event;

class AccountsFetchedEvent extends Event
{
    /**
     * @inheritDoc
     */
    public function __construct($params = [], $target = null)
    {
        parent::__construct(static::class, $target, $params);
    }
}

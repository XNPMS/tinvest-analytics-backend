<?php

declare(strict_types=1);

return [
    'listeners' => [
        [
            'event' => \Tinvest\Event\AccountsFetchedEvent::class,
            'listener' => \Tinvest\EventListener\SaveAccountsListener::class,
            /** @uses SaveAccountsListener::onAccountsFetched() */
            'method' => 'onAccountsFetched',
        ],
    ]
];

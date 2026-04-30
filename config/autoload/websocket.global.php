<?php

declare(strict_types=1);

return [
    'websocket' => [
        'port' => (int)getenv('WS_PORT') ?: 8081,
    ],
];
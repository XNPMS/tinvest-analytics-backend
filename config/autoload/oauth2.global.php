<?php

declare(strict_types=1);

return [
    'oauth2' => [
        'access_token_ttl' => (int)getenv('ACCESS_TTL') ?: 900,
        'refresh_token_ttl' => (int)getenv('REFRESH_TTL') ?: 2592000,
        'jwt' => [
            'private_key_path' => (string)getenv('JWT_PRIVATE_KEY'),
            'public_key_path' => (string)getenv('JWT_PUBLIC_KEY'),
            'issuer' => (string)getenv('JWT_ISS'),
            'audience' => (string)getenv('JWT_AUD'),
        ],
        'cookie' => [
            'secure' => true,
            'http_only' => true,
            'path' => (string)getenv('COOKIE_PATH'),
        ],
    ],
];

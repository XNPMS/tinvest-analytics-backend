<?php

declare(strict_types=1);

return [
    'oauth2' => [
        'access_token_ttl' => (int)getenv('ACCESS_TTL') ?: 900,
        'refresh_token_ttl' => (int)getenv('REFRESH_TTL') ?: 2592000,
        'jwt' => [
            'private_key_path' => getenv('JWT_PRIVATE_KEY'),
            'public_key_path' => getenv('JWT_PUBLIC_KEY'),
            'issuer' => getenv('JWT_ISS'),
            'audience' => getenv('JWT_AUD'),
        ],
        'cookie' => [
            'name' => getenv('REFRESH_COOKIE_NAME'),
            'path' => getenv('REFRESH_COOKIE_PATH'),
            'secure' => true,
            'httpOnly' => true,
            'sameSite' => getenv('REFRESH_COOKIE_SAMESITE'),
        ],
    ],
];

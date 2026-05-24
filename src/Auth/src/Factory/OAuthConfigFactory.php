<?php

declare(strict_types=1);

namespace Auth\Factory;

use Auth\Config\CookieConfig;
use Auth\Config\JwtConfig;
use Auth\Config\OAuthConfig;
use Psr\Container\ContainerInterface;

class OAuthConfigFactory
{
    public function __invoke(ContainerInterface $container): OAuthConfig
    {
        $config = $container->get('config')['oauth2'];

        return new OAuthConfig(
            $config['access_token_ttl'],
            $config['refresh_token_ttl'],
            new JwtConfig(
                $config['jwt']['private_key'],
                $config['jwt']['public_key'],
                $config['jwt']['issuer'],
                $config['jwt']['audience']
            ),
            new CookieConfig(
                $config['cookie']['path'],
                $config['cookie']['secure'],
                $config['cookie']['http_only'],
            )
        );
    }
}

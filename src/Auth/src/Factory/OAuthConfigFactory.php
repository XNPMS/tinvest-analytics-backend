<?php

declare(strict_types=1);

namespace Auth\Factory;

use Auth\Config\OAuthConfig;
use Auth\Config\JWTConfig;
use Auth\Config\CookieConfig;
use Psr\Container\ContainerInterface;

class OAuthConfigFactory
{
    public function __invoke(ContainerInterface $container): OAuthConfig
    {
        $config = $container->get('config')['oauth2'];

        return new OAuthConfig(
            $config['access_token_ttl'],
            $config['refresh_token_ttl'],
            new JWTConfig(
                $config['jwt']['private_key_path'],
                $config['jwt']['public_key_path'],
                $config['jwt']['issuer'],
                $config['jwt']['audience']
            ),
            new CookieConfig(
                $config['cookie']['name'],
                $config['cookie']['path'],
                $config['cookie']['secure'],
                $config['cookie']['httpOnly'],
                $config['cookie']['sameSite']
            )
        );
    }
}
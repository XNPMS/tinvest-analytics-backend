<?php

namespace Auth\Factory;

use Auth\Config\CookieConfig;
use Psr\Container\ContainerInterface;

class CookieConfigFactory
{
    public function __invoke(ContainerInterface $container): CookieConfig
    {
        $config = $container->get('config')['oauth2']['cookie'];

        return new CookieConfig(
            $config['path'],
            $config['secure'],
            $config['httpOnly'],
            $config['sameSite']
        );
    }
}

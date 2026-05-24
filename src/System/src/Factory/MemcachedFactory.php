<?php

declare(strict_types=1);

namespace System\Factory;

use Laminas\Cache\Psr\SimpleCache\SimpleCacheDecorator;
use Laminas\Cache\Storage\Adapter\Memcached;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;
use Psr\SimpleCache\CacheInterface;

class MemcachedFactory implements FactoryInterface
{
    /**
     * @inheritDoc
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): CacheInterface
    {
        return new SimpleCacheDecorator($container->get(Memcached::class));
    }
}

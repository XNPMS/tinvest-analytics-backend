<?php

declare(strict_types=1);

namespace System;

use Laminas\Cache\Psr\SimpleCache\SimpleCacheDecorator;
use Laminas\Cache\Service\StorageCacheFactory;
use Laminas\Cache\Storage\Adapter\Memcached;
use Laminas\ServiceManager\AbstractFactory\ReflectionBasedAbstractFactory;
use Psr\SimpleCache\CacheInterface;
use System\Factory\InputFilterMessageResolverFactory;
use System\Factory\MemcachedFactory;
use System\Queue\Command\QueueWorkerCommand;
use System\Queue\Config\RabbitMQConfig;
use System\Queue\Factory\QueueWorkerCommandFactory;
use System\Queue\Factory\RabbitMQConfigFactory;
use System\Service\InputFilterMessageResolver;

class ConfigProvider
{
    /**
     * Returns the configuration array
     *
     * To add a bit of a structure, each section is defined in a separate
     * method which returns an array with its configuration.
     */
    public function __invoke(): array
    {
        return [
            'dependencies' => $this->getDependencies(),
            'laminas-cli' => $this->getCliConfig(),
        ];
    }

    /**
     * Returns the container dependencies
     */
    public function getDependencies(): array
    {
        return [
            'abstract_factories' => [ReflectionBasedAbstractFactory::class],
            'aliases' => [
                CacheInterface::class => SimpleCacheDecorator::class,
            ],
            'invokables' => [],
            'factories'  => [
                InputFilterMessageResolver::class => InputFilterMessageResolverFactory::class,
                Memcached::class => StorageCacheFactory::class,
                SimpleCacheDecorator::class => MemcachedFactory::class,
                RabbitMQConfig::class => RabbitMQConfigFactory::class,
                QueueWorkerCommand::class => QueueWorkerCommandFactory::class,
            ],
        ];
    }

    public function getCliConfig(): array
    {
        return [
            'commands' => [
                QueueWorkerCommand::COMMAND_NAME => QueueWorkerCommand::class,
            ],
        ];
    }
}

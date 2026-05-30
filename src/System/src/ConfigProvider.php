<?php

declare(strict_types=1);

namespace System;

use Laminas\Cache\Psr\SimpleCache\SimpleCacheDecorator;
use Laminas\Cache\Service\StorageCacheFactory;
use Laminas\Cache\Storage\Adapter\Memcached;
use Laminas\EventManager\EventManager;
use Laminas\ServiceManager\AbstractFactory\ReflectionBasedAbstractFactory;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;
use System\Delegator\EventManagerDelegator;
use System\Factory\InputFilterMessageResolverFactory;
use System\Factory\LoggerServiceFactory;
use System\Factory\MemcachedFactory;
use System\Http\Strategy\JsonResponseStrategy;
use System\Http\Strategy\ResponseStrategyInterface;
use System\Queue\Command\QueueWorkerCommand;
use System\Queue\Command\WebSocketServerCommand;
use System\Queue\Config\RabbitMQConfig;
use System\Queue\Config\WebSocketConfig;
use System\Queue\Factory\QueueWorkerCommandFactory;
use System\Queue\Factory\RabbitMQConfigFactory;
use System\Queue\Factory\WebSocketConfigFactory;
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
            'aliases' => $this->getAliases(),
            'factories' => $this->getFactories(),
            'invokables' => [],
            'delegators' => $this->getDelegators(),
        ];
    }

    public function getCliConfig(): array
    {
        return [
            'commands' => [
                QueueWorkerCommand::COMMAND_NAME => QueueWorkerCommand::class,
                WebSocketServerCommand::COMMAND_NAME => WebSocketServerCommand::class,
            ],
        ];
    }

    private function getAliases(): array
    {
        return [
            CacheInterface::class => SimpleCacheDecorator::class,
            LoggerInterface::class => Logger::class,
            ResponseStrategyInterface::class => JsonResponseStrategy::class,
        ];
    }

    private function getFactories(): array
    {
        return [
            InputFilterMessageResolver::class => InputFilterMessageResolverFactory::class,
            Memcached::class => StorageCacheFactory::class,
            SimpleCacheDecorator::class => MemcachedFactory::class,
            RabbitMQConfig::class => RabbitMQConfigFactory::class,
            WebSocketConfig::class => WebSocketConfigFactory::class,
            QueueWorkerCommand::class => QueueWorkerCommandFactory::class,
            Logger::class => LoggerServiceFactory::class,
        ];
    }

    private function getDelegators(): array
    {
        return [
            EventManager::class => [EventManagerDelegator::class],
        ];
    }
}

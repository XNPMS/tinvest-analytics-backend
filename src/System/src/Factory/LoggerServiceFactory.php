<?php

declare(strict_types=1);

namespace System\Factory;

use Monolog\Handler\PsrHandler;
use Monolog\Logger;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use ReflectionException;

class LoggerServiceFactory
{
    /**
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws ContainerExceptionInterface
     */
    public function __invoke(ContainerInterface $container): LoggerInterface
    {
        $config = $container->get('config')['log']['channels'][LoggerInterface::class];

        $logger = new Logger($config['name']);
        $reflection = new ReflectionClass($config['class']);

        $handler = $reflection->newInstanceArgs($config['constructor']);

        if (!empty($config['formatter']['class'])) {
            $formatterClass = $config['formatter']['class'];
            $formatterArgs = $config['formatter']['constructor'] ?? [];

            $formatter = new $formatterClass(...$formatterArgs);
            $handler?->setFormatter($formatter);
        }

        $logger->pushHandler($handler ?? new PsrHandler($logger));

        return $logger;
    }
}

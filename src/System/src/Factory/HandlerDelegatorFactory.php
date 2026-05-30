<?php

declare(strict_types=1);

namespace System\Factory;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Http\Server\RequestHandlerInterface;
use System\Handler\AbstractHandler;
use System\Http\ResponseFactory;

use function assert;

class HandlerDelegatorFactory
{
    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function __invoke(ContainerInterface $container, string $name, callable $callback): RequestHandlerInterface
    {
        $handler = $callback();
        assert($handler instanceof AbstractHandler);

        return $handler->setResponseFactory($container->get(ResponseFactory::class));
    }
}

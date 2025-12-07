<?php

declare(strict_types=1);

namespace System\Delegator;

use Laminas\EventManager\EventManager;
use Laminas\EventManager\LazyListenerAggregate;
use Psr\Container\ContainerInterface;

class EventManagerDelegator
{
    public function __invoke(ContainerInterface $container, string $name, callable $callback): EventManager
    {
        // получаем уже созданный EventManager
        $events = $callback();
        $listeners = $container->get('config')['listeners'] ?? [];

        foreach ($listeners as $listener) {
            // оборачиваем в массив, как ожидает LazyListenerAggregate
            $aggregate = new LazyListenerAggregate([$listener], $container);
            $aggregate->attach($events);
        }

        return $events;
    }
}

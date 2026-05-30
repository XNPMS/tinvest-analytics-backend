<?php

declare(strict_types=1);

namespace System\Factory;

use Laminas\InputFilter\InputFilterInterface;
use Laminas\InputFilter\InputFilterPluginManager;
use Laminas\InputFilter\InputInterface;
use Laminas\ServiceManager\Factory\AbstractFactoryInterface;
use Psr\Container\ContainerInterface;

final class InputFilterAbstractFactory implements AbstractFactoryInterface
{
    /**
     * @inheritDoc
     */
    public function canCreate(ContainerInterface $container, $requestedName): bool
    {
        if (!class_exists($requestedName)) {
            return false;
        }

        return is_subclass_of($requestedName, InputFilterInterface::class);
    }

    /**
     * @inheritDoc
     */
    public function __invoke(
        ContainerInterface $container,
        $requestedName,
        ?array $options = null
    ): InputFilterInterface|InputInterface {
        return $container
            ->get(InputFilterPluginManager::class)
            ->get($requestedName, $options ?? []);
    }
}

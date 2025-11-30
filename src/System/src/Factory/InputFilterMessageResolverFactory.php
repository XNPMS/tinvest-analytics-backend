<?php

declare(strict_types=1);

namespace System\Factory;

use Psr\Container\ContainerInterface;
use System\Service\InputFilterMessageResolver;

class InputFilterMessageResolverFactory
{
    public function __invoke(ContainerInterface $container): InputFilterMessageResolver
    {
        $messages = $container->get('config')['validators'];

        return new InputFilterMessageResolver($messages);
    }
}

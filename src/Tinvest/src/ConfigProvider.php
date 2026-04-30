<?php

declare(strict_types=1);

namespace Tinvest;

use System\Factory\InputFilterAbstractFactory;
use Tinvest\InputFilter\TinvestAccountIdsInputFilter;
use Tinvest\InputFilter\TinvestTokenInputFilter;

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
        ];
    }

    /**
     * Returns the container dependencies
     */
    public function getDependencies(): array
    {
        return [
            'factories' => $this->getFactories(),
        ];
    }

    private function getFactories(): array
    {
        return [
            TinvestTokenInputFilter::class => InputFilterAbstractFactory::class,
            TinvestAccountIdsInputFilter::class => InputFilterAbstractFactory::class,
        ];
    }
}

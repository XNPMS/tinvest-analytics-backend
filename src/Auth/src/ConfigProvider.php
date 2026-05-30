<?php

declare(strict_types=1);

namespace Auth;

use Auth\Config\CookieConfig;
use Auth\Config\OAuthConfig;
use Auth\Factory\CookieConfigFactory;
use Auth\Factory\OAuthConfigFactory;
use Auth\InputFilter\LoginUserInputFilter;
use Auth\InputFilter\RegisterUserInputFilter;
use System\Factory\InputFilterAbstractFactory;

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
            OAuthConfig::class => OAuthConfigFactory::class,
            CookieConfig::class => CookieConfigFactory::class,
            RegisterUserInputFilter::class => InputFilterAbstractFactory::class,
            LoginUserInputFilter::class => InputFilterAbstractFactory::class,
        ];
    }
}

<?php

declare(strict_types=1);

namespace Tinvest;

use System\Factory\HandlerDelegatorFactory;
use System\Factory\InputFilterAbstractFactory;
use Tinvest\Factory\BrokerTokenServiceFactory;
use Tinvest\Handler\AssetAllocationHandler;
use Tinvest\Handler\DashboardSummaryHandler;
use Tinvest\Handler\InstrumentsPerformanceHandler;
use Tinvest\Handler\OnboardingAccountsHandler;
use Tinvest\Handler\OnboardingTokenHandler;
use Tinvest\Handler\PortfolioHistoryHandler;
use Tinvest\Handler\RevokeTokenHandler;
use Tinvest\InputFilter\PortfolioHistoryInputFilter;
use Tinvest\InputFilter\TinvestAccountIdsInputFilter;
use Tinvest\InputFilter\TinvestTokenInputFilter;
use Tinvest\Service\BrokerTokenService;

class ConfigProvider
{
    public function __invoke(): array
    {
        return [
            'dependencies' => $this->getDependencies(),
        ];
    }

    public function getDependencies(): array
    {
        return [
            'factories' => array_merge($this->getFactories(), $this->getFactoriesInputFilters()),
            'delegators' => $this->getDelegators(),
        ];
    }

    private function getFactories(): array
    {
        return [
            BrokerTokenService::class => BrokerTokenServiceFactory::class,
        ];
    }

    private function getFactoriesInputFilters(): array
    {
        return [
            TinvestTokenInputFilter::class => InputFilterAbstractFactory::class,
            TinvestAccountIdsInputFilter::class => InputFilterAbstractFactory::class,
            PortfolioHistoryInputFilter::class => InputFilterAbstractFactory::class,
        ];
    }

    private function getDelegators(): array
    {
        return [
            AssetAllocationHandler::class => [HandlerDelegatorFactory::class],
            DashboardSummaryHandler::class => [HandlerDelegatorFactory::class],
            OnboardingAccountsHandler::class => [HandlerDelegatorFactory::class],
            OnboardingTokenHandler::class => [HandlerDelegatorFactory::class],
            InstrumentsPerformanceHandler::class => [HandlerDelegatorFactory::class],
            PortfolioHistoryHandler::class => [HandlerDelegatorFactory::class],
            RevokeTokenHandler::class => [HandlerDelegatorFactory::class],
        ];
    }
}

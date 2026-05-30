<?php

declare(strict_types=1);

use App\Handler\PingHandler;
use Auth\Handler\LoginUserHandler;
use Auth\Handler\LogoutUserHandler;
use Auth\Handler\RegisterUserHandler;
use Auth\Middleware\AuthMiddleware;
use Mezzio\Application;
use Mezzio\MiddlewareFactory;
use Psr\Container\ContainerInterface;
use Tinvest\Handler\AssetAllocationHandler;
use Tinvest\Handler\DashboardSummaryHandler;
use Tinvest\Handler\DividendCalendarHandler;
use Tinvest\Handler\OnboardingAccountsHandler;
use Tinvest\Handler\OnboardingTokenHandler;
use Tinvest\Handler\InstrumentsPerformanceHandler;
use Tinvest\Handler\PortfolioHistoryHandler;
use User\Handler\UserStateHandler;

return static function (Application $app, MiddlewareFactory $factory, ContainerInterface $container): void {
    $app->get('/api/ping', PingHandler::class, 'api.ping');

    $app->post('/api/auth/register', RegisterUserHandler::class, 'api.register');
    $app->post('/api/auth/login', LoginUserHandler::class, 'api.login');
    $app->get('/api/auth/logout', [AuthMiddleware::class, LogoutUserHandler::class], 'api.logout');

    $app->get('/api/v1/user/state', UserStateHandler::class, 'api.user.state');

    $app->post('/api/v1/onboarding/token', OnboardingTokenHandler::class, 'api.onboarding.token');
    $app->post('/api/v1/onboarding/accounts', OnboardingAccountsHandler::class, 'api.onboarding.accounts');

    $app->get('/api/v1/dashboard/summary', DashboardSummaryHandler::class, 'api.dashboard.summary');
    $app->get('/api/v1/dashboard/assets-allocation', AssetAllocationHandler::class, 'api.dashboard.assets-allocation');

    $app->get('/api/v1/portfolio/history', PortfolioHistoryHandler::class, 'api.portfolio.history');
    $app->get('/api/v1/portfolio/instruments', InstrumentsPerformanceHandler::class, 'api.portfolio.instruments');
    $app->get('/api/v1/portfolio/dividends', DividendCalendarHandler::class, 'api.portfolio.dividends');
};

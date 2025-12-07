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
use Tinvest\Handler\TinvestAccountsSelectionHandler;
use Tinvest\Handler\CreateTinvestTokenHandler;

/**
 * laminas-router route configuration
 *
 * @see https://docs.laminas.dev/laminas-router/
 *
 * Setup routes with a single request method:
 *
 * $app->get('/', App\Handler\HomePageHandler::class, 'home');
 * $app->post('/album', App\Handler\AlbumCreateHandler::class, 'album.create');
 * $app->put('/album/:id', App\Handler\AlbumUpdateHandler::class, 'album.put');
 * $app->patch('/album/:id', App\Handler\AlbumUpdateHandler::class, 'album.patch');
 * $app->delete('/album/:id', App\Handler\AlbumDeleteHandler::class, 'album.delete');
 *
 * Or with multiple request methods:
 *
 * $app->route('/contact', App\Handler\ContactHandler::class, ['GET', 'POST', ...], 'contact');
 *
 * Or handling all request methods:
 *
 * $app->route('/contact', App\Handler\ContactHandler::class)->setName('contact');
 *
 * or:
 *
 * $app->route(
 *     '/contact',
 *     App\Handler\ContactHandler::class,
 *     Mezzio\Router\Route::HTTP_METHOD_ANY,
 *     'contact'
 * );
 */

return static function (Application $app, MiddlewareFactory $factory, ContainerInterface $container): void {
    $app->get('/api/ping', PingHandler::class, 'api.ping');

    $app->post('/api/auth/register', RegisterUserHandler::class, 'api.register');
    $app->post('/api/auth/login', LoginUserHandler::class, 'api.login');
    $app->get('/api/auth/logout', [AuthMiddleware::class, LogoutUserHandler::class], 'api.logout');

    $app->post(
        '/api/v1/tinvest/token',
        [AuthMiddleware::class, CreateTinvestTokenHandler::class],
        'api.tinvest.token'
    );
    $app->post(
        '/api/v1/tinvest/accounts/selection',
        [AuthMiddleware::class, TinvestAccountsSelectionHandler::class],
        'api.tinvest.accounts.selection'
    );
};

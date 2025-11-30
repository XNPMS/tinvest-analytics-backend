<?php

declare(strict_types=1);

namespace Auth\Handler;

use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final readonly class LoginUserHandler implements RequestHandlerInterface
{
    public function __construct(

    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return new JsonResponse([]);
    }
}

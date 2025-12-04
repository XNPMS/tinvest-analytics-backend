<?php

declare(strict_types=1);

namespace Auth\Middleware;

use Mezzio\ProblemDetails\Exception\ProblemDetailsExceptionInterface;
use Mezzio\ProblemDetails\ProblemDetailsResponseFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final readonly class JsonOnlyProblemDetailsMiddleware implements MiddlewareInterface
{
    public function __construct(private ProblemDetailsResponseFactory $factory)
    {
    }

    /**
     * @throws \Throwable
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            return $handler->handle($request);
        } catch (\Throwable $e) {
            if ($e instanceof ProblemDetailsExceptionInterface) {
                $request = $request->withHeader('Accept', 'application/json');

                return $this->factory
                    ->createResponseFromThrowable($request, $e)
                    ->withHeader('Content-Type', ProblemDetailsResponseFactory::CONTENT_TYPE_JSON);
            }

            throw $e;
        }
    }
}

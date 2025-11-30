<?php

declare(strict_types=1);

namespace Auth\Middleware;

use Auth\DTO\Identity;
use Auth\Exception\InvalidAccessTokenException;
use Auth\Service\AuthService;
use Auth\Service\CookieBuilder;
use Auth\Service\TokenPairService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use System\Exception\UnauthorizedException;

final readonly class AuthMiddleware implements MiddlewareInterface
{
    public function __construct(
        private AuthService $authService,
        private TokenPairService $tokenPairService,
        private CookieBuilder $cookieBuilder,
    ) {
    }

    /**
     * @throws UnauthorizedException
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $cookies = $request->getCookieParams();

        if ($accessToken = $cookies[CookieBuilder::ACCESS_TOKEN] ?? null) {
            try {
                if ([$token, $request] = $this->validateAccessToken($request, $accessToken)) {
                    if ($this->tokenPairService->shouldRefreshAccessToken($token)) {
                        return $this->handleRefreshTokenFlow($request, $handler, $cookies);
                    }

                    return $handler->handle($request);
                }
            } catch (InvalidAccessTokenException $e) {
                // Access token невалиден, пробуем refresh
            }
        }

        return $this->handleRefreshTokenFlow($request, $handler, $cookies);
    }

    /**
     * @throws UnauthorizedException
     */
    private function handleRefreshTokenFlow(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
        array $cookies,
    ): ResponseInterface {
        if (!$refreshToken = $cookies[CookieBuilder::REFRESH_TOKEN] ?? null) {
            throw UnauthorizedException::create('Authentication is required');
        }

        try {
            $tokenPair = $this->authService->refreshWithRaw($refreshToken);
            $setCookieHeader = $this->cookieBuilder->buildTokenPairCookies($tokenPair);

            if (![$token, $request] = $this->validateAccessToken($request, $tokenPair->accessToken)) {
                return $handler->handle($request);
            }

            $request = $request->withAttribute('token_pair', $tokenPair);

            return $handler
                ->handle($request)
                ->withHeader('Set-Cookie', $setCookieHeader);
        } catch (\Throwable $e) {
            throw UnauthorizedException::create($e->getMessage());
        }
    }

    /**
     * @throws InvalidAccessTokenException
     */
    private function validateAccessToken(ServerRequestInterface $request, string $accessToken): ?array
    {
        if ($token = $this->tokenPairService->validateAccessToken($accessToken)) {
            $claims = $token->claims();
            if ($userEmail = $claims->get('sub')) {
                return [$token, $request->withAttribute(
                    'identity',
                    new Identity($userEmail, $claims)
                )];
            }
        }

        return null;
    }
}

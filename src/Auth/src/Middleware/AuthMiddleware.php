<?php

declare(strict_types=1);

namespace Auth\Middleware;

use Auth\Exception\InvalidAccessTokenException;
use Auth\Exception\InvalidRefreshTokenException;
use Auth\Exception\UserRuntimeException;
use Auth\Service\AuthService;
use Auth\Service\CookieManager;
use Auth\Service\TokenManager;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use System\Exception\NotFoundException;
use System\Exception\UnauthorizedException;
use User\Service\UserService;

final readonly class AuthMiddleware implements MiddlewareInterface
{
    public function __construct(
        private AuthService $authService,
        private UserService $userService,
        private TokenManager $tokenManager,
        private CookieManager $cookieManager,
    ) {
    }

    /**
     * @throws UnauthorizedException
     * @throws NotFoundException
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $cookies = $request->getCookieParams();

        if ($accessToken = $cookies[CookieManager::ACCESS_TOKEN] ?? null) {
            try {
                if ([$token, $request] = $this->validateAccessToken($request, $accessToken)) {
                    if ($this->tokenManager->shouldRefreshAccessToken($token)) {
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
     * @throws NotFoundException
     */
    private function handleRefreshTokenFlow(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
        array $cookies,
    ): ResponseInterface {
        if (!$refreshToken = $cookies[CookieManager::REFRESH_TOKEN] ?? null) {
            throw UnauthorizedException::create('Authentication is required');
        }

        try {
            $tokenPair = $this->authService->refreshWithRaw($refreshToken);
            $setCookieHeader = $this->cookieManager->buildTokenPairCookies($tokenPair);

            if (![$token, $request] = $this->validateAccessToken($request, $tokenPair->accessToken)) {
                return $handler->handle($request);
            }

            $request = $request->withAttribute('token_pair', $tokenPair);

            return $handler
                ->handle($request)
                ->withHeader(CookieManager::SET_COOKIE, $setCookieHeader);
        } catch (InvalidAccessTokenException | InvalidRefreshTokenException $e) {
            throw UnauthorizedException::create('Authentication is required');
        } catch (UserRuntimeException $e) {
            throw NotFoundException::create($e->getMessage());
        }
    }

    /**
     * @throws InvalidAccessTokenException
     */
    private function validateAccessToken(ServerRequestInterface $request, string $accessToken): ?array
    {
        if (
            ($token = $this->tokenManager->validateAccessToken($accessToken))
            && ($userId = $token->claims()->get('sub'))
        ) {
            return [
                $token,
                $request->withAttribute(
                    'user_model',
                    $this->userService->getUserById((int)$userId)
                )
            ];
        }

        return null;
    }
}

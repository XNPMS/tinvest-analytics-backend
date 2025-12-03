<?php

declare(strict_types=1);

namespace Auth\Handler;

use Auth\Service\CookieManager;
use Auth\Service\RefreshTokenService;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use System\Enum\SuccessFailureEnum;

final readonly class LogoutUserHandler extends BaseAuthHandler
{
    public function __construct(
        private CookieManager $cookieManager,
        private RefreshTokenService $refreshTokenService,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if ($refreshToken = $request->getCookieParams()[CookieManager::REFRESH_TOKEN] ?? null) {
            $this->refreshTokenService->invalidateRefreshToken($refreshToken);
        }

        return $this->cookieManager->clearTokenCookies(
            new JsonResponse([
                SuccessFailureEnum::SUCCESS->value => true,
            ])
        );
    }
}

<?php

declare(strict_types=1);

namespace User\Handler;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use System\Handler\AbstractHandler;
use Tinvest\Service\BrokerTokenService;
use User\Entity\User;

final class UserStateHandler extends AbstractHandler
{
    public function __construct(
        private readonly BrokerTokenService $brokerTokenService,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        /** @var User $user */
        $user = $request->getAttribute(User::class);

        return $this->jsonResponse([
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'onboarding_step' => $user->getOnboardingStep()->value,
            'has_broker_token' => $this->brokerTokenService->hasActiveToken($user->getId()),
        ]);
    }
}

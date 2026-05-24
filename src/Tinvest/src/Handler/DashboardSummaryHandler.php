<?php

declare(strict_types=1);

namespace Tinvest\Handler;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use System\Handler\AbstractHandler;
use Tinvest\UseCase\GetDashboardSummaryUseCase;
use User\Entity\User;

final class DashboardSummaryHandler extends AbstractHandler
{
    public function __construct(
        private readonly GetDashboardSummaryUseCase $useCase,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        /** @var User $user */
        $user = $request->getAttribute(User::class);
        $dashboardSummary = $this->useCase->execute($user->getId());

        if ($dashboardSummary === null) {
            return $this->noContent();
        }

        return $this->jsonResponse($dashboardSummary->toArray());
    }
}

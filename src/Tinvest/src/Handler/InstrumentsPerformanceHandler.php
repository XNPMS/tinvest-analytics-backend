<?php

declare(strict_types=1);

namespace Tinvest\Handler;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use System\Handler\AbstractHandler;
use Tinvest\UseCase\GetInstrumentsPerformanceUseCase;
use User\Entity\User;

final class InstrumentsPerformanceHandler extends AbstractHandler
{
    public function __construct(private readonly GetInstrumentsPerformanceUseCase $useCase)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        /** @var User $user */
        $user = $request->getAttribute(User::class);
        $openOnly = filter_var($request->getQueryParams()['open_only'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $result = $this->useCase->execute($user, $openOnly);
        if (empty($result)) {
            return $this->noContent();
        }

        return $this->jsonResponse(['instruments' => $result]);
    }
}

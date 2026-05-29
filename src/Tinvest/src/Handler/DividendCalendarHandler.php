<?php

declare(strict_types=1);

namespace Tinvest\Handler;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use System\Handler\AbstractHandler;
use Tinvest\Model\Api\DividendCalendarApiResponse;
use Tinvest\UseCase\GetDividendCalendarUseCase;
use User\Entity\User;

final class DividendCalendarHandler extends AbstractHandler
{
    public function __construct(
        private readonly GetDividendCalendarUseCase $useCase,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        /** @var User $user */
        $user = $request->getAttribute(User::class);
        $result = $this->useCase->execute($user->getId());

        if ($result === null) {
            return $this->noContent();
        }

        return $this->jsonResponse(DividendCalendarApiResponse::fromDividendCalendar($result)->toApi());
    }
}

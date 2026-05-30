<?php

declare(strict_types=1);

namespace Tinvest\Handler;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use System\Handler\AbstractHandler;
use Tinvest\Model\Api\AssetAllocationApiResponse;
use Tinvest\UseCase\GetAssetAllocationUseCase;
use User\Entity\User;

final class AssetAllocationHandler extends AbstractHandler
{
    public function __construct(
        private readonly GetAssetAllocationUseCase $useCase,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        /** @var User $user */
        $user = $request->getAttribute(User::class);
        $accountIds = array_map('intval', (array)($request->getQueryParams()['account_id'] ?? []));

        $result = $this->useCase->execute($user->getId(), $accountIds);
        if ($result === null) {
            return $this->noContent();
        }

        return $this->jsonResponse(AssetAllocationApiResponse::fromAssetAllocation($result)->toApi());
    }
}

<?php

declare(strict_types=1);

namespace Tinvest\Handler;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use System\Exception\Http\NotFoundException;
use System\Handler\AbstractHandler;
use Tinvest\Exception\BrokerTokenException;
use Tinvest\Service\BrokerTokenService;
use User\Entity\User;

final class RevokeTokenHandler extends AbstractHandler
{
    public function __construct(private readonly BrokerTokenService $service)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        /** @var User $user */
        $user = $request->getAttribute(User::class);

        try {
            $success = $this->service->removeToken($user->getId(), $request->getAttribute('token'));
        } catch (BrokerTokenException $e) {
            return NotFoundException::create($e->getMessage());
        }

        return $this->jsonResponse(['success' => $success]);
    }
}

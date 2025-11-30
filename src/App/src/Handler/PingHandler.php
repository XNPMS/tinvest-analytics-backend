<?php

declare(strict_types=1);

namespace App\Handler;

use Auth\Service\AuthService;
use Auth\Service\TokenPairService;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

use User\Repository\UserRepository;
use function time;

final readonly class PingHandler implements RequestHandlerInterface
{
    public function __construct(
        private UserRepository $userRepository,
        private AuthService $authService,
        private TokenPairService $tokenService,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
//        $user = $this->userRepository->getUserById(1);
//        $token = $this->authService->issueTokenPair($user);

        return new JsonResponse(['ack' => '$token->toArray()']);
    }
}

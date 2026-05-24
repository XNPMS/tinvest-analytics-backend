<?php

declare(strict_types=1);

namespace Auth\Handler;

use Auth\Exception\UserRuntimeException;
use Auth\InputFilter\RegisterUserInputFilter;
use Auth\Service\AuthService;
use Auth\Service\CookieManager;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use System\Exception\Http\BadRequestException;
use System\Exception\Http\ConflictException;
use User\Service\UserService;

final readonly class RegisterUserHandler extends BaseAuthHandler
{
    public function __construct(
        private RegisterUserInputFilter $inputFilter,
        private UserService $userService,
        private AuthService $authService,
        private CookieManager $cookieManager,
    ) {
    }

    /**
     * @throws BadRequestException
     * @throws ConflictException
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $user = $this->userService->createUser(
                $this->validate(
                    $this->inputFilter,
                    $request->getParsedBody()
                )
            );
        } catch (UserRuntimeException $e) {
            throw ConflictException::create($e->getMessage());
        }

        return $this->cookieManager->addTokenPairToResponse(
            $this->authService->issueTokenPair($user),
            new JsonResponse($this->successPayload($user)),
        );
    }
}

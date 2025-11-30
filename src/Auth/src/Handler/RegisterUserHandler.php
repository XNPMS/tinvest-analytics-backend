<?php

declare(strict_types=1);

namespace Auth\Handler;

use Auth\DTO\RegisterUserData;
use Auth\Exception\UserSearchException;
use Auth\InputFilter\RegisterUserInputFilter;
use Auth\Service\AuthService;
use Auth\Service\CookieBuilder;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use System\Enum\SuccessFailureEnum;
use System\Exception\BadRequestException;
use System\Exception\ConflictException;
use User\Service\UserService;

final readonly class RegisterUserHandler implements RequestHandlerInterface
{
    public function __construct(
        private RegisterUserInputFilter $inputFilter,
        private UserService $userService,
        private AuthService $authService,
        private CookieBuilder $cookieBuilder,
    ) {
    }

    /**
     * @throws BadRequestException
     * @throws ConflictException
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->inputFilter->setData($request->getParsedBody());
        if (!$this->inputFilter->isValid()) {
            throw BadRequestException::create(
                detail: 'Invalid data',
                title: SuccessFailureEnum::FAIL->value,
                additional: ['errors' => $this->inputFilter->getMessages()],
            );
        }

        try {
            $userData = RegisterUserData::fromArray($this->inputFilter->getValues());
            $user = $this->userService->createUser($userData);
        } catch (UserSearchException $e) {
            throw ConflictException::create($e->getMessage());
        }

        return $this->cookieBuilder->addToResponse(
            new JsonResponse([
                SuccessFailureEnum::SUCCESS->value => [
                    'email' => $userData->email
                ],
            ]),
            $this->authService->issueTokenPair($user)
        );
    }
}

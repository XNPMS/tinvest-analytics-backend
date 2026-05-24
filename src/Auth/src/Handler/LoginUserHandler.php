<?php

declare(strict_types=1);

namespace Auth\Handler;

use Auth\Exception\AuthenticationFailedException;
use Auth\Exception\UserRuntimeException;
use Auth\InputFilter\LoginUserInputFilter;
use Auth\Service\AuthService;
use Auth\Service\CookieManager;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use System\Exception\Http\BadRequestException;
use System\Exception\Http\NotFoundException;
use System\Exception\Http\UnauthorizedException;

final readonly class LoginUserHandler extends BaseAuthHandler
{
    public function __construct(
        private AuthService $authService,
        private LoginUserInputFilter $inputFilter,
        private CookieManager $cookieManager,
    ) {
    }

    /**
     * @throws NotFoundException
     * @throws UnauthorizedException
     * @throws BadRequestException
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $user = $this->authService->authenticate(
                $this->validate(
                    $this->inputFilter,
                    $request->getParsedBody()
                )
            );
        } catch (UserRuntimeException $e) {
            throw NotFoundException::create($e->getMessage());
        } catch (AuthenticationFailedException $e) {
            throw UnauthorizedException::create($e->getMessage());
        }

        return $this->cookieManager->addTokenPairToResponse(
            $this->authService->issueTokenPair($user),
            new JsonResponse($this->successPayload($user)),
        );
    }
}

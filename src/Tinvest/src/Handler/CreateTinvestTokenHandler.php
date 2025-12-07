<?php

declare(strict_types=1);

namespace Tinvest\Handler;

use Laminas\Diactoros\Response\JsonResponse;
use Laminas\EventManager\EventManager;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use System\Exception\BadRequestException;
use System\Exception\ConflictException;
use Tinvest\Event\AccountsFetchedEvent;
use Tinvest\InputFilter\TinvestTokenInputFilter;
use Tinvest\Service\TinvestApiService;
use User\Entity\User;
use User\Service\UserService;

final readonly class CreateTinvestTokenHandler implements RequestHandlerInterface
{
    public function __construct(
        private TinvestTokenInputFilter $inputFilter,
        private UserService $userService,
        private TinvestApiService $apiService,
        private EventManager $eventManager,
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
            throw BadRequestException::fromInputFilter($this->inputFilter);
        }

        try {
            $user = $this->userService->saveTinvestToken(
                $request->getAttribute(User::class),
                $this->inputFilter->getValue('token')
            );

            $accounts = $this->apiService->getAllAccountsTinvest($user->getTinvestToken());

            $this->eventManager->triggerEvent(
                new AccountsFetchedEvent([
                    'user_id' => $user->getId(),
                    'accounts' => $accounts,
                ])
            );
        } catch (\Exception $e) {
            // в случае когда запрос в t-invest упал
            throw BadRequestException::create('Check the token and retry the request');
        }

        return new JsonResponse(['accounts' => $accounts]);
    }
}

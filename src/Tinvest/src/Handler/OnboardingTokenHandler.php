<?php

declare(strict_types=1);

namespace Tinvest\Handler;

use Exception;
use Laminas\EventManager\EventManager;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use System\Exception\Http\BadRequestException;
use System\Handler\AbstractHandler;
use System\Service\UseInputFilterTrait;
use Tinvest\Event\AccountsFetchedEvent;
use Tinvest\InputFilter\TinvestTokenInputFilter;
use Tinvest\Service\BrokerTokenService;
use Tinvest\Service\TinvestApiService;
use User\Entity\User;

final class OnboardingTokenHandler extends AbstractHandler
{
    use UseInputFilterTrait;

    public function __construct(
        private readonly TinvestTokenInputFilter $inputFilter,
        private readonly BrokerTokenService $brokerTokenService,
        private readonly TinvestApiService $apiService,
        private readonly EventManager $eventManager,
    ) {
    }

    /**
     * @throws BadRequestException
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->validateRequest($request->getParsedBody());

        /** @var User $user */
        $user = $request->getAttribute(User::class);
        $token = $this->inputFilter->getValue('token');

        try {
            $this->brokerTokenService->saveToken($user, $token);
            $accounts = $this->apiService->getAllAccountsTinvest($token);

            $this->eventManager->triggerEvent(
                new AccountsFetchedEvent([
                    'user_id' => $user->getId(),
                    'accounts' => $accounts,
                ])
            );
        } catch (Exception) {
            throw BadRequestException::create('Check the token and retry the request');
        }

        return $this->jsonResponse(['accounts' => $accounts]);
    }
}

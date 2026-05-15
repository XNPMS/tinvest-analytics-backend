<?php

declare(strict_types=1);

namespace Tinvest\Handler;

use Exception;
use Laminas\Diactoros\Response\JsonResponse;
use Laminas\EventManager\EventManager;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use System\Exception\BadRequestException;
use System\Service\UseInputFilterTrait;
use Tinvest\Entity\TinvestAccount;
use Tinvest\Event\AccountsFetchedEvent;
use Tinvest\InputFilter\TinvestTokenInputFilter;
use Tinvest\Repository\TinvestAccountRepository;
use Tinvest\Service\BrokerTokenService;
use Tinvest\Service\TinvestApiService;
use User\Entity\User;
use User\Enum\OnboardingStep;
use User\Service\UserService;

final readonly class OnboardingTokenHandler implements RequestHandlerInterface
{
    use UseInputFilterTrait;

    public function __construct(
        private TinvestTokenInputFilter $inputFilter,
        private UserService $userService,
        private BrokerTokenService $brokerTokenService,
        private TinvestApiService $apiService,
        private EventManager $eventManager,
        private TinvestAccountRepository $accountRepository,
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
            $this->brokerTokenService->saveToken($user->getId(), $token);

            $accounts = $this->apiService->getAllAccountsTinvest($token);

            $this->eventManager->triggerEvent(
                new AccountsFetchedEvent([
                    'user_id' => $user->getId(),
                    'accounts' => $accounts,
                ])
            );
        } catch (Exception $e) {
            throw BadRequestException::create('Check the token and retry the request');
        }

        $this->userService->updateOnboardingStep($user, OnboardingStep::ACCOUNTS_PENDING);

        // Re-fetch from DB to include integer id field
        $accountIds = array_column($accounts, 'account_id');
        $dbAccounts = $this->accountRepository->findByIds($accountIds);

        $result = $dbAccounts->map(fn(TinvestAccount $a) => [
            'id'           => $a->getId(),
            'account_id'   => $a->getAccountId(),
            'name'         => $a->getName(),
            'status'       => (int) $a->getAttribute('status'),
            'type'         => (int) $a->getAttribute('type'),
            'opened_date'  => $a->getOpenedDate(),
            'access_level' => (int) $a->getAttribute('access_level'),
            'created_at'   => (string) $a->getAttribute('created_at'),
            'updated_at'   => (string) $a->getAttribute('updated_at'),
        ])->values()->all();

        return new JsonResponse(['accounts' => $result]);
    }
}

<?php

declare(strict_types=1);

namespace Tinvest\Handler;

use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use System\Enum\SuccessFailureEnum;
use System\Exception\BadRequestException;
use System\Exception\NotFoundException;
use System\Queue\Enum\Workers;
use System\Queue\Producer\QueueManager;
use System\Service\UseInputFilterTrait;
use Tinvest\InputFilter\TinvestAccountIdsInputFilter;
use Tinvest\Message\AccountsMessage;
use Tinvest\Repository\TinvestAccountRepository;
use User\Entity\User;
use User\Enum\OnboardingStep;
use User\Service\UserService;

final readonly class OnboardingAccountsHandler implements RequestHandlerInterface
{
    use UseInputFilterTrait;

    public function __construct(
        private TinvestAccountIdsInputFilter $inputFilter,
        private TinvestAccountRepository $repository,
        private QueueManager $queueManager,
        private UserService $userService,
    ) {
    }

    /**
     * @throws BadRequestException
     * @throws NotFoundException
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->validateRequest($request->getParsedBody());

        /** @var User $user */
        $user = $request->getAttribute(User::class);
        $userId = $user->getId();
        $accountIds = $this->inputFilter->getValue('account_ids');

        $accounts = $this->repository->findByIds($userId, $accountIds, count($accountIds));
        if ($accounts->isEmpty()) {
            throw NotFoundException::create('Accounts not found');
        }

        $jobId = $this->queueManager->send(
            Workers::SYNC_ONBOARDING_ACCOUNTS,
            new AccountsMessage(
                $userId,
                array_unique($accounts->pluck('account_id')->toArray()),
            )
        );

        $this->userService->updateOnboardingStep($user, OnboardingStep::SYNCING);

        return new JsonResponse([
            SuccessFailureEnum::SUCCESS->value => true,
            'job_id' => $jobId,
        ]);
    }
}

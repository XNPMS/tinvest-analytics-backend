<?php

declare(strict_types=1);

namespace Tinvest\Handler;

use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use System\Enum\SuccessFailureEnum;
use System\Exception\BadRequestException;
use System\Queue\Enum\Workers;
use System\Queue\Producer\QueueManager;
use System\Service\UseInputFilter;
use Tinvest\InputFilter\TinvestAccountIdsInputFilter;
use Tinvest\Message\AccountsMessage;
use User\Entity\User;
use User\Enum\OnboardingStep;
use User\Service\UserService;

final readonly class TinvestAccountsSelectionHandler implements RequestHandlerInterface
{
    use UseInputFilter;

    public function __construct(
        private TinvestAccountIdsInputFilter $inputFilter,
        private QueueManager $queueManager,
        private UserService $userService,
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

        $jobId = $this->queueManager->send(
            Workers::SYNC_TINVEST_ACCOUNTS,
            new AccountsMessage(
                $user->getId(),
                $this->inputFilter->getValue('account_ids')
            )
        );

        $this->userService->updateOnboardingStep($user, OnboardingStep::SYNCING);

        return new JsonResponse([
            SuccessFailureEnum::SUCCESS->value => true,
            'job_id' => $jobId,
        ]);
    }
}

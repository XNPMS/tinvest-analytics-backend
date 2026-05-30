<?php

declare(strict_types=1);

namespace Tinvest\Handler;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use System\Enum\SuccessFailureEnum;
use System\Exception\Http\BadRequestException;
use System\Exception\Http\NotFoundException;
use System\Handler\AbstractHandler;
use System\Queue\Enum\Workers;
use System\Queue\Producer\QueueManager;
use System\Service\UseInputFilterTrait;
use Tinvest\InputFilter\TinvestAccountIdsInputFilter;
use Tinvest\Message\AccountsMessage;
use Tinvest\Repository\TinvestAccountRepository;
use User\Entity\User;
use User\Enum\OnboardingStep;

final class OnboardingAccountsHandler extends AbstractHandler
{
    use UseInputFilterTrait;

    public function __construct(
        private readonly TinvestAccountIdsInputFilter $inputFilter,
        private readonly TinvestAccountRepository $repository,
        private readonly QueueManager $queueManager,
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

        // Если синхронизация выполнена, то не даем ее выполнить повторно и не ставим задачу в очередь.
        // В будущем можно прокидывать дополнительный флаг для принудительной синхры
//        if ($user->getOnboardingStep() !== OnboardingStep::READY) {
            $jobId = $this->queueManager->send(
                Workers::SYNC_ONBOARDING_ACCOUNTS,
                new AccountsMessage(
                    $userId,
                    array_unique($accounts->pluck('account_id')->toArray()),
                )
            );
//        }

        return $this->jsonResponse([
            SuccessFailureEnum::SUCCESS->value => true,
            'job_id' => $jobId ?? null,
        ]);
    }
}

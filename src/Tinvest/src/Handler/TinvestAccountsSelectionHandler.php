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
use System\Service\UseInputFilter;
use Tinvest\InputFilter\TinvestAccountIdsInputFilter;
use Tinvest\Message\AccountsMessage;
use Tinvest\UseCase\SyncOperationsUseCase;
use User\Entity\User;

final readonly class TinvestAccountsSelectionHandler implements RequestHandlerInterface
{
    use UseInputFilter;

    public function __construct(
        private TinvestAccountIdsInputFilter $inputFilter,
        private QueueManager $queueManager,
        private SyncOperationsUseCase $syncOperationsUseCase,
    ) {
    }

    /**
     * @throws BadRequestException
     * @throws NotFoundException
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->validateRequest($request->getParsedBody());

//        $this->syncOperationsUseCase->execute(new AccountsMessage(
//            (int)$request->getAttribute(User::class)->getId(),
//            $this->inputFilter->getValue('account_ids')
//        ));

        $jobId = $this->queueManager->send(
            Workers::SYNC_TINVEST_ACCOUNTS,
            new AccountsMessage(
                (int)$request->getAttribute(User::class)->getId(),
                $this->inputFilter->getValue('account_ids')
            )
        );

        return new JsonResponse([
            SuccessFailureEnum::SUCCESS->value => true,
            'job_id' => $jobId,
        ]);
    }
}

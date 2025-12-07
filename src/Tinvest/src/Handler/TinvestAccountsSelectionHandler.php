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
use System\Queue\Enum\QueueName;
use System\Queue\Producer\QueueManager;
use Tinvest\InputFilter\TinvestAccountIdsInputFilter;
use Tinvest\Message\AccountsMessage;
use User\Entity\User;

final readonly class TinvestAccountsSelectionHandler implements RequestHandlerInterface
{
    public function __construct(
        private TinvestAccountIdsInputFilter $inputFilter,
        private QueueManager $queueManager,
    ) {
    }

    /**
     * @throws BadRequestException
     * @throws NotFoundException
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->inputFilter->setData($request->getParsedBody());
        if (!$this->inputFilter->isValid()) {
            throw BadRequestException::fromInputFilter($this->inputFilter);
        }

        $jobId = $this->queueManager->send(
            QueueName::SYNC_TINVEST_ACCOUNTS,
            new AccountsMessage(
                $request->getAttribute(User::class),
                $this->inputFilter->getValue('account_ids')
            )
        );

        return new JsonResponse([
            SuccessFailureEnum::SUCCESS->value => true,
            'job_id' => $jobId,
        ]);
    }
}

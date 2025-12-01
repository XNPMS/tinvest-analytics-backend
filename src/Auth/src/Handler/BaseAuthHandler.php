<?php

declare(strict_types=1);

namespace Auth\Handler;

use Auth\DTO\UserCredentials;
use Laminas\InputFilter\InputFilterInterface;
use Psr\Http\Server\RequestHandlerInterface;
use System\Enum\SuccessFailureEnum;
use System\Exception\BadRequestException;
use User\Entity\User;

abstract readonly class BaseAuthHandler implements RequestHandlerInterface
{
    /**
     * @throws BadRequestException
     */
    protected function validate(InputFilterInterface $filter, array|null $body): UserCredentials
    {
        $filter->setData($body ?? []);

        if (!$filter->isValid()) {
            throw BadRequestException::create(
                detail: 'Invalid data provided',
                title: SuccessFailureEnum::FAIL->value,
                additional: ['errors' => $filter->getMessages()],
            );
        }

        return UserCredentials::fromArray($filter->getValues());
    }

    protected function successPayload(User $user): array
    {
        return [
            SuccessFailureEnum::SUCCESS->value => [
                'email' => $user->getEmail(),
                'user_id' => $user->getId()
            ],
        ];
    }
}

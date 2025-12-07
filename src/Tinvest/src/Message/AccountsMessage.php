<?php

declare(strict_types=1);

namespace Tinvest\Message;

use User\Entity\User;

final readonly class AccountsMessage implements MessageInterface
{
    public function __construct(
        public array|User $user,
        public array $accountIds,
    ) {
    }

    public static function fromArray(array $data): AccountsMessage
    {
        return new self(
            $data['user'],
            $data['account_ids'],
        );
    }

    public function toArray(): array
    {
        return [
            'user' => $this->user,
            'account_ids' => $this->accountIds,
        ];
    }
}

<?php

declare(strict_types=1);

namespace Tinvest\Message;

final readonly class AccountsMessage implements MessageInterface
{
    public function __construct(
        public string $userId,
        public array $accountIds,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            $data['user_id'],
            $data['account_ids'],
        );
    }

    public function toArray(): array
    {
        return [
            'user_id' => $this->userId,
            'account_ids' => $this->accountIds,
        ];
    }
}

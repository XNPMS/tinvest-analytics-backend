<?php

declare(strict_types=1);

namespace System\Exception;

use InvalidArgumentException;

final class InvalidArgumentRepositoryException extends InvalidArgumentException
{
    public static function invalidUserId(string $userId): self
    {
        return new self(sprintf('Invalid userId: %d', $userId));
    }

    public static function invalidBrokerAccountId(int $brokerAccountId): self
    {
        return new self(sprintf('Invalid broker account id: %d', $brokerAccountId));
    }
}

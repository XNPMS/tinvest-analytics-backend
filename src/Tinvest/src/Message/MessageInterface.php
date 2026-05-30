<?php

declare(strict_types=1);

namespace Tinvest\Message;

interface MessageInterface
{
    public static function fromArray(array $data): self;

    public function toArray(): array;
}

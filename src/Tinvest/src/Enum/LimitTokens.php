<?php

declare(strict_types=1);

namespace Tinvest\Enum;

/**
 * @link https://developer.tbank.ru/invest/intro/intro/limits/
 */
enum LimitTokens: int
{
    case MAX_TOKENS_SERVICE_OPERATIONS = 200;
}

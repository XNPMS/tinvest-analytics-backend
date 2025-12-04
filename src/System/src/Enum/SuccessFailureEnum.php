<?php

declare(strict_types=1);

namespace System\Enum;

enum SuccessFailureEnum: string
{
    case SUCCESS = 'success';
    case FAIL = 'fail';

    /**
     * @return non-empty-string[]
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

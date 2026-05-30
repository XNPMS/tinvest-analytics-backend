<?php

declare(strict_types=1);

namespace System\Service;

use Laminas\InputFilter\InputFilterInterface;
use System\Exception\Http\BadRequestException;

trait UseInputFilterTrait
{
    /**
     * Валидирует данные запроса через InputFilter.
     * Возвращает валидированные значения
     *
     * @throws BadRequestException
     */
    protected function validateRequest(array|null $body): InputFilterInterface
    {
        $this->inputFilter->setData($body);

        if (!$this->inputFilter->isValid()) {
            throw BadRequestException::fromInputFilter($this->inputFilter);
        }

        return $this->inputFilter;
    }
}

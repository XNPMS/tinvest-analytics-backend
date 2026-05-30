<?php

declare(strict_types=1);

namespace System\Service;

use Laminas\InputFilter\InputFilter;
use Laminas\InputFilter\InputFilterInterface;
use Laminas\InputFilter\InputInterface;
use Laminas\Validator\StringLength;
use Laminas\Validator\ValidatorInterface;

/**
 * Для кастомных ошибок валидации из конфига
 * config/autoload/input_filter_messages.global.php
 *
 * @see InputFilter
 */
final readonly class InputFilterMessageResolver
{
    public function __construct(private array $messages)
    {
    }

    /**
     * Получить текст ошибки по валидатору и коду
     */
    private function resolveMessageTemplate(string $validatorClass, string $code): ?string
    {
        if (!isset($this->messages[$validatorClass])) {
            return null;
        }

        return $this->messages[$validatorClass][$code] ?? null;
    }

    /**
     * Получить сообщения для отдельного Input
     */
    public function resolveInput(InputInterface $input): array
    {
        if (!$errors = $input->getMessages()) {
            return [];
        }
        $value = $input->getRawValue();
        $resolved = [];

        foreach ($errors as $code => $defaultMessage) {
            // Ищем валидатор, который сгенерировал ошибку
            if (!$validatorClass = $this->findValidatorByErrorCode($input, $value, $code)) {
                $resolved[] = $defaultMessage;
                continue;
            }

            // Получаем шаблон из конфига
            if (!$template = $this->resolveMessageTemplate($validatorClass::class, $code)) {
                $resolved[] = $defaultMessage;
                continue;
            }
            // Собираем финальный текст (подставить аргументы валидатора)
            $resolved[] = $this->buildMessage($template, $validatorClass, $input, $code);
        }

        return $resolved;
    }

    /**
     * Найти валидатор, который дал ошибку
     */
    private function findValidatorByErrorCode(
        InputInterface $input,
        mixed $value,
        string $errorCode
    ): ?ValidatorInterface {
        foreach ($input->getValidatorChain()->getValidators() as $item) {
            /** @var ValidatorInterface $validator */
            $validator = $item['instance'];

            if (!$validator->isValid($value)) {
                $messages = $validator->getMessages();

                if (array_key_exists($errorCode, $messages)) {
                    return $validator;
                }
            }
        }

        return null;
    }

    /**
     * Подстановка параметров (%s, %d и т.д.)
     */
    private function buildMessage(
        string $template,
        ValidatorInterface $validator,
        InputInterface $input,
        string $code
    ): string {
        if (!method_exists($validator, 'getOptions')) {
            return $template;
        }

        $params = [$input->getName()];
        $options = $validator->getOptions();
        // Для разных типов валидаторов подставляем разные параметры
        switch ($validator::class) {
            case StringLength::class:
                switch (true) {
                    case $code === StringLength::TOO_SHORT:
                        if (isset($options['min']) && is_scalar($options['min'])) {
                            $params[] = $options['min'];
                        }

                        break;
                    case $code === StringLength::TOO_LONG:
                        if (isset($options['max']) && is_scalar($options['max'])) {
                            $params[] = $options['max'];
                        }

                        break;
                }
                break;
            case \Laminas\Validator\Identical::class:
                if ($options['token'] = $validator->getToken()) {
                    $params[] = $options['token'];
                }

                break;
            default:
                // Для остальных валидаторов подставляем min/max/token если есть
                foreach (['min', 'max', 'token'] as $key) {
                    if (isset($options[$key]) && is_scalar($options[$key])) {
                        $params[] = $options[$key];
                    }
                }
        }

        return vsprintf($template, $params) ?: $template;
    }

    /**
     * Формирует массив всех ошибок по InputFilter
     */
    public function resolveInputFilter(InputFilterInterface $inputFilter): array
    {
        return array_map(
            function (InputInterface $input) {
                return $this->resolveInput($input);
            },
            $inputFilter->getInvalidInput()
        );
    }
}

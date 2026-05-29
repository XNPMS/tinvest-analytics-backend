<?php

declare(strict_types=1);

namespace Tinvest\Exception;

/**
 * Исключение gRPC-вызовов к Tinkoff API.
 *
 * Хранит оригинальный числовой код gRPC-статуса, чтобы вызывающий код мог
 * различать виды ошибок без парсинга строки сообщения.
 */
class TinvestGrpcException extends \Exception
{
    // gRPC status code 5: запрошенный ресурс не найден (инструмент не существует в API).
    // Повторные попытки для этого кода бессмысленны — инструмент не появится.
    public const GRPC_NOT_FOUND = 5;

    public function __construct(string $message, private readonly int $grpcCode = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }

    // Возвращает числовой gRPC-код из ответа сервера (0 = OK, 5 = NOT_FOUND, 8 = RESOURCE_EXHAUSTED и т.д.).
    public function getGrpcCode(): int
    {
        return $this->grpcCode;
    }
}
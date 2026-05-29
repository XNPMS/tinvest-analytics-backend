<?php

declare(strict_types=1);

namespace User\Enum;

enum OnboardingStep: string
{
    /**
     * Начальное состояние после регистрации. Пользователь ещё не добавил токен Tinkoff API
     */
    case TOKEN_MISSING = 'token_missing';
    /**
     * Токен добавлен и счета получены из Tinkoff, но пользователь ещё не выбрал какие из них синхронизировать
     */
    case ACCOUNTS_PENDING = 'accounts_pending';
    /**
     * Пользователь выбрал счета, задача на синхронизацию операций отправлена в очередь и выполняется
     */
    case SYNCING = 'syncing';
    /**
     * Синхронизация завершена, все данные загружены, платформа готова к работе
     */
    case READY = 'ready';
    /**
     * Пост-синхронизационный пайплайн завершился с ошибкой
     */
    case SYNC_FAILED = 'sync_failed';
}

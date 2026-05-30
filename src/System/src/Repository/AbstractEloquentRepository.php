<?php

declare(strict_types=1);

namespace System\Repository;

use Illuminate\Database\Eloquent\Builder as QueryBuilder;

abstract readonly class AbstractEloquentRepository
{
    protected const DATETIME_FORMAT = 'Y-m-d H:i:s';
    protected const MAX_LIMIT = 500;

    protected string $model;

    /**
     * Создает новый экземпляр запроса
     */
    public function createQueryBuilder(): QueryBuilder
    {
        return call_user_func([$this->getEntityClass(), 'query']);
    }

    /**
     * Возвращает FQCN класса модели для создания запроса из репозитория
     */
    abstract public function getEntityClass(): string;
}

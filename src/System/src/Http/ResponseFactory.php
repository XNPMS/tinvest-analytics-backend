<?php

declare(strict_types=1);

namespace System\Http;

use Fig\Http\Message\StatusCodeInterface;
use Psr\Http\Message\ResponseInterface;
use System\Http\Strategy\ResponseStrategyInterface;

readonly class ResponseFactory
{
    public function __construct(private ResponseStrategyInterface $strategy)
    {
    }

    public function json(array $data, int $status = StatusCodeInterface::STATUS_OK, Link ...$links): ResponseInterface
    {
        return $this->strategy->resource($data, $status, $links);
    }

    public function created(array $data = [], Link ...$links): ResponseInterface
    {
        return $this->strategy->resource($data, StatusCodeInterface::STATUS_CREATED, $links);
    }

    public function collection(array $items, array $meta = [], Link ...$links): ResponseInterface
    {
        return $this->strategy->collection($items, $meta, $links);
    }

    public function noContent(): ResponseInterface
    {
        return $this->strategy->empty(StatusCodeInterface::STATUS_NO_CONTENT);
    }
}

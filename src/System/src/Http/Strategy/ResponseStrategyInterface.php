<?php

declare(strict_types=1);

namespace System\Http\Strategy;

use Psr\Http\Message\ResponseInterface;
use System\Http\Link;

interface ResponseStrategyInterface
{
    /**
     * @param Link[] $links
     */
    public function resource(array $data, int $status, array $links): ResponseInterface;

    /**
     * @param Link[] $links
     */
    public function collection(array $items, array $meta, array $links): ResponseInterface;

    public function empty(int $status): ResponseInterface;
}

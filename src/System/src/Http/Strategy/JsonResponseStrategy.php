<?php

declare(strict_types=1);

namespace System\Http\Strategy;

use Laminas\Diactoros\Response\EmptyResponse;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use System\Http\Link;

class JsonResponseStrategy implements ResponseStrategyInterface
{
    /**
     * @param Link[] $links
     */
    public function resource(array $data, int $status, array $links): ResponseInterface
    {
        return new JsonResponse($data, $status);
    }

    /**
     * @param Link[] $links
     */
    public function collection(array $items, array $meta, array $links): ResponseInterface
    {
        $body = ['data' => $items];

        if ($meta) {
            $body['meta'] = $meta;
        }

        return new JsonResponse($body);
    }

    public function empty(int $status): ResponseInterface
    {
        return new EmptyResponse($status);
    }
}

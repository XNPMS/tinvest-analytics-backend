<?php

declare(strict_types=1);

namespace System\Http\Strategy;

use Laminas\Diactoros\Response\EmptyResponse;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use System\Http\Link;

class HalResponseStrategy implements ResponseStrategyInterface
{
    private const CONTENT_TYPE = 'application/hal+json';

    /** @param Link[] $links */
    public function resource(array $data, int $status, array $links): ResponseInterface
    {
        if ($links) {
            $data['_links'] = $this->serializeLinks($links);
        }

        return new JsonResponse($data, $status, ['Content-Type' => self::CONTENT_TYPE]);
    }

    /** @param Link[] $links */
    public function collection(array $items, array $meta, array $links): ResponseInterface
    {
        $body = [
            '_links'    => $this->serializeLinks($links),
            '_embedded' => ['items' => $items],
        ];

        if ($meta) {
            $body = array_merge($body, $meta);
        }

        return new JsonResponse($body, 200, ['Content-Type' => self::CONTENT_TYPE]);
    }

    public function empty(int $status): ResponseInterface
    {
        return new EmptyResponse($status);
    }

    /** @param Link[] $links */
    private function serializeLinks(array $links): array
    {
        $result = [];

        foreach ($links as $link) {
            $result[$link->rel] = $link->toArray();
        }

        return $result;
    }
}

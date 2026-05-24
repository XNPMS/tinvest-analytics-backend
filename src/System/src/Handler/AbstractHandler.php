<?php

declare(strict_types=1);

namespace System\Handler;

use Fig\Http\Message\StatusCodeInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;
use System\Http\Link;
use System\Http\ResponseFactory;

use function assert;

abstract class AbstractHandler implements RequestHandlerInterface
{
    protected ?ResponseFactory $responseFactory = null;

    public function setResponseFactory(ResponseFactory $responseFactory): self
    {
        $this->responseFactory = $responseFactory;

        return $this;
    }

    protected function jsonResponse(
        array $data,
        int $status = StatusCodeInterface::STATUS_OK,
        Link ...$links
    ): ResponseInterface {
        assert($this->responseFactory instanceof ResponseFactory);

        return $this->responseFactory->json($data, $status, ...$links);
    }

    protected function created(array $data = [], Link ...$links): ResponseInterface
    {
        assert($this->responseFactory instanceof ResponseFactory);

        return $this->responseFactory->created($data, ...$links);
    }

    protected function noContent(): ResponseInterface
    {
        assert($this->responseFactory instanceof ResponseFactory);

        return $this->responseFactory->noContent();
    }

    protected function collection(array $items, array $meta = [], Link ...$links): ResponseInterface
    {
        assert($this->responseFactory instanceof ResponseFactory);

        return $this->responseFactory->collection($items, $meta, ...$links);
    }
}

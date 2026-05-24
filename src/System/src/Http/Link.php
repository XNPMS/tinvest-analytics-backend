<?php

declare(strict_types=1);

namespace System\Http;

final readonly class Link
{
    public function __construct(
        public string $rel,
        public string $href,
        public bool $templated = false,
    ) {
    }

    public function toArray(): array
    {
        $data = ['href' => $this->href];

        if ($this->templated) {
            $data['templated'] = true;
        }

        return $data;
    }
}

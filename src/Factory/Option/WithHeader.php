<?php

declare(strict_types=1);

namespace Zlodes\Http\Client\Factory\Option;

use Zlodes\Http\Client\Factory\HttpClientConfig;
use Zlodes\Http\Client\Factory\Option;
use Zlodes\Http\Client\Middleware\HeaderMiddleware;

final readonly class WithHeader implements Option
{
    public function __construct(
        private string $name,
        private string $value,
    ) {
    }

    public function apply(HttpClientConfig $config): void
    {
        $config->middlewares[] = new HeaderMiddleware($this->name, $this->value);
    }
}

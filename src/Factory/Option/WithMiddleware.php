<?php

declare(strict_types=1);

namespace Zlodes\Http\Client\Factory\Option;

use Zlodes\Http\Client\Contract\Middleware;
use Zlodes\Http\Client\Factory\HttpClientConfig;
use Zlodes\Http\Client\Factory\Option;

final readonly class WithMiddleware implements Option
{
    /** @var list<Middleware> */
    private array $middlewares;

    public function __construct(Middleware ...$middlewares)
    {
        $this->middlewares = array_values($middlewares);
    }

    public function apply(HttpClientConfig $config): void
    {
        $config->middlewares = [...$config->middlewares, ...$this->middlewares];
    }
}

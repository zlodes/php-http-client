<?php

declare(strict_types=1);

namespace Zlodes\Http\Client\Factory\Option;

use Zlodes\Http\Client\Factory\HttpClientConfig;
use Zlodes\Http\Client\Factory\Option;
use Zlodes\Http\Client\Middleware\TimeoutMiddleware;

final readonly class WithTimeout implements Option
{
    /**
     * @param positive-int|float|null $timeout
     * @param positive-int|float|null $connectTimeout
     */
    public function __construct(
        private int|float|null $timeout = null,
        private int|float|null $connectTimeout = null,
    ) {
    }

    public function apply(HttpClientConfig $config): void
    {
        $config->middlewares[] = new TimeoutMiddleware($this->timeout, $this->connectTimeout);
    }
}

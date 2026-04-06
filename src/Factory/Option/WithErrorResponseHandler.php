<?php

declare(strict_types=1);

namespace Zlodes\Http\Client\Factory\Option;

use Zlodes\Http\Client\Contract\ErrorResponseHandler;
use Zlodes\Http\Client\Factory\HttpClientConfig;
use Zlodes\Http\Client\Factory\Option;

final readonly class WithErrorResponseHandler implements Option
{
    /** @var list<ErrorResponseHandler> */
    private array $handlers;

    public function __construct(ErrorResponseHandler ...$handlers)
    {
        $this->handlers = array_values($handlers);
    }

    public function apply(HttpClientConfig $config): void
    {
        $config->errorResponseHandlers = [...$config->errorResponseHandlers, ...$this->handlers];
    }
}

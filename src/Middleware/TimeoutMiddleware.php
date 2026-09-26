<?php

declare(strict_types=1);

namespace Zlodes\Http\Client\Middleware;

use Psr\Http\Message\ResponseInterface;
use Zlodes\Http\Client\Contract\Middleware;
use Zlodes\Http\Client\Contract\RequestHandler;
use Zlodes\Http\Client\RequestContext;
use Zlodes\Http\Client\TransportOptions;

final readonly class TimeoutMiddleware implements Middleware
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

    public function process(RequestContext $context, RequestHandler $next): ResponseInterface
    {
        return $next->handle($context->withTransportOptions(
            $context->transportOptions->merge(new TransportOptions($this->timeout, $this->connectTimeout)),
        ));
    }
}

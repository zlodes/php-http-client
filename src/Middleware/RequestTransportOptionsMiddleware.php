<?php

declare(strict_types=1);

namespace Zlodes\Http\Client\Middleware;

use Psr\Http\Message\ResponseInterface;
use Zlodes\Http\Client\Contract\Middleware;
use Zlodes\Http\Client\Contract\RequestHandler;
use Zlodes\Http\Client\RequestContext;
use Zlodes\Http\Client\TransportOptions;

/** @internal */
final readonly class RequestTransportOptionsMiddleware implements Middleware
{
    public function __construct(
        private TransportOptions $options,
    ) {
    }

    public function process(RequestContext $context, RequestHandler $next): ResponseInterface
    {
        return $next->handle($context->withTransportOptions(
            $context->transportOptions->merge($this->options),
        ));
    }
}

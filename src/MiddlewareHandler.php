<?php

declare(strict_types=1);

namespace Zlodes\Http\Client;

use Psr\Http\Message\ResponseInterface;
use Zlodes\Http\Client\Contract\Middleware;
use Zlodes\Http\Client\Contract\RequestHandler;

/** @internal */
final readonly class MiddlewareHandler implements RequestHandler
{
    public function __construct(
        private Middleware $middleware,
        private RequestHandler $next,
    ) {
    }

    public function handle(RequestContext $context): ResponseInterface
    {
        return $this->middleware->process($context, $this->next);
    }
}

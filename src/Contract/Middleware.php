<?php

declare(strict_types=1);

namespace Zlodes\Http\Client\Contract;

use Psr\Http\Message\ResponseInterface;
use Zlodes\Http\Client\RequestContext;

/**
 * Intercepts HTTP requests in an onion-model pipeline.
 *
 * Each middleware can inspect/modify the request context before delegating
 * to {@see RequestHandler::handle()} and inspect/modify the response after.
 */
interface Middleware
{
    public function process(RequestContext $context, RequestHandler $next): ResponseInterface;
}

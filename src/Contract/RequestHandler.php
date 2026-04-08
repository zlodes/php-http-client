<?php

declare(strict_types=1);

namespace Zlodes\Http\Client\Contract;

use Psr\Http\Message\ResponseInterface;
use Zlodes\Http\Client\RequestContext;

/**
 * Handles a request context and produces a PSR-7 response.
 *
 * Used as the "next" handler passed to {@see Middleware::process()}.
 */
interface RequestHandler
{
    public function handle(RequestContext $context): ResponseInterface;
}

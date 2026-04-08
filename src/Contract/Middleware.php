<?php

declare(strict_types=1);

namespace Zlodes\Http\Client\Contract;

use Psr\Http\Message\ResponseInterface;
use Zlodes\Http\Client\RequestContext;

interface Middleware
{
    public function process(RequestContext $context, RequestHandler $next): ResponseInterface;
}

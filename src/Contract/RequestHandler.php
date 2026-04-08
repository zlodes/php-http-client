<?php

declare(strict_types=1);

namespace Zlodes\Http\Client\Contract;

use Psr\Http\Message\ResponseInterface;
use Zlodes\Http\Client\RequestContext;

interface RequestHandler
{
    public function handle(RequestContext $context): ResponseInterface;
}

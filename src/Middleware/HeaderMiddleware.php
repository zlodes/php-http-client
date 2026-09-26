<?php

declare(strict_types=1);

namespace Zlodes\Http\Client\Middleware;

use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Zlodes\Http\Client\Contract\Middleware;
use Zlodes\Http\Client\Contract\RequestHandler;
use Zlodes\Http\Client\RequestContext;

final readonly class HeaderMiddleware implements Middleware
{
    public function __construct(
        private string $name,
        private string $value,
    ) {
        if (trim($name) === '') {
            throw new InvalidArgumentException('Header name must be non-empty.');
        }
    }

    public function process(RequestContext $context, RequestHandler $next): ResponseInterface
    {
        if (!$context->httpRequest->hasHeader($this->name)) {
            $context = $context->withHttpRequest(
                $context->httpRequest->withHeader($this->name, $this->value),
            );
        }

        return $next->handle($context);
    }
}

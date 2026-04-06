<?php

declare(strict_types=1);

namespace Zlodes\Http\Client;

use Psr\Http\Message\ResponseInterface;
use Zlodes\Http\Client\Contract\Middleware;
use Zlodes\Http\Client\Contract\RequestHandler;
use Zlodes\Http\Client\Contract\Transport;

final readonly class MiddlewarePipeline implements RequestHandler
{
    /**
     * @param list<Middleware> $middlewares
     */
    public function __construct(
        private Transport $transport,
        private array $middlewares = [],
    ) {
    }

    public function handle(RequestContext $context): ResponseInterface
    {
        $handler = new TransportHandler($this->transport);

        foreach (array_reverse($this->middlewares) as $middleware) {
            $handler = new MiddlewareHandler($middleware, $handler);
        }

        return $handler->handle($context);
    }
}

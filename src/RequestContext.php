<?php

declare(strict_types=1);

namespace Zlodes\Http\Client;

use Closure;
use Psr\Http\Message\RequestInterface;

final readonly class RequestContext
{
    /**
     * @param Closure(): RequestInterface $requestFactory
     */
    public function __construct(
        public RequestInterface $httpRequest,
        public string $requestName,
        public Closure $requestFactory,
    ) {
    }

    public function withHttpRequest(RequestInterface $httpRequest): self
    {
        return new self($httpRequest, $this->requestName, $this->requestFactory);
    }

    public function withFreshHttpRequest(): self
    {
        return new self(($this->requestFactory)(), $this->requestName, $this->requestFactory);
    }
}

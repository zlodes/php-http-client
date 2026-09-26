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
        public TransportOptions $transportOptions = new TransportOptions(),
    ) {
    }

    public function withHttpRequest(RequestInterface $httpRequest): self
    {
        return new self($httpRequest, $this->requestName, $this->requestFactory, $this->transportOptions);
    }

    public function withFreshHttpRequest(): self
    {
        return new self(($this->requestFactory)(), $this->requestName, $this->requestFactory, $this->transportOptions);
    }

    public function withTransportOptions(TransportOptions $options): self
    {
        return new self($this->httpRequest, $this->requestName, $this->requestFactory, $options);
    }
}

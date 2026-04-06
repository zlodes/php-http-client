<?php

declare(strict_types=1);

namespace Zlodes\Http\Client;

use Psr\Http\Message\ResponseInterface;
use Zlodes\Http\Client\Contract\RequestHandler;
use Zlodes\Http\Client\Contract\Transport;

/** @internal */
final readonly class TransportHandler implements RequestHandler
{
    public function __construct(
        private Transport $transport,
    ) {
    }

    public function handle(RequestContext $context): ResponseInterface
    {
        return $this->transport->send($context->httpRequest);
    }
}

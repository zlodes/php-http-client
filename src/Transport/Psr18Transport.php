<?php

declare(strict_types=1);

namespace Zlodes\Http\Client\Transport;

use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Zlodes\Http\Client\Contract\Transport;
use Zlodes\Http\Client\Exception\TransportException;

final readonly class Psr18Transport implements Transport
{
    public function __construct(
        private ClientInterface $client,
    ) {
    }

    public function send(RequestInterface $request): ResponseInterface
    {
        try {
            return $this->client->sendRequest($request);
        } catch (ClientExceptionInterface $e) {
            throw new TransportException($e->getMessage(), (int) $e->getCode(), $e);
        }
    }
}

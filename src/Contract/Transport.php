<?php

declare(strict_types=1);

namespace Zlodes\Http\Client\Contract;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Zlodes\Http\Client\Exception\TransportException;

/**
 * Low-level HTTP transport that sends a PSR-7 request and returns a PSR-7 response.
 */
interface Transport
{
    /**
     * @throws TransportException
     */
    public function send(RequestInterface $request): ResponseInterface;
}

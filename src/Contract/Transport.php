<?php

declare(strict_types=1);

namespace Zlodes\Http\Client\Contract;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Zlodes\Http\Client\Exception\TransportException;
use Zlodes\Http\Client\TransportOptions;

interface Transport
{
    /**
     * Implementations MUST apply every option they support and MUST document the ones they ignore.
     *
     * @throws TransportException
     */
    public function send(RequestInterface $request, TransportOptions $options): ResponseInterface;
}

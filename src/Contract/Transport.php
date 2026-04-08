<?php

declare(strict_types=1);

namespace Zlodes\Http\Client\Contract;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Zlodes\Http\Client\Exception\TransportException;

interface Transport
{
    /**
     * @throws TransportException
     */
    public function send(RequestInterface $request): ResponseInterface;
}

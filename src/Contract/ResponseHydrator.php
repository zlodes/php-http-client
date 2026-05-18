<?php

declare(strict_types=1);

namespace Zlodes\Http\Client\Contract;

use Psr\Http\Message\ResponseInterface;
use Zlodes\Http\Client\Exception\HydrationException;

interface ResponseHydrator
{
    /**
     * @param Request<Response> $request
     *
     * @throws HydrationException
     */
    public function hydrate(ResponseInterface $response, Request $request): Response;
}

<?php

declare(strict_types=1);

namespace Zlodes\Http\Client\Contract;

use Psr\Http\Message\ResponseInterface;
use Zlodes\Http\Client\Exception\HydrationException;

/**
 * Converts a raw PSR-7 response into a typed response DTO.
 *
 * Not called when the request declares no response class ({@see Request::getResponseClass()} returns null).
 */
interface ResponseHydrator
{
    /**
     * @template TResponse of Response
     *
     * @param Request<TResponse> $request
     *
     * @return TResponse
     *
     * @throws HydrationException
     */
    public function hydrate(ResponseInterface $response, Request $request): Response;
}

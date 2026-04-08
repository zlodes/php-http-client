<?php

declare(strict_types=1);

namespace Zlodes\Http\Client\Contract;

use Zlodes\Http\Client\Exception\HttpClientException;

/**
 * Sends an HTTP request and returns a hydrated response DTO.
 *
 * Returns null when the request declares no response class ({@see Request::getResponseClass()}).
 */
interface Client
{
    /**
     * @template TResponse of Response
     *
     * @param Request<TResponse> $request
     *
     * @return TResponse|null
     *
     * @throws HttpClientException
     */
    public function send(Request $request): ?Response;
}

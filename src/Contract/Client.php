<?php

declare(strict_types=1);

namespace Zlodes\Http\Client\Contract;

use Zlodes\Http\Client\Exception\HttpClientException;

interface Client
{
    /**
     * @template TResponse of Response
     *
     * @param Request<TResponse> $request
     *
     * @return TResponse
     *
     * @throws HttpClientException
     */
    public function send(Request $request): Response;
}

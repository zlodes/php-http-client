<?php

declare(strict_types=1);

namespace Zlodes\Http\Client\Contract;

use Psr\Http\Message\ResponseInterface;
use Zlodes\Http\Client\Exception\HttpClientException;

interface ErrorResponseHandler
{
    /**
     * @template TResponse of Response
     *
     * @param Request<TResponse> $request
     */
    public function supports(ResponseInterface $response, Request $request): bool;

    /**
     * @template TResponse of Response
     *
     * @param Request<TResponse> $request
     *
     * @throws HttpClientException
     * @throws \Throwable
     */
    public function toException(ResponseInterface $response, Request $request): HttpClientException;
}

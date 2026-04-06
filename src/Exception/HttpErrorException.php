<?php

declare(strict_types=1);

namespace Zlodes\Http\Client\Exception;

use Psr\Http\Message\ResponseInterface;
use Throwable;
use Zlodes\Http\Client\Contract\Request;
use Zlodes\Http\Client\Contract\Response;

class HttpErrorException extends HttpClientException
{
    /**
     * @template TResponse of Response
     *
     * @param Request<TResponse> $request
     */
    public function __construct(
        public readonly Request $request,
        public readonly ResponseInterface $response,
        public readonly mixed $payload = null,
        string $message = '',
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            $message !== '' ? $message : sprintf(
                'HTTP request "%s" failed with status %d.',
                $request->getName(),
                $response->getStatusCode(),
            ),
            $code !== 0 ? $code : $response->getStatusCode(),
            $previous,
        );
    }
}

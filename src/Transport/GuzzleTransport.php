<?php

declare(strict_types=1);

namespace Zlodes\Http\Client\Transport;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Zlodes\Http\Client\Contract\Transport;
use Zlodes\Http\Client\Exception\TransportException;
use Zlodes\Http\Client\Exception\TransportTimeoutException;
use Zlodes\Http\Client\TransportOptions;

/**
 * Optional transport. Requires guzzlehttp/guzzle, which is not a hard dependency of this library.
 */
final readonly class GuzzleTransport implements Transport
{
    private const int CURL_OPERATION_TIMEDOUT = 28;

    public function __construct(
        private ClientInterface $client,
    ) {
    }

    public function send(RequestInterface $request, TransportOptions $options): ResponseInterface
    {
        $guzzleOptions = ['http_errors' => false];

        if ($options->timeout !== null) {
            $guzzleOptions[RequestOptions::TIMEOUT] = $options->timeout;
        }

        if ($options->connectTimeout !== null) {
            $guzzleOptions[RequestOptions::CONNECT_TIMEOUT] = $options->connectTimeout;
        }

        try {
            return $this->client->send($request, $guzzleOptions);
        } catch (ConnectException $e) {
            if ($this->isTimeout($e)) {
                throw new TransportTimeoutException($e->getMessage(), (int) $e->getCode(), $e);
            }

            throw new TransportException($e->getMessage(), (int) $e->getCode(), $e);
        } catch (GuzzleException $e) {
            throw new TransportException($e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    private function isTimeout(ConnectException $exception): bool
    {
        $context = $exception->getHandlerContext();

        return ($context['errno'] ?? null) === self::CURL_OPERATION_TIMEDOUT;
    }
}

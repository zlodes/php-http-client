<?php

declare(strict_types=1);

namespace Zlodes\Http\Client\Tests\Transport;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\RequestOptions;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Zlodes\Http\Client\Exception\TransportException;
use Zlodes\Http\Client\Exception\TransportTimeoutException;
use Zlodes\Http\Client\Transport\GuzzleTransport;
use Zlodes\Http\Client\TransportOptions;

final class GuzzleTransportTest extends TestCase
{
    public function testMapsOptionsAndDisablesHttpErrors(): void
    {
        $request = new Request('GET', 'https://example.com');
        $expected = new Response(500, [], 'boom');
        $captured = null;

        $client = new class ($expected, $captured) implements ClientInterface {
            public function __construct(
                private readonly ResponseInterface $response,
                private ?array &$captured,
            ) {
            }

            public function send(RequestInterface $request, array $options = []): ResponseInterface
            {
                $this->captured = $options;

                return $this->response;
            }

            public function sendAsync(RequestInterface $request, array $options = []): \GuzzleHttp\Promise\PromiseInterface
            {
                throw new \LogicException('not used');
            }

            public function request(string $method, $uri, array $options = []): ResponseInterface
            {
                throw new \LogicException('not used');
            }

            public function requestAsync(string $method, $uri, array $options = []): \GuzzleHttp\Promise\PromiseInterface
            {
                throw new \LogicException('not used');
            }

            public function getConfig(?string $option = null): mixed
            {
                throw new \LogicException('not used');
            }
        };

        $response = (new GuzzleTransport($client))->send(
            $request,
            new TransportOptions(timeout: 5, connectTimeout: 1.5),
        );

        self::assertSame($expected, $response);
        self::assertSame([
            'http_errors' => false,
            RequestOptions::TIMEOUT => 5,
            RequestOptions::CONNECT_TIMEOUT => 1.5,
        ], $captured);
    }

    public function testOmitsUnsetOptions(): void
    {
        $captured = null;

        $client = new class ($captured) implements ClientInterface {
            public function __construct(private ?array &$captured)
            {
            }

            public function send(RequestInterface $request, array $options = []): ResponseInterface
            {
                $this->captured = $options;

                return new Response(200);
            }

            public function sendAsync(RequestInterface $request, array $options = []): \GuzzleHttp\Promise\PromiseInterface
            {
                throw new \LogicException('not used');
            }

            public function request(string $method, $uri, array $options = []): ResponseInterface
            {
                throw new \LogicException('not used');
            }

            public function requestAsync(string $method, $uri, array $options = []): \GuzzleHttp\Promise\PromiseInterface
            {
                throw new \LogicException('not used');
            }

            public function getConfig(?string $option = null): mixed
            {
                throw new \LogicException('not used');
            }
        };

        (new GuzzleTransport($client))->send(new Request('GET', 'https://example.com'), TransportOptions::none());

        self::assertSame(['http_errors' => false], $captured);
    }

    public function testConnectTimeoutBecomesTransportTimeoutException(): void
    {
        $request = new Request('GET', 'https://example.com');
        $connectException = new ConnectException(
            'cURL error 28: Connection timed out',
            $request,
            null,
            ['errno' => 28],
        );

        $client = $this->clientThrowing($connectException);

        try {
            (new GuzzleTransport($client))->send($request, new TransportOptions(timeout: 5));
            self::fail('Expected TransportTimeoutException');
        } catch (TransportTimeoutException $e) {
            self::assertSame($connectException, $e->getPrevious());
        }
    }

    public function testConnectionRefusedStaysTransportException(): void
    {
        $request = new Request('GET', 'https://example.com');
        $connectException = new ConnectException(
            'cURL error 7: Connection refused',
            $request,
            null,
            ['errno' => 7],
        );

        $client = $this->clientThrowing($connectException);

        try {
            (new GuzzleTransport($client))->send($request, TransportOptions::none());
            self::fail('Expected TransportException');
        } catch (TransportTimeoutException) {
            self::fail('A refused connection must not be reported as a timeout');
        } catch (TransportException $e) {
            self::assertSame($connectException, $e->getPrevious());
        }
    }

    public function testOtherGuzzleExceptionStaysTransportException(): void
    {
        $request = new Request('GET', 'https://example.com');
        $requestException = new RequestException('bad request', $request);

        $this->expectException(TransportException::class);

        (new GuzzleTransport($this->clientThrowing($requestException)))->send($request, TransportOptions::none());
    }

    private function clientThrowing(\Throwable $exception): ClientInterface
    {
        return new class ($exception) implements ClientInterface {
            public function __construct(private readonly \Throwable $exception)
            {
            }

            public function send(RequestInterface $request, array $options = []): ResponseInterface
            {
                throw $this->exception;
            }

            public function sendAsync(RequestInterface $request, array $options = []): \GuzzleHttp\Promise\PromiseInterface
            {
                throw new \LogicException('not used');
            }

            public function request(string $method, $uri, array $options = []): ResponseInterface
            {
                throw new \LogicException('not used');
            }

            public function requestAsync(string $method, $uri, array $options = []): \GuzzleHttp\Promise\PromiseInterface
            {
                throw new \LogicException('not used');
            }

            public function getConfig(?string $option = null): mixed
            {
                throw new \LogicException('not used');
            }
        };
    }
}

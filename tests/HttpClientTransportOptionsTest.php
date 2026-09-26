<?php

declare(strict_types=1);

namespace Zlodes\Http\Client\Tests;

use GuzzleHttp\Psr7\Request as GuzzleRequest;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Zlodes\Http\Client\Contract\HasTransportOptions;
use Zlodes\Http\Client\Contract\Request;
use Zlodes\Http\Client\Contract\Response as ResponseContract;
use Zlodes\Http\Client\Contract\ResponseHydrator;
use Zlodes\Http\Client\Contract\Transport;
use Zlodes\Http\Client\HttpClient;
use Zlodes\Http\Client\Middleware\TimeoutMiddleware;
use Zlodes\Http\Client\TransportOptions;

final class HttpClientTransportOptionsTest extends TestCase
{
    public function testRequestOptionsWinOverMiddleware(): void
    {
        $captured = null;

        $transport = new class ($captured) implements Transport {
            public function __construct(private ?TransportOptions &$captured)
            {
            }

            public function send(RequestInterface $request, TransportOptions $options): ResponseInterface
            {
                $this->captured = $options;

                return new Response(200);
            }
        };

        $client = new HttpClient(
            transport: $transport,
            responseHydrator: $this->hydrator(),
            middlewares: [new TimeoutMiddleware(timeout: 30, connectTimeout: 5)],
        );

        $client->send(new class implements Request, HasTransportOptions {
            public function getName(): string
            {
                return 'slow.report';
            }

            public function buildRequest(): RequestInterface
            {
                return new GuzzleRequest('GET', 'https://example.com/report');
            }

            public function getResponseClass(): string
            {
                return ResponseContract::class;
            }

            public function getTransportOptions(): TransportOptions
            {
                return new TransportOptions(timeout: 120);
            }
        });

        self::assertNotNull($captured);
        self::assertSame(120, $captured->timeout);
        self::assertSame(5, $captured->connectTimeout);
    }

    public function testRequestWithoutOptionsKeepsMiddlewareValues(): void
    {
        $captured = null;

        $transport = new class ($captured) implements Transport {
            public function __construct(private ?TransportOptions &$captured)
            {
            }

            public function send(RequestInterface $request, TransportOptions $options): ResponseInterface
            {
                $this->captured = $options;

                return new Response(200);
            }
        };

        $client = new HttpClient(
            transport: $transport,
            responseHydrator: $this->hydrator(),
            middlewares: [new TimeoutMiddleware(timeout: 30, connectTimeout: 5)],
        );

        $client->send(new class implements Request {
            public function getName(): string
            {
                return 'health';
            }

            public function buildRequest(): RequestInterface
            {
                return new GuzzleRequest('GET', 'https://example.com/health');
            }

            public function getResponseClass(): string
            {
                return ResponseContract::class;
            }
        });

        self::assertNotNull($captured);
        self::assertSame(30, $captured->timeout);
        self::assertSame(5, $captured->connectTimeout);
    }

    private function hydrator(): ResponseHydrator
    {
        return new class implements ResponseHydrator {
            public function hydrate(ResponseInterface $response, Request $request): ResponseContract
            {
                return new class implements ResponseContract {
                };
            }
        };
    }
}

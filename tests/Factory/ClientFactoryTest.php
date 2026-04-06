<?php

declare(strict_types=1);

namespace Zlodes\Http\Client\Tests\Factory;

use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Uri;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Zlodes\Http\Client\Contract\ErrorResponseHandler;
use Zlodes\Http\Client\Contract\Middleware;
use Zlodes\Http\Client\Contract\Request;
use Zlodes\Http\Client\Contract\RequestHandler;
use Zlodes\Http\Client\Contract\Response as ResponseContract;
use Zlodes\Http\Client\Contract\ResponseHydrator;
use Zlodes\Http\Client\Contract\Transport;
use Zlodes\Http\Client\Exception\HttpClientException;
use Zlodes\Http\Client\Factory\ClientFactory;
use Zlodes\Http\Client\Factory\HttpClientConfig;
use Zlodes\Http\Client\Factory\Option\WithBaseUri;
use Zlodes\Http\Client\Factory\Option\WithErrorResponseHandler;
use Zlodes\Http\Client\Factory\Option\WithMiddleware;
use Zlodes\Http\Client\Factory\Option\WithResponseHydrator;
use Zlodes\Http\Client\Factory\Option\WithTransport;
use Zlodes\Http\Client\HttpClient;
use Zlodes\Http\Client\RequestContext;

final class ClientFactoryTest extends TestCase
{
    public function testMakeCreatesClientWithAllOptions(): void
    {
        $transport = $this->createTransport(new Response(200));
        $hydrator = $this->createHydrator();
        $middleware = $this->createMiddleware();
        $errorHandler = $this->createErrorHandler();
        $baseUri = new Uri('https://api.example.com');

        $factory = new ClientFactory();
        $client = $factory->make(
            new WithTransport($transport),
            new WithResponseHydrator($hydrator),
            new WithMiddleware($middleware),
            new WithErrorResponseHandler($errorHandler),
            new WithBaseUri($baseUri),
        );

        $result = $client->send($this->createRequest('GET', '/users'));

        self::assertInstanceOf(ResponseContract::class, $result);
    }

    public function testMakeThrowsWhenTransportMissing(): void
    {
        $factory = new ClientFactory();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Transport is required');

        $factory->make(
            new WithResponseHydrator($this->createHydrator()),
        );
    }

    public function testMakeThrowsWhenHydratorMissing(): void
    {
        $factory = new ClientFactory();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('ResponseHydrator is required');

        $factory->make(
            new WithTransport($this->createTransport(new Response(200))),
        );
    }

    public function testDefaultsAppliedBeforeMakeOptions(): void
    {
        $transport = $this->createTransport(new Response(200));
        $hydrator = $this->createHydrator();
        $baseUri = new Uri('https://api.example.com');

        $factory = new ClientFactory(defaults: [
            new WithTransport($transport),
            new WithResponseHydrator($hydrator),
        ]);

        $client = $factory->make(
            new WithBaseUri($baseUri),
        );

        $result = $client->send($this->createRequest('GET', '/test'));

        self::assertInstanceOf(ResponseContract::class, $result);
    }

    public function testMakeOptionsOverrideDefaults(): void
    {
        $transport = $this->createTransport(new Response(200));
        $hydrator = $this->createHydrator();

        $factory = new ClientFactory(defaults: [
            new WithTransport($transport),
            new WithResponseHydrator($hydrator),
            new WithBaseUri(new Uri('https://default.example.com')),
        ]);

        $capturedRequest = null;
        $capturingTransport = new class ($capturedRequest) implements Transport {
            public function __construct(private ?RequestInterface &$captured)
            {
            }

            public function send(RequestInterface $request): ResponseInterface
            {
                $this->captured = $request;

                return new Response(200);
            }
        };

        $client = $factory->make(
            new WithTransport($capturingTransport),
            new WithBaseUri(new Uri('https://override.example.com')),
        );

        $client->send($this->createRequest('GET', '/path'));

        self::assertNotNull($capturedRequest);
        self::assertSame('override.example.com', $capturedRequest->getUri()->getHost());
    }

    public function testMiddlewaresAccumulateAcrossDefaultsAndMake(): void
    {
        $order = [];

        $middlewareA = new class ($order, 'A') implements Middleware {
            public function __construct(private array &$order, private string $label)
            {
            }

            public function process(RequestContext $context, RequestHandler $next): ResponseInterface
            {
                $this->order[] = $this->label;

                return $next->handle($context);
            }
        };

        $middlewareB = new class ($order, 'B') implements Middleware {
            public function __construct(private array &$order, private string $label)
            {
            }

            public function process(RequestContext $context, RequestHandler $next): ResponseInterface
            {
                $this->order[] = $this->label;

                return $next->handle($context);
            }
        };

        $factory = new ClientFactory(defaults: [
            new WithTransport($this->createTransport(new Response(200))),
            new WithResponseHydrator($this->createHydrator()),
            new WithMiddleware($middlewareA),
        ]);

        $client = $factory->make(
            new WithMiddleware($middlewareB),
        );

        $client->send($this->createRequest());

        self::assertSame(['A', 'B'], $order);
    }

    public function testCustomClientCreator(): void
    {
        $capturedConfig = null;

        $factory = new ClientFactory(
            clientCreator: function (HttpClientConfig $config) use (&$capturedConfig): HttpClient {
                $capturedConfig = $config;

                return new HttpClient(
                    $config->transport,
                    $config->responseHydrator,
                    $config->middlewares,
                    $config->errorResponseHandlers,
                    $config->baseUri,
                );
            },
        );

        $transport = $this->createTransport(new Response(200));
        $hydrator = $this->createHydrator();
        $baseUri = new Uri('https://custom.example.com');

        $client = $factory->make(
            new WithTransport($transport),
            new WithResponseHydrator($hydrator),
            new WithBaseUri($baseUri),
        );

        self::assertNotNull($capturedConfig);
        self::assertSame($transport, $capturedConfig->transport);
        self::assertSame($hydrator, $capturedConfig->responseHydrator);
        self::assertSame($baseUri, $capturedConfig->baseUri);
        self::assertSame([], $capturedConfig->middlewares);
        self::assertSame([], $capturedConfig->errorResponseHandlers);

        $result = $client->send($this->createRequest('GET', '/test'));
        self::assertInstanceOf(ResponseContract::class, $result);
    }

    private function createTransport(ResponseInterface $response): Transport
    {
        return new class ($response) implements Transport {
            public function __construct(private readonly ResponseInterface $response)
            {
            }

            public function send(RequestInterface $request): ResponseInterface
            {
                return $this->response;
            }
        };
    }

    private function createHydrator(): ResponseHydrator
    {
        return new class implements ResponseHydrator {
            public function hydrate(ResponseInterface $response, Request $request): ResponseContract
            {
                return new class implements ResponseContract {
                };
            }
        };
    }

    private function createMiddleware(): Middleware
    {
        return new class implements Middleware {
            public function process(RequestContext $context, RequestHandler $next): ResponseInterface
            {
                return $next->handle($context);
            }
        };
    }

    private function createErrorHandler(): ErrorResponseHandler
    {
        return new class implements ErrorResponseHandler {
            public function supports(ResponseInterface $response, Request $request): bool
            {
                return false;
            }

            public function toException(ResponseInterface $response, Request $request): HttpClientException
            {
                throw new \RuntimeException('Should not be called');
            }
        };
    }

    /**
     * @return Request<ResponseContract>
     */
    private function createRequest(string $method = 'GET', string $uri = 'https://example.com'): Request
    {
        return new class ($method, $uri) implements Request {
            public function __construct(
                private readonly string $method,
                private readonly string $uri,
            ) {
            }

            public function getName(): string
            {
                return 'test.request';
            }

            public function buildRequest(): RequestInterface
            {
                return new \GuzzleHttp\Psr7\Request($this->method, $this->uri);
            }

            public function getResponseClass(): string
            {
                return ResponseContract::class;
            }
        };
    }
}

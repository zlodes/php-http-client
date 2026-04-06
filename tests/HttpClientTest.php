<?php

declare(strict_types=1);

namespace Zlodes\Http\Client\Tests;

use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Zlodes\Http\Client\Contract\ErrorResponseHandler;
use Zlodes\Http\Client\Contract\HasErrorResponseHandlers;
use Zlodes\Http\Client\Contract\HasResponseHydrator;
use Zlodes\Http\Client\Contract\Request;
use Zlodes\Http\Client\Contract\Response as ResponseContract;
use Zlodes\Http\Client\Contract\ResponseHydrator;
use Zlodes\Http\Client\Contract\Transport;
use Zlodes\Http\Client\Exception\HydrationException;
use Zlodes\Http\Client\Exception\HttpClientException;
use Zlodes\Http\Client\Exception\HttpErrorException;
use Zlodes\Http\Client\HttpClient;

final class HttpClientTest extends TestCase
{
    public function testSendUsesDefaultHydrator(): void
    {
        $httpResponse = new Response(200, [], '{"name":"Alice"}');

        $transport = $this->createTransport($httpResponse);

        $expectedResult = new class ('Alice') implements ResponseContract {
            public function __construct(public readonly string $name)
            {
            }
        };

        $hydrator = new class ($expectedResult) implements ResponseHydrator {
            public function __construct(private readonly ResponseContract $result)
            {
            }

            public function hydrate(ResponseInterface $response, Request $request): ResponseContract
            {
                return $this->result;
            }
        };

        $client = new HttpClient($transport, $hydrator);
        $result = $client->send($this->createRequest());

        self::assertSame($expectedResult, $result);
    }

    public function testSendUsesCustomHydratorFromRequest(): void
    {
        $httpResponse = new Response(200);
        $transport = $this->createTransport($httpResponse);

        $defaultResult = new class implements ResponseContract {
        };
        $customResult = new class ('custom') implements ResponseContract {
            public function __construct(public readonly string $value)
            {
            }
        };

        $defaultHydrator = new class ($defaultResult) implements ResponseHydrator {
            public function __construct(private readonly ResponseContract $result)
            {
            }

            public function hydrate(ResponseInterface $response, Request $request): ResponseContract
            {
                return $this->result;
            }
        };

        $customHydrator = new class ($customResult) implements ResponseHydrator {
            public function __construct(private readonly ResponseContract $result)
            {
            }

            public function hydrate(ResponseInterface $response, Request $request): ResponseContract
            {
                return $this->result;
            }
        };

        $request = $this->createRequestWithHydrator($customHydrator);

        $client = new HttpClient($transport, $defaultHydrator);
        $result = $client->send($request);

        self::assertSame($customResult, $result);
    }

    public function testSendWrapsArbitraryExceptionsIntoHydrationException(): void
    {
        $transport = $this->createTransport(new Response(200));

        $hydrator = new class implements ResponseHydrator {
            public function hydrate(ResponseInterface $response, Request $request): ResponseContract
            {
                throw new \InvalidArgumentException('bad json');
            }
        };

        $client = new HttpClient($transport, $hydrator);

        try {
            $client->send($this->createRequest());
            self::fail('Expected HydrationException');
        } catch (HydrationException $e) {
            self::assertSame('bad json', $e->getMessage());
            self::assertInstanceOf(\InvalidArgumentException::class, $e->getPrevious());
        }
    }

    public function testSendPassesThroughHydrationException(): void
    {
        $transport = $this->createTransport(new Response(200));
        $original = new HydrationException('already hydration');

        $hydrator = new class ($original) implements ResponseHydrator {
            public function __construct(private readonly HydrationException $exception)
            {
            }

            public function hydrate(ResponseInterface $response, Request $request): ResponseContract
            {
                throw $this->exception;
            }
        };

        $client = new HttpClient($transport, $hydrator);

        try {
            $client->send($this->createRequest());
            self::fail('Expected HydrationException');
        } catch (HydrationException $e) {
            self::assertSame($original, $e);
        }
    }

    public function testSendPassesCorrectHttpRequestToTransport(): void
    {
        $capturedUri = $this->sendAndCaptureUri(
            request: $this->createRequest('PUT', 'https://api.test/resource'),
        );

        self::assertSame('https://api.test/resource', $capturedUri);
    }

    public function testBaseUriPrependedToRelativePath(): void
    {
        $capturedUri = $this->sendAndCaptureUri(
            request: $this->createRequest('GET', '/users/42'),
            baseUri: new \GuzzleHttp\Psr7\Uri('https://api.example.com'),
        );

        self::assertSame('https://api.example.com/users/42', $capturedUri);
    }

    public function testBaseUriWithPathPrependedToRelativePath(): void
    {
        $capturedUri = $this->sendAndCaptureUri(
            request: $this->createRequest('GET', '/users/42'),
            baseUri: new \GuzzleHttp\Psr7\Uri('https://api.example.com/v2'),
        );

        self::assertSame('https://api.example.com/v2/users/42', $capturedUri);
    }

    public function testBaseUriWithTrailingSlash(): void
    {
        $capturedUri = $this->sendAndCaptureUri(
            request: $this->createRequest('GET', '/users'),
            baseUri: new \GuzzleHttp\Psr7\Uri('https://api.example.com/v2/'),
        );

        self::assertSame('https://api.example.com/v2/users', $capturedUri);
    }

    public function testBaseUriIgnoredWhenRequestHasHost(): void
    {
        $capturedUri = $this->sendAndCaptureUri(
            request: $this->createRequest('GET', 'https://other.api/data'),
            baseUri: new \GuzzleHttp\Psr7\Uri('https://api.example.com'),
        );

        self::assertSame('https://other.api/data', $capturedUri);
    }

    public function testBaseUriPreservesQueryAndFragment(): void
    {
        $capturedUri = $this->sendAndCaptureUri(
            request: $this->createRequest('GET', '/search?q=test#results'),
            baseUri: new \GuzzleHttp\Psr7\Uri('https://api.example.com'),
        );

        self::assertSame('https://api.example.com/search?q=test#results', $capturedUri);
    }

    public function testSendThrowsGenericHttpErrorExceptionWhenNoErrorHandlerMatches(): void
    {
        $httpResponse = new Response(500, [], '{"error":"boom"}');
        $transport = $this->createTransport($httpResponse);
        $hydratorCalled = false;

        $hydrator = new class ($hydratorCalled) implements ResponseHydrator {
            public function __construct(private bool &$hydratorCalled)
            {
            }

            public function hydrate(ResponseInterface $response, Request $request): ResponseContract
            {
                $this->hydratorCalled = true;

                return new class implements ResponseContract {
                };
            }
        };

        $request = $this->createRequest();
        $client = new HttpClient($transport, $hydrator);

        try {
            $client->send($request);
            self::fail('Expected HttpErrorException');
        } catch (HttpErrorException $e) {
            self::assertSame($request, $e->request);
            self::assertSame($httpResponse, $e->response);
            self::assertNull($e->payload);
            self::assertSame(500, $e->getCode());
            self::assertSame('HTTP request "test.request" failed with status 500.', $e->getMessage());
        }

        self::assertFalse($hydratorCalled);
    }

    public function testSendUsesMatchingErrorResponseHandler(): void
    {
        $httpResponse = new Response(422, ['Content-Type' => 'application/json'], '{"errors":{"email":["already taken"]}}');
        $transport = $this->createTransport($httpResponse);
        $hydratorCalled = false;

        $hydrator = new class ($hydratorCalled) implements ResponseHydrator {
            public function __construct(private bool &$hydratorCalled)
            {
            }

            public function hydrate(ResponseInterface $response, Request $request): ResponseContract
            {
                $this->hydratorCalled = true;

                return new class implements ResponseContract {
                };
            }
        };

        $handler = new class implements ErrorResponseHandler {
            public function supports(ResponseInterface $response, Request $request): bool
            {
                return $response->getStatusCode() === 422 && $request->getName() === 'test.request';
            }

            public function toException(ResponseInterface $response, Request $request): HttpClientException
            {
                /** @var array{errors: array<string, list<string>>} $payload */
                $payload = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);

                return new ValidationFailedException($payload['errors'], $request, $response);
            }
        };

        $client = new HttpClient($transport, $hydrator, [], [$handler]);
        $request = $this->createRequest();

        try {
            $client->send($request);
            self::fail('Expected ValidationFailedException');
        } catch (ValidationFailedException $e) {
            self::assertSame(['email' => ['already taken']], $e->errors);
            self::assertSame($httpResponse, $e->response);
            self::assertSame(422, $e->getCode());
        }

        self::assertFalse($hydratorCalled);
    }

    public function testRequestErrorResponseHandlersTakePrecedenceOverClientHandlers(): void
    {
        $httpResponse = new Response(422, [], '{"errors":{"email":["already taken"]}}');
        $transport = $this->createTransport($httpResponse);

        $hydrator = new class implements ResponseHydrator {
            public function hydrate(ResponseInterface $response, Request $request): ResponseContract
            {
                return new class implements ResponseContract {
                };
            }
        };

        $clientHandler = new class implements ErrorResponseHandler {
            public function supports(ResponseInterface $response, Request $request): bool
            {
                return $response->getStatusCode() === 422;
            }

            public function toException(ResponseInterface $response, Request $request): HttpClientException
            {
                return new HttpErrorException($request, $response, message: 'client handler');
            }
        };

        $requestHandler = new class implements ErrorResponseHandler {
            public function supports(ResponseInterface $response, Request $request): bool
            {
                return $response->getStatusCode() === 422;
            }

            public function toException(ResponseInterface $response, Request $request): HttpClientException
            {
                return new HttpErrorException($request, $response, message: 'request handler');
            }
        };

        $client = new HttpClient($transport, $hydrator, [], [$clientHandler]);
        $request = $this->createRequestWithErrorHandlers([$requestHandler]);

        try {
            $client->send($request);
            self::fail('Expected HttpErrorException');
        } catch (HttpErrorException $e) {
            self::assertSame('request handler', $e->getMessage());
        }
    }

    public function testSendWrapsUnexpectedErrorHandlerFailures(): void
    {
        $httpResponse = new Response(422, [], '{"errors":');
        $transport = $this->createTransport($httpResponse);

        $hydrator = new class implements ResponseHydrator {
            public function hydrate(ResponseInterface $response, Request $request): ResponseContract
            {
                return new class implements ResponseContract {
                };
            }
        };

        $handler = new class implements ErrorResponseHandler {
            public function supports(ResponseInterface $response, Request $request): bool
            {
                return $response->getStatusCode() === 422;
            }

            public function toException(ResponseInterface $response, Request $request): HttpClientException
            {
                json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);

                return new HttpErrorException($request, $response);
            }
        };

        $client = new HttpClient($transport, $hydrator, [], [$handler]);

        try {
            $client->send($this->createRequest());
            self::fail('Expected HttpErrorException');
        } catch (HttpErrorException $e) {
            self::assertSame(422, $e->getCode());
            self::assertSame(
                'Failed to handle HTTP error response for "test.request" with status 422.',
                $e->getMessage(),
            );
            self::assertInstanceOf(\JsonException::class, $e->getPrevious());
        }
    }

    private function sendAndCaptureUri(Request $request, ?\GuzzleHttp\Psr7\Uri $baseUri = null): string
    {
        $capturedRequest = null;

        $transport = new class ($capturedRequest) implements Transport {
            public function __construct(private ?RequestInterface &$captured)
            {
            }

            public function send(RequestInterface $request): ResponseInterface
            {
                $this->captured = $request;

                return new Response(200);
            }
        };

        $hydrator = new class implements ResponseHydrator {
            public function hydrate(ResponseInterface $response, Request $request): ResponseContract
            {
                return new class implements ResponseContract {
                };
            }
        };

        $client = new HttpClient($transport, $hydrator, baseUri: $baseUri);
        $client->send($request);

        self::assertNotNull($capturedRequest);

        return (string) $capturedRequest->getUri();
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

    /**
     * @return Request<ResponseContract>&HasResponseHydrator
     */
    private function createRequestWithHydrator(ResponseHydrator $hydrator): Request
    {
        return new class ($hydrator) implements Request, HasResponseHydrator {
            public function __construct(private readonly ResponseHydrator $hydrator)
            {
            }

            public function getName(): string
            {
                return 'test.custom';
            }

            public function buildRequest(): RequestInterface
            {
                return new \GuzzleHttp\Psr7\Request('GET', 'https://example.com');
            }

            public function getResponseClass(): string
            {
                return ResponseContract::class;
            }

            public function getResponseHydrator(): ResponseHydrator
            {
                return $this->hydrator;
            }
        };
    }

    /**
     * @param list<ErrorResponseHandler> $handlers
     *
     * @return Request<ResponseContract>&HasErrorResponseHandlers
     */
    private function createRequestWithErrorHandlers(array $handlers): Request
    {
        return new class ($handlers) implements Request, HasErrorResponseHandlers {
            /** @param list<ErrorResponseHandler> $handlers */
            public function __construct(private readonly array $handlers)
            {
            }

            public function getName(): string
            {
                return 'test.request';
            }

            public function buildRequest(): RequestInterface
            {
                return new \GuzzleHttp\Psr7\Request('GET', 'https://example.com');
            }

            public function getResponseClass(): string
            {
                return ResponseContract::class;
            }

            public function getErrorResponseHandlers(): array
            {
                return $this->handlers;
            }
        };
    }
}

final class ValidationFailedException extends HttpErrorException
{
    /**
     * @param array<string, list<string>> $errors
     */
    public function __construct(
        public readonly array $errors,
        Request $request,
        ResponseInterface $response,
    ) {
        parent::__construct($request, $response, $errors, 'Validation failed');
    }
}

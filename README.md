# zlodes/http-client

Observable, type-safe HTTP client for PHP 8.4+.

Generic `Request<TResponse>` ensures `$client->send(request: $request)` returns the correct response type — verified by PHPStan at max level. Requests that don't need a response body (e.g. DELETE) can return `null` from `getResponseClass()` and the client will skip hydration.

## Features

- **Type-safe** — generic `Request<TResponse>` to `TResponse` flow, fully validated by PHPStan
- **Composable** — middleware pipeline for auth, retries, logging, metrics, tracing, and custom concerns
- **Framework-agnostic** — depends only on PSR interfaces (`psr/http-message`, `psr/http-client`)
- **Fiber-compatible** — synchronous API that transparently supports async via AMP/Revolt fibers
- **Flexible hydration** — default `ResponseHydrator` on the client, per-request override via `HasResponseHydrator`
- **Typed error handling** — map `4xx/5xx` responses into typed exceptions with reusable error handlers

## Installation

```bash
composer require zlodes/http-client
```

You'll also need a PSR-7 implementation and a PSR-18 HTTP client:

```bash
composer require guzzlehttp/psr7 guzzlehttp/guzzle
```

Optional integrations such as logging and metrics are up to your application and middleware choices.

## Quick Start

### Define a request

```php
use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\RequestInterface;
use Zlodes\Http\Client\Contract\Request as RequestContract;

/**
 * @implements RequestContract<GetUserResponse>
 */
final readonly class GetUserRequest implements RequestContract
{
    public function __construct(private int $userId) {}

    public function getName(): string
    {
        return 'users.get';
    }

    public function buildRequest(): RequestInterface
    {
        return new Request(
            method: 'GET',
            uri: "https://api.example.com/users/{$this->userId}",
        );
    }

    public function getResponseClass(): string
    {
        return GetUserResponse::class;
    }
}
```

### Define a response

```php
use Zlodes\Http\Client\Contract\Response;

final readonly class GetUserResponse implements Response
{
    public function __construct(
        public int $id,
        public string $name,
    ) {}
}
```

### Implement a hydrator

```php
use Psr\Http\Message\ResponseInterface;
use Zlodes\Http\Client\Contract\Request;
use Zlodes\Http\Client\Contract\Response;
use Zlodes\Http\Client\Contract\ResponseHydrator;

final readonly class JsonHydrator implements ResponseHydrator
{
    public function __construct(private SerializerInterface $serializer) {}

    public function hydrate(ResponseInterface $response, Request $request): Response
    {
        return $this->serializer->deserialize(
            data: (string) $response->getBody(),
            type: $request->getResponseClass(),
            format: 'json',
        );
    }
}
```

### Wire it together

```php
use Zlodes\Http\Client\HttpClient;
use Zlodes\Http\Client\Transport\Psr18Transport;

$transport = new Psr18Transport(client: $psr18Client);

$client = new HttpClient(
    transport: $transport,
    responseHydrator: $responseHydrator,
);

// PHPStan knows this returns GetUserResponse
$response = $client->send(
    request: new GetUserRequest(userId: 42),
);
echo $response->name;
```

## Fire-and-Forget Requests

For requests that don't return a response body (e.g. DELETE, PUT with no content), return `null` from `getResponseClass()`. The client will skip hydration and return `null`:

```php
use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\RequestInterface;
use Zlodes\Http\Client\Contract\Request as RequestContract;

/**
 * @implements RequestContract<never>
 */
final readonly class DeleteUserRequest implements RequestContract
{
    public function __construct(private int $userId) {}

    public function getName(): string
    {
        return 'users.delete';
    }

    public function buildRequest(): RequestInterface
    {
        return new Request(
            method: 'DELETE',
            uri: "/users/{$this->userId}",
        );
    }

    public function getResponseClass(): ?string
    {
        return null;
    }
}
```

```php
$client->send(request: new DeleteUserRequest(userId: 42)); // returns null
```

Error handling still runs — `4xx/5xx` responses throw exceptions even for response-less requests.

## Per-Request Hydration Override

For APIs that need custom parsing, implement `HasResponseHydrator` on the request:

```php
use Zlodes\Http\Client\Contract\HasResponseHydrator;
use Zlodes\Http\Client\Contract\ResponseHydrator;

final readonly class LegacyApiRequest implements RequestContract, HasResponseHydrator
{
    public function __construct(
        private string $id,
        private ResponseHydrator $hydrator,
    ) {}

    public function getResponseHydrator(): ResponseHydrator
    {
        return $this->hydrator;
    }

    // ... getName(), buildRequest(), getResponseClass()
}
```

The client checks for `HasResponseHydrator` first, then falls back to the default hydrator.

## Error Responses

`HttpClient::send()` treats `4xx/5xx` responses as failures. Register `ErrorResponseHandler` implementations to turn those responses into typed exceptions.

```php
use Psr\Http\Message\ResponseInterface;
use Zlodes\Http\Client\Contract\ErrorResponseHandler;
use Zlodes\Http\Client\Contract\Request as RequestContract;
use Zlodes\Http\Client\Exception\HttpClientException;
use Zlodes\Http\Client\Exception\HttpErrorException;

final class ValidationFailedException extends HttpErrorException
{
    public function __construct(
        public readonly array $errors,
        RequestContract $request,
        ResponseInterface $response,
    ) {
        parent::__construct(
            request: $request,
            response: $response,
            payload: $errors,
            message: 'Validation failed',
        );
    }
}

final readonly class ValidationErrorHandler implements ErrorResponseHandler
{
    public function supports(ResponseInterface $response, RequestContract $request): bool
    {
        return $response->getStatusCode() === 422
            && $request instanceof CreateUserRequest;
    }

    public function toException(ResponseInterface $response, RequestContract $request): HttpClientException
    {
        /** @var array{errors: array<string, list<string>>} $payload */
        $payload = json_decode(
            json: (string) $response->getBody(),
            associative: true,
            flags: JSON_THROW_ON_ERROR,
        );

        return new ValidationFailedException(
            errors: $payload['errors'],
            request: $request,
            response: $response,
        );
    }
}
```

Register handlers globally on the client, after the middleware array:

```php
$client = new HttpClient(
    transport: $transport,
    responseHydrator: $responseHydrator,
    middlewares: [],
    errorResponseHandlers: [new ValidationErrorHandler()],
);
```

Or per request with `HasErrorResponseHandlers`; request-level handlers are checked before client-level ones.

```php
$request = new CreateUserRequest(input: $input);

try {
    $response = $client->send(request: $request);
} catch (ValidationFailedException $e) {
    return CreateUserResult::validationFailed(errors: $e->errors);
} catch (HttpErrorException $e) {
    // Fallback for unhandled upstream errors. Raw PSR-7 response is available on $e->response.
}
```

## Middleware

Middleware follows an onion model. Each middleware receives a `RequestContext` and a `RequestHandler $next`:

```php
use Zlodes\Http\Client\Contract\Middleware;
use Zlodes\Http\Client\Contract\RequestHandler;
use Zlodes\Http\Client\RequestContext;
use Psr\Http\Message\ResponseInterface;

final readonly class AuthMiddleware implements Middleware
{
    public function process(RequestContext $context, RequestHandler $next): ResponseInterface
    {
        $authenticatedRequest = $context->httpRequest->withHeader(
            name: 'Authorization',
            value: 'Bearer ...',
        );

        return $next->handle(
            context: $context->withHttpRequest(httpRequest: $authenticatedRequest),
        );
    }
}
```

### Logging example

```php
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Zlodes\Http\Client\Contract\Middleware;
use Zlodes\Http\Client\Contract\RequestHandler;
use Zlodes\Http\Client\RequestContext;

final readonly class LoggingMiddleware implements Middleware
{
    public function __construct(private LoggerInterface $logger) {}

    public function process(RequestContext $context, RequestHandler $next): ResponseInterface
    {
        $this->logger->info(
            message: 'Sending HTTP request',
            context: [
                'name' => $context->requestName,
                'method' => $context->httpRequest->getMethod(),
                'uri' => (string) $context->httpRequest->getUri(),
            ],
        );

        try {
            $response = $next->handle(context: $context);
        } catch (\Throwable $e) {
            $this->logger->error(
                message: 'HTTP request failed',
                context: [
                    'name' => $context->requestName,
                    'method' => $context->httpRequest->getMethod(),
                    'uri' => (string) $context->httpRequest->getUri(),
                    'error' => $e->getMessage(),
                ],
            );

            throw $e;
        }

        $this->logger->info(
            message: 'HTTP response received',
            context: [
                'name' => $context->requestName,
                'status' => $response->getStatusCode(),
            ],
        );

        return $response;
    }
}
```

### Metrics example

```php
use Psr\Http\Message\ResponseInterface;
use Zlodes\Http\Client\Contract\Middleware;
use Zlodes\Http\Client\Contract\RequestHandler;
use Zlodes\Http\Client\RequestContext;

interface MetricsCollector
{
    public function recordRequest(string $name, string $method, int $statusCode, float $duration): void;
}

final readonly class MetricsMiddleware implements Middleware
{
    public function __construct(private MetricsCollector $collector) {}

    public function process(RequestContext $context, RequestHandler $next): ResponseInterface
    {
        $start = hrtime(as_number: true);
        $response = $next->handle(context: $context);
        $duration = (hrtime(as_number: true) - $start) / 1e9;

        $this->collector->recordRequest(
            name: $context->requestName,
            method: $context->httpRequest->getMethod(),
            statusCode: $response->getStatusCode(),
            duration: $duration,
        );

        return $response;
    }
}
```

Wire them in by passing middleware instances to `HttpClient`:

```php
$client = new HttpClient(
    transport: $transport,
    responseHydrator: $responseHydrator,
    middlewares: [
        new LoggingMiddleware(logger: $logger),
        new MetricsMiddleware(collector: $metricsCollector),
    ],
);
```

## ClientFactory

For applications with multiple API clients sharing common configuration (transport, logging middleware, hydrator), use `ClientFactory` with composable options instead of constructing `HttpClient` directly.

```php
use Zlodes\Http\Client\Factory\ClientFactory;
use Zlodes\Http\Client\Factory\Option\WithTransport;
use Zlodes\Http\Client\Factory\Option\WithResponseHydrator;
use Zlodes\Http\Client\Factory\Option\WithMiddleware;
use Zlodes\Http\Client\Factory\Option\WithBaseUri;

// Shared defaults for all clients
$factory = new ClientFactory(defaults: [
    new WithTransport($transport),
    new WithResponseHydrator($hydrator),
    new WithMiddleware($loggingMiddleware, $metricsMiddleware),
]);

// Per-service clients add their own options
$usersClient = $factory->make(
    new WithBaseUri(new Uri('https://users-api.example.com')),
);

$billingClient = $factory->make(
    new WithBaseUri(new Uri('https://billing-api.example.com')),
    new WithMiddleware($billingAuthMiddleware),
);
```

### Available options

| Option                                               | Behavior                              |
|------------------------------------------------------|---------------------------------------|
| `WithTransport(Transport)`                           | Sets the transport (last-writer-wins) |
| `WithResponseHydrator(ResponseHydrator)`             | Sets the hydrator (last-writer-wins)  |
| `WithMiddleware(Middleware ...)`                     | Appends middlewares (additive)        |
| `WithErrorResponseHandler(ErrorResponseHandler ...)` | Appends error handlers (additive)     |
| `WithBaseUri(UriInterface)`                          | Sets the base URI (last-writer-wins)  |

Defaults are applied first, then `make()` options on top. Last-writer-wins options can be overridden per client; additive options accumulate across defaults and `make()` calls.

### Custom client creator

Pass a custom closure to control how `HttpClient` is instantiated:

```php
$factory = new ClientFactory(
    defaults: $defaults,
    clientCreator: function (HttpClientConfig $config): HttpClient {
        // Custom validation, decoration, etc.
        return new HttpClient(
            $config->transport,
            $config->responseHydrator,
            $config->middlewares,
            $config->errorResponseHandlers,
            $config->baseUri,
        );
    },
);
```

## Architecture

```
HttpClient::send(Request<T>)
    ├── builds RequestContext (PSR-7 request + name + factory)
    ├── MiddlewarePipeline (onion chain)
    │   ├── Middleware 1
    │   ├── Middleware 2
    │   └── Transport::send() (innermost)
    ├── error handling (4xx/5xx → ErrorResponseHandler → exception)
    └── if getResponseClass() is null → return null
        else → ResponseHydrator::hydrate() → T
```

## Development

```bash
composer install
vendor/bin/phpstan analyse    # static analysis at max level
vendor/bin/phpunit            # run tests
```

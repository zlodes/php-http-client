# Upgrading to 1.0

## Breaking change: `Transport::send()` takes options

```php
// Before
public function send(RequestInterface $request): ResponseInterface;

// After
public function send(RequestInterface $request, TransportOptions $options): ResponseInterface;
```

Every `Transport` implementation must accept the second argument. Apply the options you support and document the ones you ignore.

`TransportOptions` holds `timeout` and `connectTimeout`, in seconds, both nullable. `null` means "not set" — leave the transport's own default in place. It never means "no limit".

```php
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Zlodes\Http\Client\Contract\Transport;
use Zlodes\Http\Client\TransportOptions;

final readonly class MyTransport implements Transport
{
    public function send(RequestInterface $request, TransportOptions $options): ResponseInterface
    {
        $timeout = $options->timeout ?? 30;

        // ...
    }
}
```

## Test doubles

Anonymous transports and mocks need the new parameter. Closures passed to `withArgs()` keep working, because an extra argument is ignored. A single `with()` matcher needs a second one:

```php
// Before
$transport->shouldReceive('send')->once()->with(Mockery::on($matcher));

// After
$transport->shouldReceive('send')->once()->with(Mockery::on($matcher), Mockery::any());
```

## `RequestContext`

`RequestContext` now carries `transportOptions`. The constructor parameter is optional and defaults to nothing set, so existing `new RequestContext(...)` calls keep working. `withHttpRequest()` and `withFreshHttpRequest()` keep the options, so a retry or token refresh does not drop the timeout.

## Timeouts

Set them with `WithTimeout` on a `ClientFactory`, with `TimeoutMiddleware` directly, or per endpoint by implementing `HasTransportOptions`. Precedence is request > client > global default.

`Psr18Transport` ignores `TransportOptions`. PSR-18 has no per-request options; configure timeouts on the wrapped client.

`GuzzleTransport` applies them. It requires `guzzlehttp/guzzle`, which this library now depends on.

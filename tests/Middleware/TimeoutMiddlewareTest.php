<?php

declare(strict_types=1);

namespace Zlodes\Http\Client\Tests\Middleware;

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Zlodes\Http\Client\Contract\RequestHandler;
use Zlodes\Http\Client\Middleware\TimeoutMiddleware;
use Zlodes\Http\Client\RequestContext;
use Zlodes\Http\Client\TransportOptions;

final class TimeoutMiddlewareTest extends TestCase
{
    public function testMergesTimeoutsIntoContext(): void
    {
        $captured = null;

        $next = new class ($captured) implements RequestHandler {
            public function __construct(private ?TransportOptions &$captured)
            {
            }

            public function handle(RequestContext $context): ResponseInterface
            {
                $this->captured = $context->transportOptions;

                return new Response(200);
            }
        };

        $middleware = new TimeoutMiddleware(timeout: 5, connectTimeout: 2);
        $middleware->process($this->context(TransportOptions::none()), $next);

        self::assertNotNull($captured);
        self::assertSame(5, $captured->timeout);
        self::assertSame(2, $captured->connectTimeout);
    }

    public function testNullDoesNotOverwriteSetValue(): void
    {
        $captured = null;

        $next = new class ($captured) implements RequestHandler {
            public function __construct(private ?TransportOptions &$captured)
            {
            }

            public function handle(RequestContext $context): ResponseInterface
            {
                $this->captured = $context->transportOptions;

                return new Response(200);
            }
        };

        $middleware = new TimeoutMiddleware(timeout: 5);
        $middleware->process($this->context(new TransportOptions(timeout: 10, connectTimeout: 3)), $next);

        self::assertNotNull($captured);
        self::assertSame(5, $captured->timeout);
        self::assertSame(3, $captured->connectTimeout);
    }

    public function testKeepsRequestAndName(): void
    {
        $captured = null;

        $next = new class ($captured) implements RequestHandler {
            public function __construct(private ?RequestContext &$captured)
            {
            }

            public function handle(RequestContext $context): ResponseInterface
            {
                $this->captured = $context;

                return new Response(200);
            }
        };

        $context = $this->context(TransportOptions::none());
        (new TimeoutMiddleware(timeout: 1))->process($context, $next);

        self::assertNotNull($captured);
        self::assertSame($context->httpRequest, $captured->httpRequest);
        self::assertSame('test', $captured->requestName);
    }

    private function context(TransportOptions $options): RequestContext
    {
        $request = new Request('GET', 'https://example.com');

        return new RequestContext(
            httpRequest: $request,
            requestName: 'test',
            requestFactory: fn (): RequestInterface => $request,
            transportOptions: $options,
        );
    }
}

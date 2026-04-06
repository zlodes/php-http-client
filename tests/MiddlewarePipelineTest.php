<?php

declare(strict_types=1);

namespace Zlodes\Http\Client\Tests;

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Zlodes\Http\Client\Contract\Middleware;
use Zlodes\Http\Client\Contract\RequestHandler;
use Zlodes\Http\Client\Contract\Transport;
use Zlodes\Http\Client\MiddlewarePipeline;
use Zlodes\Http\Client\RequestContext;

final class MiddlewarePipelineTest extends TestCase
{
    public function testNoMiddlewaresCallsTransportDirectly(): void
    {
        $expectedResponse = new Response(200);

        $transport = new class ($expectedResponse) implements Transport {
            public function __construct(private readonly ResponseInterface $response)
            {
            }

            public function send(RequestInterface $request): ResponseInterface
            {
                return $this->response;
            }
        };

        $pipeline = new MiddlewarePipeline($transport);
        $context = $this->createContext();

        $response = $pipeline->handle($context);

        self::assertSame($expectedResponse, $response);
    }

    public function testSingleMiddlewareWrapsTransport(): void
    {
        $transport = new class implements Transport {
            public function send(RequestInterface $request): ResponseInterface
            {
                return new Response(200, ['X-Transport' => 'true']);
            }
        };

        $middleware = new class implements Middleware {
            public function process(RequestContext $context, RequestHandler $next): ResponseInterface
            {
                $response = $next->handle($context);

                return $response->withHeader('X-Middleware', 'true');
            }
        };

        $pipeline = new MiddlewarePipeline($transport, [$middleware]);
        $response = $pipeline->handle($this->createContext());

        self::assertSame('true', $response->getHeaderLine('X-Transport'));
        self::assertSame('true', $response->getHeaderLine('X-Middleware'));
    }

    public function testMiddlewareExecutionOrder(): void
    {
        $order = [];

        $transport = new class implements Transport {
            public function send(RequestInterface $request): ResponseInterface
            {
                return new Response(200);
            }
        };

        $first = new class ($order) implements Middleware {
            /** @param list<string> $order */
            public function __construct(private array &$order)
            {
            }

            public function process(RequestContext $context, RequestHandler $next): ResponseInterface
            {
                $this->order[] = 'first:before';
                $response = $next->handle($context);
                $this->order[] = 'first:after';

                return $response;
            }
        };

        $second = new class ($order) implements Middleware {
            /** @param list<string> $order */
            public function __construct(private array &$order)
            {
            }

            public function process(RequestContext $context, RequestHandler $next): ResponseInterface
            {
                $this->order[] = 'second:before';
                $response = $next->handle($context);
                $this->order[] = 'second:after';

                return $response;
            }
        };

        $pipeline = new MiddlewarePipeline($transport, [$first, $second]);
        $pipeline->handle($this->createContext());

        self::assertSame(['first:before', 'second:before', 'second:after', 'first:after'], $order);
    }

    public function testMiddlewareCanModifyRequest(): void
    {
        $capturedMethod = null;

        $transport = new class ($capturedMethod) implements Transport {
            public function __construct(private ?string &$capturedMethod)
            {
            }

            public function send(RequestInterface $request): ResponseInterface
            {
                $this->capturedMethod = $request->getMethod();

                return new Response(200);
            }
        };

        $middleware = new class implements Middleware {
            public function process(RequestContext $context, RequestHandler $next): ResponseInterface
            {
                $modifiedRequest = $context->httpRequest->withMethod('POST');

                return $next->handle($context->withHttpRequest($modifiedRequest));
            }
        };

        $pipeline = new MiddlewarePipeline($transport, [$middleware]);
        $pipeline->handle($this->createContext());

        self::assertSame('POST', $capturedMethod);
    }

    private function createContext(): RequestContext
    {
        $request = new Request('GET', 'https://example.com');

        return new RequestContext(
            httpRequest: $request,
            requestName: 'test',
            requestFactory: fn () => $request,
        );
    }
}

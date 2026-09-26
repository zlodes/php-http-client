<?php

declare(strict_types=1);

namespace Zlodes\Http\Client\Tests\Middleware;

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Zlodes\Http\Client\Contract\RequestHandler;
use Zlodes\Http\Client\Middleware\HeaderMiddleware;
use Zlodes\Http\Client\RequestContext;

final class HeaderMiddlewareTest extends TestCase
{
    public function testSetsHeaderWhenAbsent(): void
    {
        $captured = null;

        $next = new class ($captured) implements RequestHandler {
            public function __construct(private ?RequestInterface &$captured)
            {
            }

            public function handle(RequestContext $context): ResponseInterface
            {
                $this->captured = $context->httpRequest;

                return new Response(200);
            }
        };

        (new HeaderMiddleware('X-Tenant', 'acme'))
            ->process($this->context(new Request('GET', 'https://example.com')), $next);

        self::assertNotNull($captured);
        self::assertSame(['acme'], $captured->getHeader('X-Tenant'));
    }

    public function testLeavesExistingHeader(): void
    {
        $captured = null;

        $next = new class ($captured) implements RequestHandler {
            public function __construct(private ?RequestInterface &$captured)
            {
            }

            public function handle(RequestContext $context): ResponseInterface
            {
                $this->captured = $context->httpRequest;

                return new Response(200);
            }
        };

        $request = new Request('GET', 'https://example.com', ['user-agent' => 'caller/1.0']);
        (new HeaderMiddleware('User-Agent', 'library/1.0'))->process($this->context($request), $next);

        self::assertNotNull($captured);
        self::assertSame($request, $captured);
        self::assertSame(['caller/1.0'], $captured->getHeader('User-Agent'));
    }

    public function testPassesResponseThrough(): void
    {
        $expected = new Response(201, ['X-Test' => 'yes'], 'body');

        $next = new class ($expected) implements RequestHandler {
            public function __construct(private ResponseInterface $response)
            {
            }

            public function handle(RequestContext $context): ResponseInterface
            {
                return $this->response;
            }
        };

        $actual = (new HeaderMiddleware('X-Tenant', 'acme'))
            ->process($this->context(new Request('GET', 'https://example.com')), $next);

        self::assertSame($expected, $actual);
    }

    public function testEmptyNameIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Header name must be non-empty.');

        new HeaderMiddleware('', 'acme');
    }

    public function testBlankNameIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Header name must be non-empty.');

        new HeaderMiddleware('   ', 'acme');
    }

    private function context(RequestInterface $request): RequestContext
    {
        return new RequestContext(
            httpRequest: $request,
            requestName: 'test',
            requestFactory: fn (): RequestInterface => $request,
        );
    }
}

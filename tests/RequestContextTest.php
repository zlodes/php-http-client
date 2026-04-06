<?php

declare(strict_types=1);

namespace Zlodes\Http\Client\Tests;

use GuzzleHttp\Psr7\Request;
use PHPUnit\Framework\TestCase;
use Zlodes\Http\Client\RequestContext;

final class RequestContextTest extends TestCase
{
    public function testWithHttpRequestReturnsNewInstance(): void
    {
        $original = new Request('GET', 'https://example.com/original');
        $replacement = new Request('POST', 'https://example.com/replacement');

        $context = new RequestContext(
            httpRequest: $original,
            requestName: 'test',
            requestFactory: fn () => $original,
        );

        $newContext = $context->withHttpRequest($replacement);

        self::assertNotSame($context, $newContext);
        self::assertSame($original, $context->httpRequest);
        self::assertSame($replacement, $newContext->httpRequest);
        self::assertSame('test', $newContext->requestName);
    }

    public function testWithFreshHttpRequestCallsFactory(): void
    {
        $initial = new Request('GET', 'https://example.com/initial');
        $fresh = new Request('GET', 'https://example.com/fresh');

        $context = new RequestContext(
            httpRequest: $initial,
            requestName: 'test',
            requestFactory: fn () => $fresh,
        );

        $newContext = $context->withFreshHttpRequest();

        self::assertNotSame($context, $newContext);
        self::assertSame($initial, $context->httpRequest);
        self::assertSame($fresh, $newContext->httpRequest);
    }
}

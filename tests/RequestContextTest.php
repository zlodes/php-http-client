<?php

declare(strict_types=1);

namespace Zlodes\Http\Client\Tests;

use GuzzleHttp\Psr7\Request;
use PHPUnit\Framework\TestCase;
use Zlodes\Http\Client\RequestContext;
use Zlodes\Http\Client\TransportOptions;

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

    public function testCopiesKeepTransportOptions(): void
    {
        $request = new Request('GET', 'https://example.com/original');
        $options = new TransportOptions(timeout: 10, connectTimeout: 3);

        $context = new RequestContext(
            httpRequest: $request,
            requestName: 'test',
            requestFactory: fn () => new Request('GET', 'https://example.com/fresh'),
            transportOptions: $options,
        );

        $withRequest = $context->withHttpRequest(new Request('POST', 'https://example.com/other'));
        $withFresh = $context->withFreshHttpRequest();
        $withOptions = $context->withTransportOptions(new TransportOptions(timeout: 1));

        self::assertSame($options, $withRequest->transportOptions);
        self::assertSame($options, $withFresh->transportOptions);
        self::assertSame(1, $withOptions->transportOptions->timeout);
        self::assertSame('test', $withOptions->requestName);
        self::assertSame($request, $withOptions->httpRequest);
    }

    public function testTransportOptionsDefaultToNone(): void
    {
        $context = new RequestContext(
            httpRequest: new Request('GET', 'https://example.com'),
            requestName: 'test',
            requestFactory: fn () => new Request('GET', 'https://example.com'),
        );

        self::assertEquals(TransportOptions::none(), $context->transportOptions);
    }
}

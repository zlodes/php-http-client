<?php

declare(strict_types=1);

namespace Zlodes\Http\Client\Tests\Transport;

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Zlodes\Http\Client\Exception\TransportException;
use Zlodes\Http\Client\Transport\Psr18Transport;
use Zlodes\Http\Client\TransportOptions;

final class Psr18TransportTest extends TestCase
{
    public function testDelegatesToPsr18Client(): void
    {
        $expectedResponse = new Response(200, [], 'ok');
        $request = new Request('GET', 'https://example.com');

        $client = new class ($expectedResponse) implements ClientInterface {
            public function __construct(private readonly ResponseInterface $response)
            {
            }

            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                return $this->response;
            }
        };

        $transport = new Psr18Transport($client);
        $response = $transport->send($request, new TransportOptions(timeout: 5, connectTimeout: 1));

        self::assertSame($expectedResponse, $response);
    }

    public function testWrapsClientExceptionIntoTransportException(): void
    {
        $clientException = new class ('Connection refused') extends \RuntimeException implements ClientExceptionInterface {
        };

        $client = new class ($clientException) implements ClientInterface {
            public function __construct(private readonly ClientExceptionInterface $exception)
            {
            }

            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                throw $this->exception;
            }
        };

        $transport = new Psr18Transport($client);

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Connection refused');

        $transport->send(new Request('GET', 'https://example.com'), TransportOptions::none());
    }

    public function testTransportExceptionPreservesPrevious(): void
    {
        $clientException = new class ('timeout') extends \RuntimeException implements ClientExceptionInterface {
        };

        $client = new class ($clientException) implements ClientInterface {
            public function __construct(private readonly ClientExceptionInterface $exception)
            {
            }

            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                throw $this->exception;
            }
        };

        $transport = new Psr18Transport($client);

        try {
            $transport->send(new Request('GET', 'https://example.com'), TransportOptions::none());
            self::fail('Expected TransportException');
        } catch (TransportException $e) {
            self::assertSame($clientException, $e->getPrevious());
        }
    }
}

<?php

declare(strict_types=1);

namespace Zlodes\Http\Client\Tests\Contract;

use GuzzleHttp\Psr7\Request as GuzzleRequest;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Zlodes\Http\Client\CollectionResponse;
use Zlodes\Http\Client\Contract\CollectionRequest;
use Zlodes\Http\Client\Contract\Request;
use Zlodes\Http\Client\Contract\Response as ResponseContract;
use Zlodes\Http\Client\Contract\ResponseHydrator;
use Zlodes\Http\Client\Contract\Transport;
use Zlodes\Http\Client\HttpClient;
use Zlodes\Http\Client\Tests\Fixture\FakeDto;

final class CollectionRequestTest extends TestCase
{
    public function testHttpClientReturnsCollectionResponseFromCollectionRequest(): void
    {
        $transport = new class implements Transport {
            public function send(RequestInterface $request): ResponseInterface
            {
                return new GuzzleResponse(
                    200,
                    [],
                    '[{"id":1,"name":"Alice"},{"id":2,"name":"Bob"}]',
                );
            }
        };

        $hydrator = new class implements ResponseHydrator {
            public function hydrate(ResponseInterface $response, Request $request): ResponseContract
            {
                if ($request instanceof CollectionRequest) {
                    /** @var list<array{id: int, name: string}> $decoded */
                    $decoded = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);

                    $itemClass = $request->getItemClass();
                    $items = [];

                    foreach ($decoded as $row) {
                        $items[] = new $itemClass($row['id'], $row['name']);
                    }

                    return new CollectionResponse($items);
                }

                Assert::fail('Expected CollectionRequest branch to be taken.');
            }
        };

        /** @var CollectionRequest<FakeDto> $request */
        $request = new class implements CollectionRequest {
            public function getName(): string
            {
                return 'users.list';
            }

            public function buildRequest(): RequestInterface
            {
                return new GuzzleRequest('GET', 'https://example.com/users');
            }

            public function getResponseClass(): string
            {
                return CollectionResponse::class;
            }

            public function getItemClass(): string
            {
                return FakeDto::class;
            }
        };

        $client = new HttpClient($transport, $hydrator);
        $result = $client->send($request);

        self::assertInstanceOf(CollectionResponse::class, $result);
        self::assertCount(2, $result);
        self::assertContainsOnlyInstancesOf(FakeDto::class, $result->items);
        self::assertSame(1, $result->items[0]->id);
        self::assertSame('Alice', $result->items[0]->name);
        self::assertSame(2, $result->items[1]->id);
        self::assertSame('Bob', $result->items[1]->name);

        $iterated = [];
        foreach ($result as $item) {
            $iterated[] = $item;
        }

        self::assertSame($result->items, $iterated);
    }
}

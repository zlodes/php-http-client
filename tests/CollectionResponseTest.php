<?php

declare(strict_types=1);

namespace Zlodes\Http\Client\Tests;

use GuzzleHttp\Psr7\Request as GuzzleRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Zlodes\Http\Client\CollectionResponse;
use Zlodes\Http\Client\Contract\CollectionRequest;
use Zlodes\Http\Client\Exception\HydrationException;
use Zlodes\Http\Client\Tests\Fixture\FakeDto;

final class CollectionResponseTest extends TestCase
{
    public function testEmptyCollection(): void
    {
        $response = new CollectionResponse([]);

        self::assertCount(0, $response);
        self::assertSame([], $response->items);
        self::assertSame([], iterator_to_array($response));
    }

    public function testPopulatedCollection(): void
    {
        $items = [
            new FakeDto(id: 1, name: 'Alice'),
            new FakeDto(id: 2, name: 'Bob'),
            new FakeDto(id: 3, name: 'Charlie'),
        ];

        $response = new CollectionResponse($items);

        self::assertCount(3, $response);
        self::assertSame($items, $response->items);

        $iterated = [];
        foreach ($response as $item) {
            $iterated[] = $item;
        }

        self::assertSame($items, $iterated);
    }

    public function testFromHydratedBuildsTypedCollection(): void
    {
        $items = [
            new FakeDto(id: 1, name: 'Alice'),
            new FakeDto(id: 2, name: 'Bob'),
        ];

        $response = CollectionResponse::fromHydrated($this->fakeDtoRequest(), $items);

        self::assertCount(2, $response);
        self::assertSame($items, $response->items);
    }

    public function testFromHydratedThrowsWhenPayloadIsNotArray(): void
    {
        $this->expectException(HydrationException::class);
        $this->expectExceptionMessage(sprintf('Expected array of "%s", got "string".', FakeDto::class));

        CollectionResponse::fromHydrated($this->fakeDtoRequest(), 'not an array');
    }

    public function testFromHydratedThrowsWhenItemTypeMismatches(): void
    {
        $this->expectException(HydrationException::class);
        $this->expectExceptionMessage(sprintf('Expected instance of "%s", got "string".', FakeDto::class));

        CollectionResponse::fromHydrated($this->fakeDtoRequest(), [new FakeDto(1, 'Alice'), 'not a dto']);
    }

    /**
     * @return CollectionRequest<FakeDto>
     */
    private function fakeDtoRequest(): CollectionRequest
    {
        return new class implements CollectionRequest {
            public function getName(): string
            {
                return 'test.list';
            }

            public function buildRequest(): RequestInterface
            {
                return new GuzzleRequest('GET', 'https://example.com');
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
    }
}

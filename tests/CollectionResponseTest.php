<?php

declare(strict_types=1);

namespace Zlodes\Http\Client\Tests;

use PHPUnit\Framework\TestCase;
use Zlodes\Http\Client\CollectionResponse;
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
}

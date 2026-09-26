<?php

declare(strict_types=1);

namespace Zlodes\Http\Client\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Zlodes\Http\Client\TransportOptions;

final class TransportOptionsTest extends TestCase
{
    public function testDefaultsAreUnset(): void
    {
        $options = new TransportOptions();

        self::assertNull($options->timeout);
        self::assertNull($options->connectTimeout);
    }

    public function testNoneMatchesEmptyConstructor(): void
    {
        self::assertEquals(new TransportOptions(), TransportOptions::none());
    }

    public function testAcceptsPositiveValues(): void
    {
        $options = new TransportOptions(timeout: 5, connectTimeout: 1.5);

        self::assertSame(5, $options->timeout);
        self::assertSame(1.5, $options->connectTimeout);
    }

    #[DataProvider('nonPositiveValues')]
    public function testRejectsNonPositiveTimeout(int|float $value): void
    {
        $this->expectException(InvalidArgumentException::class);

        new TransportOptions(timeout: $value);
    }

    #[DataProvider('nonPositiveValues')]
    public function testRejectsNonPositiveConnectTimeout(int|float $value): void
    {
        $this->expectException(InvalidArgumentException::class);

        new TransportOptions(connectTimeout: $value);
    }

    /**
     * @return iterable<string, array{int|float}>
     */
    public static function nonPositiveValues(): iterable
    {
        yield 'zero int' => [0];
        yield 'zero float' => [0.0];
        yield 'negative int' => [-1];
        yield 'negative float' => [-0.5];
    }

    public function testMergeOverrideWinsOnSetValues(): void
    {
        $base = new TransportOptions(timeout: 10, connectTimeout: 3);
        $override = new TransportOptions(timeout: 5, connectTimeout: 1);

        $merged = $base->merge($override);

        self::assertSame(5, $merged->timeout);
        self::assertSame(1, $merged->connectTimeout);
    }

    public function testMergeKeepsBaseWhenOverrideIsNull(): void
    {
        $base = new TransportOptions(timeout: 10, connectTimeout: 3);

        $merged = $base->merge(new TransportOptions(timeout: 5));

        self::assertSame(5, $merged->timeout);
        self::assertSame(3, $merged->connectTimeout);
    }

    public function testMergeDoesNotMutateEitherSide(): void
    {
        $base = new TransportOptions(timeout: 10, connectTimeout: 3);
        $override = new TransportOptions(connectTimeout: 1);

        $base->merge($override);

        self::assertSame(10, $base->timeout);
        self::assertSame(3, $base->connectTimeout);
        self::assertNull($override->timeout);
    }
}

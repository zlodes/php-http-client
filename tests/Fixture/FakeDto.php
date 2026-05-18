<?php

declare(strict_types=1);

namespace Zlodes\Http\Client\Tests\Fixture;

final readonly class FakeDto
{
    public function __construct(
        public int $id,
        public string $name,
    ) {
    }
}

<?php

declare(strict_types=1);

namespace Zlodes\Http\Client;

use InvalidArgumentException;

final readonly class TransportOptions
{
    /**
     * @param positive-int|float|null $timeout Total seconds for one attempt (connect + transfer)
     * @param positive-int|float|null $connectTimeout Seconds to establish the connection
     */
    public function __construct(
        public int|float|null $timeout = null,
        public int|float|null $connectTimeout = null,
    ) {
        self::assertPositive($timeout, 'timeout');
        self::assertPositive($connectTimeout, 'connectTimeout');
    }

    public static function none(): self
    {
        return new self();
    }

    /**
     * Values set in $override win; null means "not set here, keep mine".
     */
    public function merge(self $override): self
    {
        return new self(
            timeout: $override->timeout ?? $this->timeout,
            connectTimeout: $override->connectTimeout ?? $this->connectTimeout,
        );
    }

    private static function assertPositive(int|float|null $value, string $name): void
    {
        if ($value !== null && $value <= 0) {
            throw new InvalidArgumentException(sprintf('%s must be positive, got %s.', $name, $value));
        }
    }
}

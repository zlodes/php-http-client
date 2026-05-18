<?php

declare(strict_types=1);

namespace Zlodes\Http\Client;

use ArrayIterator;
use Countable;
use Iterator;
use IteratorAggregate;
use Zlodes\Http\Client\Contract\Response;

/**
 * @template TItem
 *
 * @implements IteratorAggregate<int, TItem>
 */
readonly class CollectionResponse implements Response, IteratorAggregate, Countable
{
    /**
     * @param list<TItem> $items
     */
    public function __construct(public array $items)
    {
    }

    /**
     * @return Iterator<int, TItem>
     */
    public function getIterator(): Iterator
    {
        return new ArrayIterator($this->items);
    }

    public function count(): int
    {
        return count($this->items);
    }
}

<?php

declare(strict_types=1);

namespace Zlodes\Http\Client;

use ArrayIterator;
use Countable;
use Iterator;
use IteratorAggregate;
use Zlodes\Http\Client\Contract\CollectionRequest;
use Zlodes\Http\Client\Contract\Response;
use Zlodes\Http\Client\Exception\HydrationException;

/**
 * @template-covariant TItem
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
     * Build a typed CollectionResponse from a hydrator payload of unknown shape.
     *
     * Validates that $items is an array and each element is an instance of the request's item class,
     * then returns a typed CollectionResponse<TItem>.
     *
     * @template THydratedItem
     *
     * @param CollectionRequest<THydratedItem> $request
     *
     * @return self<THydratedItem>
     *
     * @throws HydrationException
     */
    public static function fromHydrated(CollectionRequest $request, mixed $items): self
    {
        if (! is_array($items)) {
            throw new HydrationException(sprintf(
                'Expected array of "%s", got "%s".',
                $request->getItemClass(),
                get_debug_type($items),
            ));
        }

        $itemClass = $request->getItemClass();
        $list = [];

        foreach ($items as $item) {
            if (! $item instanceof $itemClass) {
                throw new HydrationException(sprintf(
                    'Expected instance of "%s", got "%s".',
                    $itemClass,
                    get_debug_type($item),
                ));
            }

            $list[] = $item;
        }

        return new self($list);
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

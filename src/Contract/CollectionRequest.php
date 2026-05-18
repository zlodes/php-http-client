<?php

declare(strict_types=1);

namespace Zlodes\Http\Client\Contract;

use Zlodes\Http\Client\CollectionResponse;

/**
 * @template TItem
 *
 * @extends Request<CollectionResponse<TItem>>
 */
interface CollectionRequest extends Request
{
    /**
     * @return class-string<TItem>
     */
    public function getItemClass(): string;
}

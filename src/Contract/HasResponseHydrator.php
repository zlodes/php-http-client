<?php

declare(strict_types=1);

namespace Zlodes\Http\Client\Contract;

/**
 * Implement on a {@see Request} to override the client's default hydrator for this request.
 */
interface HasResponseHydrator
{
    public function getResponseHydrator(): ResponseHydrator;
}

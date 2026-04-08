<?php

declare(strict_types=1);

namespace Zlodes\Http\Client\Contract;

/**
 * Implement on a {@see Request} to provide request-level error handlers.
 *
 * These handlers are checked before client-level handlers.
 */
interface HasErrorResponseHandlers
{
    /**
     * @return list<ErrorResponseHandler>
     */
    public function getErrorResponseHandlers(): array;
}

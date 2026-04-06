<?php

declare(strict_types=1);

namespace Zlodes\Http\Client\Contract;

interface HasErrorResponseHandlers
{
    /**
     * @return list<ErrorResponseHandler>
     */
    public function getErrorResponseHandlers(): array;
}

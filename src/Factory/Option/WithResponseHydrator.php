<?php

declare(strict_types=1);

namespace Zlodes\Http\Client\Factory\Option;

use Zlodes\Http\Client\Contract\ResponseHydrator;
use Zlodes\Http\Client\Factory\HttpClientConfig;
use Zlodes\Http\Client\Factory\Option;

final readonly class WithResponseHydrator implements Option
{
    public function __construct(
        private ResponseHydrator $responseHydrator,
    ) {
    }

    public function apply(HttpClientConfig $config): void
    {
        $config->responseHydrator = $this->responseHydrator;
    }
}

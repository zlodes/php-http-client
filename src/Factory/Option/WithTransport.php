<?php

declare(strict_types=1);

namespace Zlodes\Http\Client\Factory\Option;

use Zlodes\Http\Client\Contract\Transport;
use Zlodes\Http\Client\Factory\HttpClientConfig;
use Zlodes\Http\Client\Factory\Option;

final readonly class WithTransport implements Option
{
    public function __construct(
        private Transport $transport,
    ) {
    }

    public function apply(HttpClientConfig $config): void
    {
        $config->transport = $this->transport;
    }
}

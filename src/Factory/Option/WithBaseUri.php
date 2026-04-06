<?php

declare(strict_types=1);

namespace Zlodes\Http\Client\Factory\Option;

use Psr\Http\Message\UriInterface;
use Zlodes\Http\Client\Factory\HttpClientConfig;
use Zlodes\Http\Client\Factory\Option;

final readonly class WithBaseUri implements Option
{
    public function __construct(
        private UriInterface $uri,
    ) {
    }

    public function apply(HttpClientConfig $config): void
    {
        $config->baseUri = $this->uri;
    }
}

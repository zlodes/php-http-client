<?php

declare(strict_types=1);

namespace Zlodes\Http\Client\Factory;

interface Option
{
    public function apply(HttpClientConfig $config): void;
}

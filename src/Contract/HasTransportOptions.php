<?php

declare(strict_types=1);

namespace Zlodes\Http\Client\Contract;

use Zlodes\Http\Client\TransportOptions;

interface HasTransportOptions
{
    public function getTransportOptions(): TransportOptions;
}

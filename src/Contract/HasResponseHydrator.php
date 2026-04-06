<?php

declare(strict_types=1);

namespace Zlodes\Http\Client\Contract;

interface HasResponseHydrator
{
    public function getResponseHydrator(): ResponseHydrator;
}

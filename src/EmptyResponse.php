<?php

declare(strict_types=1);

namespace Zlodes\Http\Client;

use Zlodes\Http\Client\Contract\Response;

final class EmptyResponse implements Response
{
    private static ?self $instance = null;

    private function __construct()
    {
    }

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }
}

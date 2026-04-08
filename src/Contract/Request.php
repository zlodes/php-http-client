<?php

declare(strict_types=1);

namespace Zlodes\Http\Client\Contract;

use Psr\Http\Message\RequestInterface;

/**
 * @template TResponse of Response
 */
interface Request
{
    public function getName(): string;

    public function buildRequest(): RequestInterface;

    /**
     * @return class-string<TResponse>
     */
    public function getResponseClass(): string;
}

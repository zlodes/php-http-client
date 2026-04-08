<?php

declare(strict_types=1);

namespace Zlodes\Http\Client\Contract;

use Psr\Http\Message\RequestInterface;

/**
 * Defines an HTTP request with an associated response type.
 *
 * Return null from {@see getResponseClass()} for fire-and-forget requests (e.g. DELETE)
 * that do not need response hydration — the client will return null instead.
 *
 * @template TResponse of Response
 */
interface Request
{
    /**
     * Unique name used for logging, metrics, and error messages.
     */
    public function getName(): string;

    /**
     * Build the underlying PSR-7 request.
     */
    public function buildRequest(): RequestInterface;

    /**
     * FQCN of the response DTO to hydrate, or null when no response body is expected.
     *
     * @return class-string<TResponse>|null
     */
    public function getResponseClass(): ?string;
}

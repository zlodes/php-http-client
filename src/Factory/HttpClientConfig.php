<?php

declare(strict_types=1);

namespace Zlodes\Http\Client\Factory;

use Psr\Http\Message\UriInterface;
use Zlodes\Http\Client\Contract\ErrorResponseHandler;
use Zlodes\Http\Client\Contract\Middleware;
use Zlodes\Http\Client\Contract\ResponseHydrator;
use Zlodes\Http\Client\Contract\Transport;

final class HttpClientConfig
{
    public ?Transport $transport = null;
    public ?ResponseHydrator $responseHydrator = null;
    /** @var list<Middleware> */
    public array $middlewares = [];
    /** @var list<ErrorResponseHandler> */
    public array $errorResponseHandlers = [];
    public ?UriInterface $baseUri = null;
}

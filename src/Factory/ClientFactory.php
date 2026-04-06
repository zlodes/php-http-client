<?php

declare(strict_types=1);

namespace Zlodes\Http\Client\Factory;

use Closure;
use LogicException;
use Zlodes\Http\Client\HttpClient;

final readonly class ClientFactory
{
    /** @var Closure(HttpClientConfig): HttpClient */
    private Closure $clientCreator;

    /**
     * @param list<Option> $defaults
     * @param (Closure(HttpClientConfig): HttpClient)|null $clientCreator
     */
    public function __construct(
        private array $defaults = [],
        ?Closure $clientCreator = null,
    ) {
        $this->clientCreator = $clientCreator ?? static function (HttpClientConfig $config): HttpClient {
            if ($config->transport === null) {
                throw new LogicException('Transport is required to create an HttpClient');
            }

            if ($config->responseHydrator === null) {
                throw new LogicException('ResponseHydrator is required to create an HttpClient');
            }

            return new HttpClient(
                $config->transport,
                $config->responseHydrator,
                $config->middlewares,
                $config->errorResponseHandlers,
                $config->baseUri,
            );
        };
    }

    public function make(Option ...$options): HttpClient
    {
        $config = new HttpClientConfig();

        foreach ($this->defaults as $default) {
            $default->apply($config);
        }

        foreach ($options as $option) {
            $option->apply($config);
        }

        return ($this->clientCreator)($config);
    }
}

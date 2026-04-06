<?php

declare(strict_types=1);

namespace Zlodes\Http\Client;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;
use Throwable;
use Zlodes\Http\Client\Contract\Client;
use Zlodes\Http\Client\Contract\ErrorResponseHandler;
use Zlodes\Http\Client\Contract\HasErrorResponseHandlers;
use Zlodes\Http\Client\Contract\HasResponseHydrator;
use Zlodes\Http\Client\Contract\Middleware;
use Zlodes\Http\Client\Contract\Request;
use Zlodes\Http\Client\Contract\Response;
use Zlodes\Http\Client\Contract\ResponseHydrator;
use Zlodes\Http\Client\Contract\Transport;
use Zlodes\Http\Client\Exception\HydrationException;
use Zlodes\Http\Client\Exception\HttpClientException;
use Zlodes\Http\Client\Exception\HttpErrorException;

final readonly class HttpClient implements Client
{
    private MiddlewarePipeline $pipeline;

    /**
     * @param list<Middleware> $middlewares
     * @param list<ErrorResponseHandler> $errorResponseHandlers
     */
    public function __construct(
        Transport $transport,
        private ResponseHydrator $responseHydrator,
        array $middlewares = [],
        private array $errorResponseHandlers = [],
        private ?UriInterface $baseUri = null,
    ) {
        $this->pipeline = new MiddlewarePipeline($transport, $middlewares);
    }

    /**
     * @template TResponse of Response
     *
     * @param Request<TResponse> $request
     *
     * @return TResponse
     *
     * @throws HttpClientException
     */
    public function send(Request $request): Response
    {
        $httpRequest = $this->applyBaseUri($request->buildRequest());

        $context = new RequestContext(
            httpRequest: $httpRequest,
            requestName: $request->getName(),
            requestFactory: fn (): RequestInterface => $this->applyBaseUri($request->buildRequest()),
        );

        $httpResponse = $this->pipeline->handle($context);

        if ($httpResponse->getStatusCode() >= 400) {
            $this->throwForErrorResponse($httpResponse, $request);
        }

        $responseHydrator = $request instanceof HasResponseHydrator
            ? $request->getResponseHydrator()
            : $this->responseHydrator;

        try {
            return $responseHydrator->hydrate($httpResponse, $request);
        } catch (HydrationException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new HydrationException($e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    /**
     * @template TResponse of Response
     *
     * @param Request<TResponse> $request
     *
     * @return list<ErrorResponseHandler>
     */
    private function getErrorResponseHandlers(Request $request): array
    {
        if (! $request instanceof HasErrorResponseHandlers) {
            return $this->errorResponseHandlers;
        }

        return [
            ...$request->getErrorResponseHandlers(),
            ...$this->errorResponseHandlers,
        ];
    }

    /**
     * @template TResponse of Response
     *
     * @param Request<TResponse> $request
     */
    private function throwForErrorResponse(ResponseInterface $response, Request $request): never
    {
        foreach ($this->getErrorResponseHandlers($request) as $handler) {
            if (! $handler->supports($response, $request)) {
                continue;
            }

            try {
                $exception = $handler->toException($response, $request);
            } catch (HttpClientException $e) {
                throw $e;
            } catch (Throwable $e) {
                throw new HttpErrorException(
                    request: $request,
                    response: $response,
                    message: sprintf(
                        'Failed to handle HTTP error response for "%s" with status %d.',
                        $request->getName(),
                        $response->getStatusCode(),
                    ),
                    previous: $e,
                );
            }

            throw $exception;
        }

        throw new HttpErrorException($request, $response);
    }

    private function applyBaseUri(RequestInterface $httpRequest): RequestInterface
    {
        if ($this->baseUri === null) {
            return $httpRequest;
        }

        $uri = $httpRequest->getUri();

        if ($uri->getHost() !== '') {
            return $httpRequest;
        }

        $resolvedUri = $this->baseUri->withPath(
            rtrim($this->baseUri->getPath(), '/') . '/' . ltrim($uri->getPath(), '/'),
        );

        if ($uri->getQuery() !== '') {
            $resolvedUri = $resolvedUri->withQuery($uri->getQuery());
        }

        if ($uri->getFragment() !== '') {
            $resolvedUri = $resolvedUri->withFragment($uri->getFragment());
        }

        return $httpRequest->withUri($resolvedUri);
    }
}

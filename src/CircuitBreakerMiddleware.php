<?php declare(strict_types=1);
/*
 * This file is part of ksaveras/guzzle-circuit-breaker-middleware.
 *
 * (c) Ksaveras Sakys <xawiers@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Ksaveras\GuzzleCircuitBreakerMiddleware;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\PromiseInterface;
use Ksaveras\CircuitBreaker\CircuitBreaker;
use Ksaveras\CircuitBreaker\Exception\OpenCircuitException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

final class CircuitBreakerMiddleware
{
    private CircuitBreaker $circuitBreaker;

    public function __construct(CircuitBreaker $circuitBreaker)
    {
        $this->circuitBreaker = $circuitBreaker;
    }

    public function __invoke(callable $handler): callable
    {
        return function (RequestInterface $request, array $options) use (&$handler): PromiseInterface {
            if (!$this->circuitBreaker->isAvailable()) {
                return Create::rejectionFor(
                    new OpenCircuitException(sprintf('Open circuit "%s"', $this->circuitBreaker->getName()))
                );
            }

            return $handler($request, $options)
                ->then(
                    $this->handleSuccess($request, $options),
                    $this->handleFailure($request, $options)
                );
        };
    }

    private function handleSuccess(RequestInterface $request, array $options): callable
    {
        return function (ResponseInterface $response) {
            if ($response->getStatusCode() >= 500) {
                $this->circuitBreaker->failure();
            } else {
                $this->circuitBreaker->success();
            }

            return $response;
        };
    }

    private function handleFailure(RequestInterface $request, array $options): callable
    {
        return function (\Exception $reason): PromiseInterface {
            if ($reason instanceof ServerException || $reason instanceof ConnectException) {
                $this->circuitBreaker->failure();
            }

            return Create::rejectionFor($reason);
        };
    }
}

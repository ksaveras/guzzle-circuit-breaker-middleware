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
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Promise\RejectedPromise;
use Ksaveras\CircuitBreaker\CircuitBreakerInterface;
use Ksaveras\CircuitBreaker\Exception\OpenCircuitException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

final class CircuitBreakerMiddleware
{
    public function __construct(
        private readonly CircuitBreakerInterface $circuitBreaker
    ) {
    }

    public function __invoke(callable $handler): callable
    {
        return function (RequestInterface $request, array $options) use (&$handler): PromiseInterface {
            if (!$this->circuitBreaker->isAvailable()) {
                return new RejectedPromise(
                    new OpenCircuitException(sprintf('Open circuit "%s"', $this->circuitBreaker->getName()))
                );
            }

            return $handler($request, $options)
                ->then(
                    $this->handleSuccess(),
                    $this->handleFailure()
                );
        };
    }

    private function handleSuccess(): callable
    {
        return function (ResponseInterface $response): ResponseInterface {
            if (429 === $response->getStatusCode() || $response->getStatusCode() >= 500) {
                $this->circuitBreaker->recordRequestFailure($response);
            } else {
                $this->circuitBreaker->recordSuccess();
            }

            return $response;
        };
    }

    private function handleFailure(): callable
    {
        return function (\Exception $reason): PromiseInterface {
            if ($reason instanceof ConnectException) {
                $this->circuitBreaker->recordFailure();
            }

            return $reason instanceof PromiseInterface ? $reason : new RejectedPromise($reason);
        };
    }
}

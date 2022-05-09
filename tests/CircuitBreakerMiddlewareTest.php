<?php declare(strict_types=1);
/*
 * This file is part of ksaveras/guzzle-circuit-breaker-middleware.
 *
 * (c) Ksaveras Sakys <xawiers@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Ksaveras\GuzzleCircuitBreakerMiddleware\Tests;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\RejectedPromise;
use GuzzleHttp\Psr7\Response;
use Ksaveras\CircuitBreaker\CircuitBreaker;
use Ksaveras\CircuitBreaker\Exception\OpenCircuitException;
use Ksaveras\GuzzleCircuitBreakerMiddleware\CircuitBreakerMiddleware;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class CircuitBreakerMiddlewareTest extends TestCase
{
    /**
     * @var CircuitBreaker|MockObject
     */
    private MockObject $cbMock;

    private CircuitBreakerMiddleware $middleware;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cbMock = $this->createMock(CircuitBreaker::class);
        $this->cbMock->method('getName')->willReturn('Test');

        $this->middleware = new CircuitBreakerMiddleware($this->cbMock);
    }

    public function testInvokeWithSuccess(): void
    {
        $this->cbMock->expects(self::once())
            ->method('isAvailable')
            ->willReturn(true);
        $this->cbMock->expects(self::once())
            ->method('success');

        ($this->middleware)(function () {
            return new FulfilledPromise($this->createMock(ResponseInterface::class));
        })($this->createMock(RequestInterface::class), [])
            ->wait();
    }

    public function testOpenCircuitBreaker(): void
    {
        $this->expectException(OpenCircuitException::class);
        $this->expectExceptionMessage('Open circuit "Test"');

        $this->cbMock->expects(self::once())
            ->method('isAvailable')
            ->willReturn(false);
        $this->cbMock->expects(self::never())
            ->method('success');

        ($this->middleware)(function () {
            return new FulfilledPromise(new Response());
        })($this->createMock(RequestInterface::class), [])
            ->wait();
    }

    public function testHandleServerErrorResponseCode(): void
    {
        $this->cbMock->method('isAvailable')->willReturn(true);
        $this->cbMock->expects(self::never())->method('success');
        $this->cbMock->expects(self::once())->method('failure');

        ($this->middleware)(function () {
            return new FulfilledPromise(new Response(500));
        })($this->createMock(RequestInterface::class), [])
            ->wait();
    }

    public function testHandleServerException(): void
    {
        $this->expectException(ServerException::class);

        $this->cbMock->method('isAvailable')->willReturn(true);
        $this->cbMock->expects(self::never())->method('success');
        $this->cbMock->expects(self::once())->method('failure');

        $request = $this->createMock(RequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);

        ($this->middleware)(function () use ($request, $response) {
            return new RejectedPromise(
                new ServerException('Server exception', $request, $response)
            );
        })($request, [])
            ->wait();
    }

    public function testHandleConnectException(): void
    {
        $this->expectException(ConnectException::class);

        $this->cbMock->method('isAvailable')->willReturn(true);
        $this->cbMock->expects(self::never())->method('success');
        $this->cbMock->expects(self::once())->method('failure');

        $request = $this->createMock(RequestInterface::class);

        ($this->middleware)(function () use ($request) {
            return new RejectedPromise(
                new ConnectException('Server exception', $request)
            );
        })($request, [])
            ->wait();
    }
}

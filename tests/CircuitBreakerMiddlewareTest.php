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

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Ksaveras\CircuitBreaker\Circuit;
use Ksaveras\CircuitBreaker\CircuitBreaker;
use Ksaveras\CircuitBreaker\Exception\OpenCircuitException;
use Ksaveras\CircuitBreaker\HeaderPolicy\PolicyChain;
use Ksaveras\CircuitBreaker\HeaderPolicy\RateLimitPolicy;
use Ksaveras\CircuitBreaker\HeaderPolicy\RetryAfterPolicy;
use Ksaveras\CircuitBreaker\Policy\ConstantRetryPolicy;
use Ksaveras\CircuitBreaker\Storage\InMemoryStorage;
use Ksaveras\GuzzleCircuitBreakerMiddleware\CircuitBreakerMiddleware;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

final class CircuitBreakerMiddlewareTest extends TestCase
{
    private InMemoryStorage $storage;

    private CircuitBreaker $circuitBreaker;

    private CircuitBreakerMiddleware $middleware;

    protected function setUp(): void
    {
        parent::setUp();

        $this->storage = new InMemoryStorage();

        $this->circuitBreaker = new CircuitBreaker(
            'test',
            2,
            new ConstantRetryPolicy(60),
            $this->storage,
            new PolicyChain([
                new RetryAfterPolicy(),
                new RateLimitPolicy(),
            ]),
        );

        $this->middleware = new CircuitBreakerMiddleware($this->circuitBreaker);
    }

    public function testSuccessResponse(): void
    {
        $client = $this->createClient([
            new Response(200),
        ]);

        $client->get('/');

        self::assertNull($this->storage->fetch('test'));
    }

    public function testServerErrorResponse(): void
    {
        $client = $this->createClient([
            new Response(500),
            new Response(500),
        ]);

        try {
            $client->get('/');
        } catch (GuzzleException) {
        }

        self::assertTrue($this->circuitBreaker->isClosed());

        try {
            $client->get('/');
        } catch (GuzzleException) {
        }

        self::assertTrue($this->circuitBreaker->isOpen());
    }

    public function testResetAfterSuccessResponse(): void
    {
        $client = $this->createClient([
            new Response(500),
            new Response(200),
        ]);

        try {
            $client->get('/');
        } catch (GuzzleException) {
        }

        self::assertNotNull($this->storage->fetch('test'));

        $client->get('/');

        self::assertNull($this->storage->fetch('test'));
        self::assertTrue($this->circuitBreaker->isClosed());
    }

    #[DataProvider('provideFailingResponses')]
    public function testRetryAfterResponseHeaders(ResponseInterface $response): void
    {
        $client = $this->createClient([$response]);

        try {
            $client->get('/');
        } catch (GuzzleException) {
        }

        self::assertTrue($this->circuitBreaker->isOpen());
    }

    public static function provideFailingResponses(): iterable
    {
        return [
            '429 retry after' => [new Response(429, ['Retry-After' => '600'])],
            '503 retry after' => [new Response(503, ['Retry-After' => '600'])],
            '429 rate limit reached' => [
                new Response(429, [
                    'X-RateLimit-Reset' => '600',
                    'X-RateLimit-Remaining' => '0',
                ]),
            ],
        ];
    }

    public function testHandleConnectionException(): void
    {
        $client = $this->createClient([
            new ConnectException('Error Communicating with Server', new Request('GET', 'test')),
            new ConnectException('Error Communicating with Server', new Request('GET', 'test')),
        ]);

        try {
            $client->get('/');
        } catch (GuzzleException) {
        }

        self::assertNotNull($this->storage->fetch('test'));

        try {
            $client->get('/');
        } catch (GuzzleException) {
        }

        self::assertTrue($this->circuitBreaker->isOpen());
    }

    public function testThrowsOpenCircuitException(): void
    {
        $this->expectException(OpenCircuitException::class);

        $this->storage->save(new Circuit('test', 1, 600, 1, microtime(true)));

        $client = $this->createClient([
            new Response(500),
        ]);

        $client->get('/');
    }

    private function createClient(array $queue): Client
    {
        $stack = HandlerStack::create(new MockHandler($queue));
        $stack->push($this->middleware);

        return new Client([
            'http_errors' => true,
            'handler' => $stack,
        ]);
    }
}

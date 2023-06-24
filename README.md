# Guzzle Circuit Breaker Middleware

## Installation
```
composer require ksaveras/guzzle-circuit-breaker-middleware
```

## Use

It's recommended to use CB (Circuit Breaker) Middleware on top of the stack, but after the cache 
middleware if you use any. This allows to utilize the cache layer and fail fast when the service
is not available.

```php
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use Ksaveras\GuzzleCircuitBreakerMiddleware\CircuitBreakerMiddleware;

// $factory is instance of CircuitBreakerFactory
$middleware = new CircuitBreakerMiddleware($factory->create('CB Name'));

$handlerStack = HandlerStack::create();
$handlerStack->push($middleware);

$client = new Client(['handler' => $handlerStack]);
```

## Tests
```
composer test
```

## Code quality
```
composer static-analysis
```

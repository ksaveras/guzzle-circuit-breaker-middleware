# Guzzle Circuit Breaker Middleware

## Installation
```
composer require ksaveras/guzzle-circuit-breaker-middleware
```

## Use

It's recommended to use CB (Circuit Breaker) Middleware on top of the stack, but after the cache 
middleware if you use any. This allows to utilize the cache layer and fail fast when the service
is not available.

Use `'http_errors' => false` option for catching 5xx errors. CB will mark requests as failed automatically.

```php
use \Ksaveras\GuzzleCircuitBreakerMiddleware;

// $factory is instance of CircuitBreakerFactory
$middleware = new CircuitBreakerMiddleware($factory->create('CB Name'));

$handlerStack = HandlerStack::create();
$handlerStack->push($middleware);

$client = new Client(['handler' => $handlerStack, 'http_errors' => false]);
```

## Tests
```
composer test
```

## Code quality
```
composer phpstan
composer phpcsfix
```

<?php

use Hibla\EventLoop\Loop;
use Hibla\Promise\Handlers\ChainHandler;
use Hibla\Promise\Interfaces\PromiseInterface;

beforeEach(function () {
    $this->chainHandler = new ChainHandler;
});

describe('ChainHandler', function () {
    describe('then handler creation', function () {
        it('should create handler that transforms value', function () {
            $resolvedValue = null;
            $rejectedReason = null;

            $resolve = function ($value) use (&$resolvedValue) {
                $resolvedValue = $value;
            };

            $reject = function ($reason) use (&$rejectedReason) {
                $rejectedReason = $reason;
            };

            $onFulfilled = fn ($value) => strtoupper($value);

            $handler = $this->chainHandler->createThenHandler($onFulfilled, $resolve, $reject);
            $handler('test');

            expect($resolvedValue)->toBe('TEST')
                ->and($rejectedReason)->toBeNull()
            ;
        });

        it('should create handler that passes through value when no callback', function () {
            $resolvedValue = null;

            $resolve = function ($value) use (&$resolvedValue) {
                $resolvedValue = $value;
            };

            $reject = function () {};

            $handler = $this->chainHandler->createThenHandler(null, $resolve, $reject);
            $handler('test');

            expect($resolvedValue)->toBe('test');
        });

        it('should handle promise returned from callback', function () {
            $finalValue = null;
            $mockPromise = Mockery::mock(PromiseInterface::class);

            $resolve = function ($value) use (&$finalValue) {
                $finalValue = $value;
            };

            $reject = function () {};

            $onFulfilled = fn ($value) => $mockPromise;

            $mockPromise->shouldReceive('then')
                ->once()
                ->with($resolve, $reject)
            ;

            $handler = $this->chainHandler->createThenHandler($onFulfilled, $resolve, $reject);
            $handler('test');

            // The mock expectation validates the behavior
        });

        it('should reject when callback throws exception', function () {
            $resolvedValue = null;
            $rejectedReason = null;

            $resolve = function ($value) use (&$resolvedValue) {
                $resolvedValue = $value;
            };

            $reject = function ($reason) use (&$rejectedReason) {
                $rejectedReason = $reason;
            };

            $onFulfilled = function () {
                throw new Exception('callback error');
            };

            $handler = $this->chainHandler->createThenHandler($onFulfilled, $resolve, $reject);
            $handler('test');

            expect($rejectedReason)->toBeInstanceOf(Exception::class)
                ->and($rejectedReason->getMessage())->toBe('callback error')
                ->and($resolvedValue)->toBeNull()
            ;
        });
    });

    describe('catch handler creation', function () {
        it('should create handler that transforms rejection reason', function () {
            $resolvedValue = null;
            $rejectedReason = null;

            $resolve = function ($value) use (&$resolvedValue) {
                $resolvedValue = $value;
            };

            $reject = function ($reason) use (&$rejectedReason) {
                $rejectedReason = $reason;
            };

            $onRejected = fn ($reason) => "handled: $reason";

            $handler = $this->chainHandler->createCatchHandler($onRejected, $resolve, $reject);
            $handler('error');

            expect($resolvedValue)->toBe('handled: error')
                ->and($rejectedReason)->toBeNull()
            ;
        });

        it('should create handler that passes through rejection when no callback', function () {
            $rejectedReason = null;

            $resolve = function () {};

            $reject = function ($reason) use (&$rejectedReason) {
                $rejectedReason = $reason;
            };

            $handler = $this->chainHandler->createCatchHandler(null, $resolve, $reject);
            $handler('error');

            expect($rejectedReason)->toBe('error');
        });

        it('should handle promise returned from catch callback', function () {
            $mockPromise = Mockery::mock(PromiseInterface::class);

            $resolve = function () {};
            $reject = function () {};

            $onRejected = fn ($reason) => $mockPromise;

            $mockPromise->shouldReceive('then')
                ->once()
                ->with($resolve, $reject)
            ;

            $handler = $this->chainHandler->createCatchHandler($onRejected, $resolve, $reject);
            $handler('error');
        });

        it('should reject when catch callback throws exception', function () {
            $rejectedReason = null;

            $resolve = function () {};

            $reject = function ($reason) use (&$rejectedReason) {
                $rejectedReason = $reason;
            };

            $onRejected = function () {
                throw new Exception('catch callback error');
            };

            $handler = $this->chainHandler->createCatchHandler($onRejected, $resolve, $reject);
            $handler('original error');

            expect($rejectedReason)->toBeInstanceOf(Exception::class)
                ->and($rejectedReason->getMessage())->toBe('catch callback error')
            ;
        });
    });

    describe('handler scheduling', function () {
        it('should schedule handler execution', function () {
            $executed = false;

            $handler = function () use (&$executed) {
                $executed = true;
            };

            $this->chainHandler->scheduleHandler($handler);

            Loop::run();

            expect($executed)->toBeTrue();
        });
    });
});

afterEach(function () {
    Mockery::close();
});

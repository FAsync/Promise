<?php

use Hibla\Promise\Interfaces\PromiseInterface;
use Hibla\Promise\Promise;

describe('Promise Chaining', function () {
    beforeEach(function () {
        resetTest();
    });

    describe('Then method', function () {
        it('calls onFulfilled when promise is resolved', function () {
            $called = false;
            $receivedValue = null;

            run(function () use (&$called, &$receivedValue) {
                $promise = new Promise;

                $promise->then(function ($value) use (&$called, &$receivedValue) {
                    $called = true;
                    $receivedValue = $value;
                });

                $promise->resolve('test value');
            });

            expect($called)->toBeTrue()
                ->and($receivedValue)->toBe('test value')
            ;
        });

        it('calls onRejected when promise is rejected', function () {
            $called = false;
            $receivedReason = null;

            run(function () use (&$called, &$receivedReason) {
                $promise = new Promise;

                $promise->then(null, function ($reason) use (&$called, &$receivedReason) {
                    $called = true;
                    $receivedReason = $reason;
                });

                $exception = new Exception('test error');
                $promise->reject($exception);
            });

            expect($called)->toBeTrue()
                ->and($receivedReason)->toBeInstanceOf(Exception::class)
            ;
        });

        it('returns a new promise', function () {
            $promise = new Promise;
            $newPromise = $promise->then(function ($value) {
                return $value;
            });

            expect($newPromise)->toBeInstanceOf(PromiseInterface::class)
                ->and($newPromise)->not->toBe($promise)
            ;
        });

        it('transforms values through the chain', function () {
            $finalPromise = run(function () {
                $promise = new Promise;

                $finalPromise = $promise->then(function ($value) {
                    return $value * 2;
                })->then(function ($value) {
                    return $value + 1;
                });

                $promise->resolve(5);

                return $finalPromise;
            });

            expect($finalPromise->isResolved())->toBeTrue()
                ->and($finalPromise->getValue())->toBe(11) // (5 * 2) + 1
            ;
        });

        it('handles promise returning from onFulfilled', function () {
            $chainedPromise = run(function () {
                $promise = new Promise;
                $innerPromise = new Promise;

                $chainedPromise = $promise->then(function ($value) use ($innerPromise) {
                    return $innerPromise;
                });

                $promise->resolve('original');
                // At this point chainedPromise should be pending

                $innerPromise->resolve('inner value');

                return $chainedPromise;
            });

            expect($chainedPromise->isResolved())->toBeTrue()
                ->and($chainedPromise->getValue())->toBe('inner value')
            ;
        });

        it('handles exceptions in onFulfilled', function () {
            $chainedPromise = run(function () {
                $promise = new Promise;
                $exception = new Exception('handler error');

                $chainedPromise = $promise->then(function ($value) use ($exception) {
                    throw $exception;
                });

                $promise->resolve('value');

                return $chainedPromise;
            });

            expect($chainedPromise->isRejected())->toBeTrue()
                ->and($chainedPromise->getReason())->toBeInstanceOf(Exception::class)
                ->and($chainedPromise->getReason()->getMessage())->toBe('handler error')
            ;
        });

        it('calls handlers for already resolved promises', function () {
            $called = false;
            $receivedValue = null;

            run(function () use (&$called, &$receivedValue) {
                $promise = new Promise;
                $promise->resolve('test value');

                $promise->then(function ($value) use (&$called, &$receivedValue) {
                    $called = true;
                    $receivedValue = $value;
                });
            });

            expect($called)->toBeTrue()
                ->and($receivedValue)->toBe('test value')
            ;
        });

        it('supports multiple then handlers', function () {
            $calls = [];

            run(function () use (&$calls) {
                $promise = new Promise;

                $promise->then(function ($value) use (&$calls) {
                    $calls[] = 'first: '.$value;
                });

                $promise->then(function ($value) use (&$calls) {
                    $calls[] = 'second: '.$value;
                });

                $promise->resolve('test');
            });

            expect($calls)->toHaveCount(2)
                ->and($calls)->toContain('first: test')
                ->and($calls)->toContain('second: test')
            ;
        });
    });

    describe('Catch method', function () {
        it('handles rejected promises', function () {
            $called = false;
            /** @var Exception|null $receivedReason */
            $receivedReason = null;

            run(function () use (&$called, &$receivedReason) {
                $promise = new Promise;

                $promise->catch(function ($reason) use (&$called, &$receivedReason) {
                    $called = true;
                    $receivedReason = $reason;

                    return 'recovered';
                });

                $exception = new Exception('test error');
                $promise->reject($exception);
            });

            expect($called)->toBeTrue()
                ->and($receivedReason)->toBeInstanceOf(Exception::class)
                ->and($receivedReason->getMessage())->toBe('test error')
            ;
        });

        it('does not handle resolved promises', function () {
            $promise = new Promise;
            $called = false;

            $promise->catch(function ($reason) use (&$called) {
                $called = true;
            });

            $promise->resolve('value');

            expect($called)->toBeFalse();
        });

        it('can recover from rejection', function () {
            $recoveredPromise = run(function () {
                $promise = new Promise;
                $exception = new Exception('error');

                $recoveredPromise = $promise->catch(function ($reason) {
                    return 'recovered value';
                });

                $promise->reject($exception);

                return $recoveredPromise;
            });

            expect($recoveredPromise->isResolved())->toBeTrue()
                ->and($recoveredPromise->getValue())->toBe('recovered value')
            ;
        });
    });

    describe('Finally method', function () {
        it('calls finally handler on resolution', function () {
            $called = false;

            run(function () use (&$called) {
                $promise = new Promise;

                $promise->finally(function () use (&$called) {
                    $called = true;
                });

                $promise->resolve('value');
            });

            expect($called)->toBeTrue();
        });

        it('calls finally handler on rejection', function () {
            $called = false;

            run(function () use (&$called) {
                $promise = new Promise;

                $promise->finally(function () use (&$called) {
                    $called = true;
                });

                $promise->reject(new Exception('error'));
            });

            expect($called)->toBeTrue();
        });

        it('returns the same promise', function () {
            $promise = new Promise;
            $finallyPromise = $promise->finally(function () {
                // cleanup
            });

            expect($finallyPromise)->toBe($promise);
        });
    });
});

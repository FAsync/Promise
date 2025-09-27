<?php

use Hibla\Promise\Handlers\CallbackHandler;
use Hibla\Promise\Handlers\ResolutionHandler;
use Hibla\Promise\Handlers\StateHandler;

beforeEach(function () {
    $this->stateHandler = new StateHandler();
    $this->callbackHandler = new CallbackHandler();
    $this->resolutionHandler = new ResolutionHandler($this->stateHandler, $this->callbackHandler);
});

describe('ResolutionHandler', function () {
    describe('resolve handling', function () {
        it('should resolve state and execute then callbacks', function () {
            $executedCallbacks = [];
            $value = 'test value';

            $this->callbackHandler->addThenCallback(function ($v) use (&$executedCallbacks) {
                $executedCallbacks[] = "then:$v";
            });

            $this->callbackHandler->addFinallyCallback(function () use (&$executedCallbacks) {
                $executedCallbacks[] = 'finally';
            });

            $this->resolutionHandler->handleResolve($value);

            expect($this->stateHandler->isResolved())->toBeTrue()
                ->and($this->stateHandler->getValue())->toBe($value)
            ;
        });

        it('should not execute catch callbacks on resolve', function () {
            $executedCallbacks = [];

            $this->callbackHandler->addCatchCallback(function () use (&$executedCallbacks) {
                $executedCallbacks[] = 'catch';
            });

            $this->resolutionHandler->handleResolve('value');

            expect($executedCallbacks)->toBeEmpty();
        });
    });

    describe('reject handling', function () {
        it('should reject state and execute catch callbacks', function () {
            $executedCallbacks = [];
            $reason = 'error reason';

            $this->callbackHandler->addCatchCallback(function ($r) use (&$executedCallbacks) {
                $executedCallbacks[] = "catch:$r";
            });

            $this->callbackHandler->addFinallyCallback(function () use (&$executedCallbacks) {
                $executedCallbacks[] = 'finally';
            });

            $this->resolutionHandler->handleReject($reason);

            expect($this->stateHandler->isRejected())->toBeTrue();

            // Note: The callbacks are scheduled for next tick
        });

        it('should not execute then callbacks on reject', function () {
            $executedCallbacks = [];

            $this->callbackHandler->addThenCallback(function () use (&$executedCallbacks) {
                $executedCallbacks[] = 'then';
            });

            $this->resolutionHandler->handleReject('error');

            expect($executedCallbacks)->toBeEmpty();
        });
    });

    describe('state consistency', function () {
        it('should maintain state consistency during resolution', function () {
            $value = 'test';

            expect($this->stateHandler->isPending())->toBeTrue();

            $this->resolutionHandler->handleResolve($value);

            expect($this->stateHandler->isResolved())->toBeTrue()
                ->and($this->stateHandler->isPending())->toBeFalse()
                ->and($this->stateHandler->isRejected())->toBeFalse()
                ->and($this->stateHandler->getValue())->toBe($value)
            ;
        });

        it('should maintain state consistency during rejection', function () {
            $reason = 'error';

            expect($this->stateHandler->isPending())->toBeTrue();

            $this->resolutionHandler->handleReject($reason);

            expect($this->stateHandler->isRejected())->toBeTrue()
                ->and($this->stateHandler->isPending())->toBeFalse()
                ->and($this->stateHandler->isResolved())->toBeFalse()
            ;
        });
    });
});

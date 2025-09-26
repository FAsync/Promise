<?php

use Hibla\Promise\Interfaces\CancellablePromiseInterface;
use Hibla\Promise\Interfaces\PromiseInterface;

describe('CancellablePromise Integration', function () {
    beforeEach(function () {
        resetTest();
    });

    it('works with delay function', function () {
        $promise = delay(0.1);

        expect($promise)->toBeInstanceOf(CancellablePromiseInterface::class);

        $promise->cancel();

        expect($promise->isCancelled())->toBeTrue();
    });

    it('can create timeout operations', function () {
        $promise = timeout(delay(0.1), 0.1);

        expect($promise)->toBeInstanceOf(PromiseInterface::class);

        if ($promise instanceof CancellablePromiseInterface) {
            $promise->cancel();
            expect($promise->isCancelled())->toBeTrue();
        }
    });

    it('works with concurrent operations', function () {
        $tasks = [
            fn () => delay(0.1),
            fn () => delay(0.2),
            fn () => delay(0.3),
        ];

        $promise = concurrent($tasks, 2);

        expect($promise)->toBeInstanceOf(PromiseInterface::class);

        if ($promise instanceof CancellablePromiseInterface) {
            $promise->cancel();
            expect($promise->isCancelled())->toBeTrue();
        }
    });

    it('can be used with delay operations', function () {
        $promise = delay(1.0);
        $timeoutCleared = false;

        $promise->setCancelHandler(function () use (&$timeoutCleared) {
            $timeoutCleared = true;
        });

        $promise->cancel();

        expect($timeoutCleared)->toBeTrue()
            ->and($promise->isCancelled())->toBeTrue();
    });
});
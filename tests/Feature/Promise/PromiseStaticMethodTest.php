<?php

use Hibla\Promise\Promise;

describe('Promise Static Methods', function () {
    beforeEach(function () {
        resetTest();
    });

    describe('Promise::all', function () {
        it('resolves when all promises resolve', function () {
            $promise1 = Promise::resolved('value1');
            $promise2 = Promise::resolved('value2');
            $promise3 = Promise::resolved('value3');

            $result = Promise::all([$promise1, $promise2, $promise3])->await();

            expect($result)->toBe(['value1', 'value2', 'value3']);
        });

        it('rejects when any promise rejects', function () {
            $exception = new Exception('error');

            try {
                $promise1 = Promise::resolved('value1');
                $promise2 = Promise::rejected($exception);
                $promise3 = Promise::resolved('value3');

                Promise::all([$promise1, $promise2, $promise3])->await();
                expect(false)->toBeTrue('Expected exception to be thrown');
            } catch (Exception $e) {
                expect($e)->toBe($exception);
            }
        });

        it('handles empty array', function () {
            $result = Promise::all([])->await();

            expect($result)->toBe([]);
        });

        it('preserves order of results', function () {
            $promises = [
                Promise::resolved('first'),
                Promise::resolved('second'),
                Promise::resolved('third'),
            ];

            $result = Promise::all($promises)->await();

            expect($result[0])->toBe('first')
                ->and($result[1])->toBe('second')
                ->and($result[2])->toBe('third');
        });
    });

    describe('Promise::any', function () {
        it('resolves with the first resolved promise even when earlier promises reject', function () {
            $promise1 = Promise::rejected(new Exception('first error'));
            $promise2 = Promise::rejected(new Exception('second error'));
            $promise3 = Promise::resolved('third success');
            $promise4 = Promise::resolved('fourth success');

            $result = Promise::any([$promise1, $promise2, $promise3, $promise4])->await();

            expect($result)->toBe('third success');
        });

        it('rejects with AggregateException when all promises reject', function () {
            try {
                $promise1 = Promise::rejected(new Exception('first error'));
                $promise2 = Promise::rejected(new Exception('second error'));
                $promise3 = Promise::rejected(new Exception('third error'));

                Promise::any([$promise1, $promise2, $promise3])->await();
                expect(false)->toBeTrue('Expected AggregateException to be thrown');
            } catch (Exception $e) {
                expect($e)->toBeInstanceOf(Exception::class);
            }
        });

        it('resolves immediately with the first successful promise in mixed order', function () {
            $promise1 = new Promise;
            $promise2 = Promise::resolved('quick success');
            $promise3 = new Promise;

            $anyPromise = Promise::any([$promise1, $promise2, $promise3]);

            $promise1->reject(new Exception('delayed error'));
            $promise3->resolve('delayed success');

            $result = $anyPromise->await();

            expect($result)->toBe('quick success');
        });

        it('handles empty array by rejecting', function () {
            try {
                Promise::any([])->await();
                expect(false)->toBeTrue('Expected exception to be thrown for empty array');
            } catch (Exception $e) {
                expect($e)->toBeInstanceOf(Exception::class);
            }
        });

        it('resolves with first successful promise when mixed with pending promises', function () {
            $promise1 = Promise::rejected(new Exception('error'));
            $promise2 = new Promise; // Never settles
            $promise3 = Promise::resolved('success');
            $promise4 = new Promise; // Never settles

            $result = Promise::any([$promise1, $promise2, $promise3, $promise4])->await();

            expect($result)->toBe('success');
        });
    });

    describe('Promise::race', function () {
        it('resolves with the first settled promise value', function () {
            $promise1 = Promise::resolved('fast');
            $promise2 = new Promise; // never settles
            $promise3 = new Promise; // never settles

            $result = Promise::race([$promise1, $promise2, $promise3])->await();

            expect($result)->toBe('fast');
        });

        it('rejects with the first settled promise reason', function () {
            $exception = new Exception('fast error');

            try {
                $promise1 = Promise::rejected($exception);
                $promise2 = new Promise; // never settles

                Promise::race([$promise1, $promise2])->await();
                expect(false)->toBeTrue('Expected exception to be thrown');
            } catch (Exception $e) {
                expect($e)->toBe($exception);
            }
        });
    });
});
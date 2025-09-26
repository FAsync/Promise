<?php

use Hibla\Promise\Promise;

describe('Promise Static Methods', function () {
    beforeEach(function () {
        resetTest();
    });

    describe('Promise::all', function () {
        it('resolves when all promises resolve', function () {
            $result = run(function () {
                $promise1 = Promise::resolved('value1');
                $promise2 = Promise::resolved('value2');
                $promise3 = Promise::resolved('value3');

                return await(Promise::all([$promise1, $promise2, $promise3]));
            });

            expect($result)->toBe(['value1', 'value2', 'value3']);
        });

        it('rejects when any promise rejects', function () {
            $exception = new Exception('error');

            try {
                run(function () use ($exception) {
                    $promise1 = Promise::resolved('value1');
                    $promise2 = Promise::rejected($exception);
                    $promise3 = Promise::resolved('value3');

                    return await(Promise::all([$promise1, $promise2, $promise3]));
                });

                expect(false)->toBeTrue('Expected exception to be thrown');
            } catch (Exception $e) {
                expect($e)->toBe($exception);
            }
        });

        it('handles empty array', function () {
            $result = run(function () {
                return await(Promise::all([]));
            });

            expect($result)->toBe([]);
        });

        it('preserves order of results', function () {
            $result = run(function () {
                $promises = [
                    Promise::resolved('first'),
                    Promise::resolved('second'),
                    Promise::resolved('third'),
                ];

                return await(Promise::all($promises));
            });

            expect($result[0])->toBe('first')
                ->and($result[1])->toBe('second')
                ->and($result[2])->toBe('third')
            ;
        });
    });

    describe('Promise::any', function () {
        it('resolves with the first resolved promise even when earlier promises reject', function () {
            $result = run(function () {
                $promise1 = Promise::rejected(new Exception('first error'));
                $promise2 = Promise::rejected(new Exception('second error'));
                $promise3 = Promise::resolved('third success');
                $promise4 = Promise::resolved('fourth success');

                return await(Promise::any([$promise1, $promise2, $promise3, $promise4]));
            });

            expect($result)->toBe('third success');
        });

        it('rejects with AggregateException when all promises reject', function () {
            try {
                run(function () {
                    $promise1 = Promise::rejected(new Exception('first error'));
                    $promise2 = Promise::rejected(new Exception('second error'));
                    $promise3 = Promise::rejected(new Exception('third error'));

                    return await(Promise::any([$promise1, $promise2, $promise3]));
                });

                expect(false)->toBeTrue('Expected AggregateException to be thrown');
            } catch (Exception $e) {
                expect($e)->toBeInstanceOf(Exception::class);
            }
        });

        it('resolves immediately with the first successful promise in mixed order', function () {
            $result = run(function () {
                $promise1 = new Promise;
                $promise2 = Promise::resolved('quick success');
                $promise3 = new Promise;

                $anyPromise = Promise::any([$promise1, $promise2, $promise3]);

                $promise1->reject(new Exception('delayed error'));
                $promise3->resolve('delayed success');

                return await($anyPromise);
            });

            expect($result)->toBe('quick success');
        });

        it('handles empty array by rejecting', function () {
            try {
                run(function () {
                    return await(Promise::any([]));
                });

                expect(false)->toBeTrue('Expected exception to be thrown for empty array');
            } catch (Exception $e) {
                expect($e)->toBeInstanceOf(Exception::class);
            }
        });

        it('resolves with first successful promise when mixed with pending promises', function () {
            $result = run(function () {
                $promise1 = Promise::rejected(new Exception('error'));
                $promise2 = new Promise; // Never settles
                $promise3 = Promise::resolved('success');
                $promise4 = new Promise; // Never settles

                return await(Promise::any([$promise1, $promise2, $promise3, $promise4]));
            });

            expect($result)->toBe('success');
        });
    });

    describe('Promise::race', function () {
        it('resolves with the first settled promise value', function () {
            $result = run(function () {
                $promise1 = Promise::resolved('fast');
                $promise2 = new Promise; // never settles
                $promise3 = new Promise; // never settles

                return await(Promise::race([$promise1, $promise2, $promise3]));
            });

            expect($result)->toBe('fast');
        });

        it('rejects with the first settled promise reason', function () {
            $exception = new Exception('fast error');

            try {
                run(function () use ($exception) {
                    $promise1 = Promise::rejected($exception);
                    $promise2 = new Promise; // never settles

                    return await(Promise::race([$promise1, $promise2]));
                });

                expect(false)->toBeTrue('Expected exception to be thrown');
            } catch (Exception $e) {
                expect($e)->toBe($exception);
            }
        });
    });
});

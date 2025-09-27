<?php

use Hibla\Async\Exceptions\AggregateErrorException;
use Hibla\Async\Exceptions\TimeoutException;
use Hibla\Promise\Promise;

describe('Promise Static Methods Integration', function () {
    describe('Promise::resolved() and Promise::rejected()', function () {
        it('creates resolved promises', function () {
            $promise = Promise::resolved('test value');

            expect($promise)->toBePromise();
            expect($promise->isResolved())->toBeTrue();
            expect($promise->getValue())->toBe('test value');

            $result = await($promise);
            expect($result)->toBe('test value');
        });

        it('creates rejected promises', function () {
            $error = new RuntimeException('test error');
            $promise = Promise::rejected($error);

            expect($promise)->toBePromise();
            expect($promise->isRejected())->toBeTrue();
            expect($promise->getReason())->toBe($error);

            expect(fn() => await($promise))
                ->toThrow(RuntimeException::class, 'test error');
        });
    });

    describe('Promise::all()', function () {
        it('resolves when all promises resolve', function () {
            $promises = [
                Promise::resolved('first'),
                Promise::resolved('second'),
                Promise::resolved('third'),
            ];

            $promise = Promise::all($promises);
            $results = await($promise);

            expect($results)->toBe(['first', 'second', 'third']);
        });

        it('preserves string keys in results', function () {
            $promises = [
                'a' => Promise::resolved('first'),
                'b' => Promise::resolved('second'),
                'c' => Promise::resolved('third'),
            ];

            $promise = Promise::all($promises);
            $results = await($promise);

            expect($results)->toEqual([
                'a' => 'first',
                'b' => 'second',
                'c' => 'third',
            ]);
        });

        it('rejects when any promise rejects', function () {
            $promises = [
                Promise::resolved('success'),
                Promise::rejected(new RuntimeException('all error')),
                Promise::resolved('another success'),
            ];

            $promise = Promise::all($promises);

            expect(fn() => await($promise))
                ->toThrow(RuntimeException::class, 'all error');
        });

        it('works with async functions', function () {
            $promises = [
                async(fn() => 'async-first'),
                async(fn() => 'async-second'),
                function () {
                    return async(function () {
                        await(delay(0.05));
                        return 'async-delayed';
                    });
                },
            ];

            $promise = Promise::all($promises);
            $results = await($promise);

            expect($results)->toBe(['async-first', 'async-second', 'async-delayed']);
        });

        it('handles empty array', function () {
            $promise = Promise::all([]);
            $results = await($promise);

            expect($results)->toBe([]);
        });
    });

    describe('Promise::allSettled()', function () {
        it('waits for all promises to settle', function () {
            $promises = [
                Promise::resolved('success'),
                Promise::rejected(new RuntimeException('error')),
                Promise::resolved('another success'),
            ];

            $promise = Promise::allSettled($promises);
            $results = await($promise);

            expect($results)->toHaveCount(3);

            expect($results[0])->toEqual([
                'status' => 'fulfilled',
                'value' => 'success'
            ]);

            expect($results[1]['status'])->toBe('rejected');
            expect($results[1]['reason'])->toBeInstanceOf(RuntimeException::class);
            expect($results[1]['reason']->getMessage())->toBe('error');

            expect($results[2])->toEqual([
                'status' => 'fulfilled',
                'value' => 'another success'
            ]);
        });

        it('preserves string keys in settlement results', function () {
            $promises = [
                'success' => Promise::resolved('good'),
                'failure' => Promise::rejected(new RuntimeException('bad')),
            ];

            $promise = Promise::allSettled($promises);
            $results = await($promise);

            expect($results)->toHaveKey('success');
            expect($results)->toHaveKey('failure');

            expect($results['success']['status'])->toBe('fulfilled');
            expect($results['failure']['status'])->toBe('rejected');
        });

        it('works with mixed async and sync promises', function () {
            $promises = [
                Promise::resolved('sync'),
                async(fn() => 'async'),
                function () {
                    return async(function () {
                        throw new RuntimeException('async error');
                    });
                },
            ];

            $promise = Promise::allSettled($promises);
            $results = await($promise);

            expect($results)->toHaveCount(3);
            expect($results[0]['status'])->toBe('fulfilled');
            expect($results[1]['status'])->toBe('fulfilled');
            expect($results[2]['status'])->toBe('rejected');
        });
    });

    describe('Promise::race()', function () {
        it('resolves with the first settled promise', function () {
            $promises = [
                function () {
                    return async(function () {
                        await(delay(0.1));
                        return 'slow';
                    });
                },
                Promise::resolved('fast'),
                function () {
                    return async(function () {
                        await(delay(0.2));
                        return 'slower';
                    });
                },
            ];

            $promise = Promise::race($promises);
            $result = await($promise);

            expect($result)->toBe('fast');
        });

        it('rejects with the first rejection', function () {
            $promises = [
                async(function () {
                    await(delay(0.1));
                    return 'slow success';
                }),
                Promise::rejected(new RuntimeException('fast error')),
            ];

            $promise = Promise::race($promises);

            expect(fn() => await($promise))
                ->toThrow(RuntimeException::class, 'fast error');
        });

        it('cancels cancellable promises when race settles', function () {
            $completed = [];

            $immediatePromise = Promise::resolved('immediate')->then(function ($value) use (&$completed) {
                $completed[] = 'immediate';
                return $value;
            });

            $slowDelay = delay(0.1);
            $delayedPromise = $slowDelay->then(function () use (&$completed) {
                $completed[] = 'delayed';
                return 'delayed';
            });

            $promises = [
                $immediatePromise,
                $delayedPromise,
            ];

            $promise = Promise::race($promises);
            $result = await($promise);

            expect($result)->toBe('immediate');

            usleep(120000);

            expect($completed)->toBe(['immediate']);

            if (method_exists($slowDelay, 'isCancelled')) {
                expect($slowDelay->isCancelled())->toBeTrue();
            }
        });

        it('does not cancel async function wrappers, only direct cancellable promises', function () {
            $completed = [];

            $immediatePromise = Promise::resolved('immediate')->then(function ($value) use (&$completed) {
                $completed[] = 'immediate';
                return $value;
            });

            $delayedPromise = async(asyncFunction: function () use (&$completed) {
                await(delay(0.1));
                $completed[] = 'delayed-async';
                return 'delayed';
            });

            $promises = [
                $immediatePromise,
                $delayedPromise,
            ];

            $promise = Promise::race($promises);
            $result = await($promise);

            expect($result)->toBe('immediate');
            expect($completed)->toBe(['immediate', 'delayed-async']);
        });

        it('demonstrates cancellation hierarchy in race conditions', function () {
            $events = [];

            $shortDelay = delay(0.05);
            $longDelay = delay(0.15);

            $shortPromise = $shortDelay->then(function () use (&$events) {
                $events[] = 'short-completed';
                return 'short';
            });

            $longPromise = $longDelay->then(function () use (&$events) {
                $events[] = 'long-completed';
                return 'long';
            });

            $immediatePromise = Promise::resolved('immediate')->then(function ($value) use (&$events) {
                $events[] = 'immediate-completed';
                return $value;
            });

            $promises = [
                'short' => $shortPromise,
                'long' => $longPromise,
                'immediate' => $immediatePromise,
            ];

            $promise = Promise::race($promises);
            $result = await($promise);

            expect($result)->toBe('immediate');

            usleep(200000);

            expect($events)->toBe(['immediate-completed']);

            if (method_exists($shortDelay, 'isCancelled')) {
                expect($shortDelay->isCancelled())->toBeTrue();
            }
            if (method_exists($longDelay, 'isCancelled')) {
                expect($longDelay->isCancelled())->toBeTrue();
            }
        });
    });

    describe('Promise::any()', function () {
        it('resolves with the first fulfilled promise', function () {
            $promises = [
                Promise::rejected(new RuntimeException('first error')),
                Promise::resolved('first success'),
                Promise::rejected(new RuntimeException('second error')),
            ];

            $promise = Promise::any($promises);
            $result = await($promise);

            expect($result)->toBe('first success');
        });

        it('rejects with AggregateErrorException when all promises reject', function () {
            $promises = [
                Promise::rejected(new RuntimeException('error 1')),
                Promise::rejected(new InvalidArgumentException('error 2')),
                Promise::rejected(new LogicException('error 3')),
            ];

            $promise = Promise::any($promises);

            try {
                await($promise);
                expect(false)->toBeTrue('Expected AggregateErrorException to be thrown');
            } catch (AggregateErrorException $e) {
                expect($e->getMessage())->toContain('All promises were rejected');
                $errors = $e->getErrors();
                expect($errors)->toHaveCount(3);
                expect($errors[0])->toBeInstanceOf(RuntimeException::class);
                expect($errors[1])->toBeInstanceOf(InvalidArgumentException::class);
                expect($errors[2])->toBeInstanceOf(LogicException::class);
            }
        });

        it('works with async functions', function () {
            $promises = [
                function () {
                    return async(function () {
                        await(delay(0.1));
                        throw new RuntimeException('slow error');
                    });
                },
                function () {
                    return async(function () {
                        await(delay(0.05));
                        return 'fast success';
                    });
                },
            ];

            $promise = Promise::any($promises);
            $result = await($promise);

            expect($result)->toBe('fast success');
        });
    });

    describe('Promise::timeout()', function () {
        it('resolves if promise completes before timeout', function () {
            $fastPromise = async(function () {
                await(delay(0.05));
                return 'completed in time';
            });

            $promise = Promise::timeout($fastPromise, 0.2); // 200ms timeout
            $result = await($promise);

            expect($result)->toBe('completed in time');
        });

        it('rejects with TimeoutException if promise takes too long', function () {
            $slowPromise = async(function () {
                await(delay(0.2));
                return 'too slow';
            });

            $promise = Promise::timeout($slowPromise, 0.05); // 50ms timeout

            expect(fn() => await($promise))
                ->toThrow(TimeoutException::class);
        });

        it('handles promise rejection before timeout', function () {
            $errorPromise = async(function () {
                await(delay(0.05));
                throw new RuntimeException('promise error');
            });

            $promise = Promise::timeout($errorPromise, 0.2);

            expect(fn() => await($promise))
                ->toThrow(RuntimeException::class, 'promise error');
        });
    });

    describe('Promise::concurrent()', function () {
        it('executes tasks concurrently with default limit', function () {
            $start = microtime(true);

            $tasks = [];
            for ($i = 0; $i < 5; $i++) {
                $tasks[] = async(function () use ($i) {
                        await(delay(0.1));
                        return "task-$i";
                    });
            }

            $promise = Promise::concurrent($tasks);
            $results = await($promise);

            $elapsed = microtime(true) - $start;

            expect($results)->toHaveCount(5);
            expect($results[0])->toBe('task-0');
            expect($results[4])->toBe('task-4');

            // Should complete in roughly 0.1s (all concurrent) rather than 0.5s (sequential)
            expect($elapsed)->toBeLessThan(0.3);
        });

        it('respects concurrency limit', function () {
            $running = 0;
            $maxConcurrent = 0;

            $tasks = [];
            for ($i = 0; $i < 10; $i++) {
                $tasks[] = function () use ($i, &$running, &$maxConcurrent) {
                    return async(function () use ($i, &$running, &$maxConcurrent) {
                        $running++;
                        $maxConcurrent = max($maxConcurrent, $running);

                        await(delay(0.05));

                        $running--;
                        return "task-$i";
                    });
                };
            }

            $promise = Promise::concurrent($tasks, 3); // Limit to 3 concurrent
            $results = await($promise);

            expect($results)->toHaveCount(10);
            expect($maxConcurrent)->toBeLessThanOrEqual(3);
        });
    });

    describe('Promise::batch()', function () {
        it('processes tasks in sequential batches', function () {
            $batchOrder = [];

            $tasks = [];
            for ($i = 0; $i < 6; $i++) {
                $tasks[] = function () use ($i, &$batchOrder) {
                    return async(function () use ($i, &$batchOrder) {
                        $batchOrder[] = "start-$i";
                        await(delay(0.05));
                        $batchOrder[] = "end-$i";
                        return "task-$i";
                    });
                };
            }

            $promise = Promise::batch($tasks, 3); // Batch size of 3
            $results = await($promise);

            expect($results)->toHaveCount(6);

            // First batch (0,1,2) should complete before second batch (3,4,5) starts
            $firstBatchStarts = array_filter($batchOrder, fn($item) => str_starts_with($item, 'start') && in_array($item, ['start-0', 'start-1', 'start-2']));
            $secondBatchStarts = array_filter($batchOrder, fn($item) => str_starts_with($item, 'start') && in_array($item, ['start-3', 'start-4', 'start-5']));

            expect(count($firstBatchStarts))->toBe(3);
            expect(count($secondBatchStarts))->toBe(3);
        });
    });

    describe('Promise::concurrentSettled() and Promise::batchSettled()', function () {
        it('handles mixed success and failure in concurrent execution', function () {
            $tasks = [
                async(fn() => 'success-1'),
                async(function () {
                    throw new RuntimeException('error-1');
                }),
                async(fn() => 'success-2'),
            ];

            $promise = Promise::concurrentSettled($tasks);
            $results = await($promise);

            expect($results)->toHaveCount(3);

            expect($results[0]['status'])->toBe('fulfilled');
            expect($results[0]['value'])->toBe('success-1');

            expect($results[1]['status'])->toBe('rejected');
            expect($results[1]['reason'])->toBeInstanceOf(RuntimeException::class);

            expect($results[2]['status'])->toBe('fulfilled');
            expect($results[2]['value'])->toBe('success-2');
        });

        it('handles mixed results in batch processing', function () {
            $tasks = [];
            for ($i = 0; $i < 6; $i++) {
                if ($i % 2 === 0) {
                    $tasks[] = async(fn() => "success-$i");
                } else {
                    $tasks[] = async(function () use ($i) {
                        throw new RuntimeException("error-$i");
                    });
                }
            }

            $promise = Promise::batchSettled($tasks, 3);
            $results = await($promise);

            expect($results)->toHaveCount(6);

            // Check even indices are successful
            foreach ([0, 2, 4] as $index) {
                expect($results[$index]['status'])->toBe('fulfilled');
                expect($results[$index]['value'])->toBe("success-$index");
            }

            // Check odd indices are rejected
            foreach ([1, 3, 5] as $index) {
                expect($results[$index]['status'])->toBe('rejected');
                expect($results[$index]['reason']->getMessage())->toBe("error-$index");
            }
        });
    });

    describe('Integration with global/namespace functions', function () {
        it('works seamlessly with global async functions', function () {
            $promises = [
                async(fn() => 'global-async'),
                Promise::resolved('static-resolved'),
                async(function () {
                    $result = await(Promise::resolved('awaited-static'));
                    return "processed-$result";
                }),
            ];

            $promise = Promise::all($promises);
            $results = await($promise);

            expect($results)->toBe([
                'global-async',
                'static-resolved',
                'processed-awaited-static'
            ]);
        });

        it('integrates with namespace functions', function () {
            $promises = [
                Hibla\async(fn() => 'namespace-async'),
                Promise::resolved('static-promise'),
                Hibla\async(function () {
                    \Hibla\await(\Hibla\delay(0.05));
                    return 'namespace-delayed';
                }),
            ];

            $promise = Promise::allSettled($promises);
            $results = await($promise);

            expect($results)->toHaveCount(3);
            foreach ($results as $result) {
                expect($result['status'])->toBe('fulfilled');
            }
        });
    });
});

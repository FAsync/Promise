<?php

namespace Hibla\Promise;

use Hibla\Async\AsyncOperations;
use Hibla\Promise\Handlers\AwaitHandler;
use Hibla\Promise\Handlers\CallbackHandler;
use Hibla\Promise\Handlers\ChainHandler;
use Hibla\Promise\Handlers\ExecutorHandler;
use Hibla\Promise\Handlers\ResolutionHandler;
use Hibla\Promise\Handlers\StateHandler;
use Hibla\Promise\Interfaces\CancellablePromiseInterface;
use Hibla\Promise\Interfaces\PromiseCollectionInterface;
use Hibla\Promise\Interfaces\PromiseInterface;

/**
 * A Promise/A+ compliant implementation for managing asynchronous operations.
 *
 * This class provides a robust mechanism for handling eventual results or
 * failures from asynchronous tasks. It supports chaining, error handling,
 * and a clear lifecycle (pending, fulfilled, rejected).
 *
 * @template TValue
 *
 * @implements PromiseInterface<TValue>
 */
class Promise implements PromiseCollectionInterface, PromiseInterface
{
    /**
     * @var StateHandler Manages the promise's state (pending, resolved, rejected)
     */
    private StateHandler $stateHandler;

    /**
     * @var CallbackHandler Manages then, catch, and finally callback queues
     */
    private CallbackHandler $callbackHandler;

    /**
     * @var ExecutorHandler Handles the initial executor function execution
     */
    private ExecutorHandler $executorHandler;

    /**
     * @var ChainHandler Manages promise chaining and callback scheduling
     */
    private ChainHandler $chainHandler;

    /**
     * @var ResolutionHandler Handles promise resolution and rejection logic
     */
    private ResolutionHandler $resolutionHandler;

    /**
     * @var CancellablePromiseInterface<mixed>|null
     */
    protected ?CancellablePromiseInterface $rootCancellable = null;

    private AwaitHandler $awaitHandler;

    /**
     * @var AsyncOperations|null Static instance for collection operations
     */
    private static ?AsyncOperations $asyncOps = null;

    /**
     * Create a new promise with an optional executor function.
     *
     * The executor function receives resolve and reject callbacks that
     * can be used to settle the promise. If no executor is provided,
     * the promise starts in a pending state.
     *
     * @param  callable|null  $executor  Function to execute immediately with resolve/reject callbacks
     */
    public function __construct(?callable $executor = null)
    {
        $this->stateHandler = new StateHandler;
        $this->callbackHandler = new CallbackHandler;
        $this->executorHandler = new ExecutorHandler;
        $this->chainHandler = new ChainHandler;
        $this->resolutionHandler = new ResolutionHandler(
            $this->stateHandler,
            $this->callbackHandler
        );

        $this->executorHandler->executeExecutor(
            $executor,
            fn ($value = null) => $this->resolve($value),
            fn ($reason = null) => $this->reject($reason)
        );
    }

    /**
     * {@inheritdoc}
     */
    public function await(bool $resetEventLoop = true): mixed
    {
        $this->awaitHandler ??= new AwaitHandler;

        return $this->awaitHandler->await($this, $resetEventLoop);
    }

    /**
     * {@inheritdoc}
     */
    public function isSettled(): bool
    {
        // A promise is settled if it is no longer pending.
        return ! $this->stateHandler->isPending();
    }

    /**
     * Resolve the promise with a value.
     *
     * If the promise is already settled, this operation has no effect.
     * The resolution triggers all registered fulfillment callbacks.
     *
     * @param  mixed  $value  The value to resolve the promise with
     */
    public function resolve(mixed $value): void
    {
        $this->resolutionHandler->handleResolve($value);
    }

    /**
     * Reject the promise with a reason.
     *
     * If the promise is already settled, this operation has no effect.
     * The rejection triggers all registered rejection callbacks.
     *
     * @param  mixed  $reason  The reason for rejection (typically an exception)
     */
    public function reject(mixed $reason): void
    {
        $this->resolutionHandler->handleReject($reason);
    }

    /**
     * {@inheritdoc}
     *
     * @template TResult
     *
     * @param  callable(TValue): (TResult|PromiseInterface<TResult>)|null  $onFulfilled
     * @param  callable(mixed): (TResult|PromiseInterface<TResult>)|null  $onRejected
     * @return PromiseInterface<TResult>
     */
    public function then(?callable $onFulfilled = null, ?callable $onRejected = null): PromiseInterface
    {
        /** @var Promise<TResult> $newPromise */
        $newPromise = new self(
            /**
             * @param  callable(TResult): void  $resolve
             * @param  callable(mixed): void  $reject
             */
            function (callable $resolve, callable $reject) use ($onFulfilled, $onRejected) {
                $root = $this instanceof CancellablePromiseInterface
                    ? $this
                    : $this->rootCancellable;

                $handleResolve = function ($value) use ($onFulfilled, $resolve, $reject, $root) {
                    if ($root !== null && $root->isCancelled()) {
                        return;
                    }

                    if ($onFulfilled !== null) {
                        try {
                            $result = $onFulfilled($value);
                            if ($result instanceof PromiseInterface) {
                                $result->then($resolve, $reject);
                            } else {
                                $resolve($result);
                            }
                        } catch (\Throwable $e) {
                            $reject($e);
                        }
                    } else {
                        $resolve($value);
                    }
                };

                $handleReject = function ($reason) use ($onRejected, $resolve, $reject, $root) {
                    if ($root !== null && $root->isCancelled()) {
                        return;
                    }

                    if ($onRejected !== null) {
                        try {
                            $result = $onRejected($reason);
                            if ($result instanceof PromiseInterface) {
                                $result->then($resolve, $reject);
                            } else {
                                $resolve($result);
                            }
                        } catch (\Throwable $e) {
                            $reject($e);
                        }
                    } else {
                        $reject($reason);
                    }
                };

                if ($this->stateHandler->isResolved()) {
                    $this->chainHandler->scheduleHandler(fn () => $handleResolve($this->stateHandler->getValue()));
                } elseif ($this->stateHandler->isRejected()) {
                    $this->chainHandler->scheduleHandler(fn () => $handleReject($this->stateHandler->getReason()));
                } else {
                    $this->callbackHandler->addThenCallback($handleResolve);
                    $this->callbackHandler->addCatchCallback($handleReject);
                }
            }
        );

        if ($this instanceof CancellablePromiseInterface) {
            $newPromise->rootCancellable = $this;
        } elseif ($this->rootCancellable !== null) {
            $newPromise->rootCancellable = $this->rootCancellable;
        }

        return $newPromise;
    }

    /**
     * @return CancellablePromiseInterface<mixed>|null
     */
    public function getRootCancellable(): ?CancellablePromiseInterface
    {
        return $this->rootCancellable;
    }

    /**
     * {@inheritdoc}
     *
     * @template TResult
     *
     * @param  callable(mixed): (TResult|PromiseInterface<TResult>)  $onRejected
     * @return PromiseInterface<TResult>
     */
    public function catch(callable $onRejected): PromiseInterface
    {
        return $this->then(null, $onRejected);
    }

    /**
     * {@inheritdoc}
     *
     * @return PromiseInterface<TValue>
     */
    public function finally(callable $onFinally): PromiseInterface
    {
        $this->callbackHandler->addFinallyCallback($onFinally);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function isResolved(): bool
    {
        return $this->stateHandler->isResolved();
    }

    /**
     * {@inheritdoc}
     */
    public function isRejected(): bool
    {
        return $this->stateHandler->isRejected();
    }

    /**
     * {@inheritdoc}
     */
    public function isPending(): bool
    {
        return $this->stateHandler->isPending();
    }

    /**
     * {@inheritdoc}
     */
    public function getValue(): mixed
    {
        return $this->stateHandler->getValue();
    }

    /**
     * {@inheritdoc}
     */
    public function getReason(): mixed
    {
        return $this->stateHandler->getReason();
    }

    /**
     * Get or create the AsyncOperations instance for static methods.
     */
    private static function getAsyncOps(): AsyncOperations
    {
        if (self::$asyncOps === null) {
            self::$asyncOps = new AsyncOperations;
        }

        return self::$asyncOps;
    }

    /**
     * {@inheritdoc}
     */
    public static function reset(): void
    {
        self::$asyncOps = null;
    }

    /**
     * {@inheritdoc}
     */
    public static function resolved(mixed $value): PromiseInterface
    {
        return self::getAsyncOps()->resolved($value);
    }

    /**
     * {@inheritdoc}
     */
    public static function rejected(mixed $reason): PromiseInterface
    {
        return self::getAsyncOps()->rejected($reason);
    }

    /**
     * {@inheritdoc}
     */
    public static function all(array $promises): PromiseInterface
    {
        return self::getAsyncOps()->all($promises);
    }

    /**
     * {@inheritdoc}
     */
    public static function allSettled(array $promises): PromiseInterface
    {
        return self::getAsyncOps()->allSettled($promises);
    }

    /**
     * {@inheritdoc}
     */
    public static function race(array $promises): PromiseInterface
    {
        return self::getAsyncOps()->race($promises);
    }

    /**
     * {@inheritdoc}
     */
    public static function any(array $promises): PromiseInterface
    {
        return self::getAsyncOps()->any($promises);
    }

    /**
     * {@inheritdoc}
     */
    public static function timeout(PromiseInterface $promise, float $seconds): PromiseInterface
    {
        return self::getAsyncOps()->timeout($promise, $seconds);
    }

    /**
     * {@inheritdoc}
     */
    public static function concurrent(array $tasks, int $concurrency = 10): PromiseInterface
    {
        return self::getAsyncOps()->concurrent($tasks, $concurrency);
    }

    /**
     * {@inheritdoc}
     */
    public static function batch(array $tasks, int $batchSize = 10, ?int $concurrency = null): PromiseInterface
    {
        return self::getAsyncOps()->batch($tasks, $batchSize, $concurrency);
    }

    /**
     * {@inheritdoc}
     */
    public static function concurrentSettled(array $tasks, int $concurrency = 10): PromiseInterface
    {
        return self::getAsyncOps()->concurrentSettled($tasks, $concurrency);
    }

    /**
     * {@inheritdoc}
     */
    public static function batchSettled(array $tasks, int $batchSize = 10, ?int $concurrency = null): PromiseInterface
    {
        return self::getAsyncOps()->batchSettled($tasks, $batchSize, $concurrency);
    }
}

<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
*/

use Hibla\Async\Async;
use Hibla\EventLoop\EventLoop;
use Hibla\Promise\Promise;
use Tests\TestCase;

pest()->extend(TestCase::class)->in('Feature');
pest()->extend(TestCase::class)->in('Unit');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
*/

/**
 * Resets all core singletons and clears test state.
 *
 * This function is the single source of truth for test setup. By calling it
 * in each test file's `beforeEach` hook, we ensure perfect test isolation.
 */
function resetTest()
{
    EventLoop::reset();
    Async::reset();
    Promise::reset();
}

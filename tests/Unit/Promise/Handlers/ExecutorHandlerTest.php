<?php

use Hibla\Promise\Handlers\ExecutorHandler;

beforeEach(function () {
    $this->executorHandler = new ExecutorHandler();
});

describe('ExecutorHandler', function () {
    it('should execute executor with resolve and reject functions', function () {
        $resolvedValue = null;
        $rejectedReason = null;

        $resolve = function ($value) use (&$resolvedValue) {
            $resolvedValue = $value;
        };

        $reject = function ($reason) use (&$rejectedReason) {
            $rejectedReason = $reason;
        };

        $executor = function ($res, $rej) {
            $res('test value');
        };

        $this->executorHandler->executeExecutor($executor, $resolve, $reject);

        expect($resolvedValue)->toBe('test value')
            ->and($rejectedReason)->toBeNull()
        ;
    });

    it('should handle executor that rejects', function () {
        $resolvedValue = null;
        $rejectedReason = null;

        $resolve = function ($value) use (&$resolvedValue) {
            $resolvedValue = $value;
        };

        $reject = function ($reason) use (&$rejectedReason) {
            $rejectedReason = $reason;
        };

        $executor = function ($res, $rej) {
            $rej('error reason');
        };

        $this->executorHandler->executeExecutor($executor, $resolve, $reject);

        expect($rejectedReason)->toBe('error reason')
            ->and($resolvedValue)->toBeNull()
        ;
    });

    it('should handle executor exceptions by rejecting', function () {
        $resolvedValue = null;
        $rejectedReason = null;

        $resolve = function ($value) use (&$resolvedValue) {
            $resolvedValue = $value;
        };

        $reject = function ($reason) use (&$rejectedReason) {
            $rejectedReason = $reason;
        };

        $executor = function () {
            throw new Exception('executor error');
        };

        $this->executorHandler->executeExecutor($executor, $resolve, $reject);

        expect($rejectedReason)->toBeInstanceOf(Exception::class)
            ->and($rejectedReason->getMessage())->toBe('executor error')
            ->and($resolvedValue)->toBeNull()
        ;
    });

    it('should handle null executor gracefully', function () {
        $resolvedValue = null;
        $rejectedReason = null;

        $resolve = function ($value) use (&$resolvedValue) {
            $resolvedValue = $value;
        };

        $reject = function ($reason) use (&$rejectedReason) {
            $rejectedReason = $reason;
        };

        // Should not throw or call resolve/reject
        $this->executorHandler->executeExecutor(null, $resolve, $reject);

        expect($resolvedValue)->toBeNull()
            ->and($rejectedReason)->toBeNull()
        ;
    });

    it('should handle executor with complex operations', function () {
        $resolvedValue = null;

        $resolve = function ($value) use (&$resolvedValue) {
            $resolvedValue = $value;
        };

        $reject = function () {};

        $executor = function ($res, $rej) {
            // Simulate async-like operation
            $result = array_map(fn ($x) => $x * 2, [1, 2, 3]);
            $res($result);
        };

        $this->executorHandler->executeExecutor($executor, $resolve, $reject);

        expect($resolvedValue)->toBe([2, 4, 6]);
    });
});

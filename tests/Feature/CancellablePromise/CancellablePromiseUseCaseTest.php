<?php

use Hibla\Promise\CancellablePromise;

describe('CancellablePromise Real-World Examples', function () {
    beforeEach(function () {
        resetTest();
    });

    it('file upload with progress tracking', function () {
        $uploadProgress = 0;
        $uploadCancelled = false;
        $tempFileDeleted = false;

        $uploadPromise = new CancellablePromise(function ($resolve, $reject) use (&$uploadProgress) {
            $uploadProgress = 25;
        });

        $uploadPromise->setCancelHandler(function () use (&$uploadCancelled, &$tempFileDeleted) {
            $uploadCancelled = true;
            $tempFileDeleted = true;
        });

        $uploadPromise->cancel();

        expect($uploadCancelled)->toBeTrue()
            ->and($tempFileDeleted)->toBeTrue()
            ->and($uploadPromise->isCancelled())->toBeTrue()
        ;
    });

    it('database transaction with rollback', function () {
        $transactionStarted = false;
        $transactionRolledBack = false;

        $dbPromise = new CancellablePromise(function ($resolve, $reject) use (&$transactionStarted) {
            $transactionStarted = true;
        });

        $dbPromise->setCancelHandler(function () use (&$transactionRolledBack) {
            $transactionRolledBack = true;
        });

        $dbPromise->cancel();

        expect($transactionStarted)->toBeTrue()
            ->and($transactionRolledBack)->toBeTrue()
            ->and($dbPromise->isCancelled())->toBeTrue()
        ;
    });

    it('API request with connection cleanup', function () {
        $requestSent = false;
        $connectionClosed = false;
        $cacheCleared = false;

        $apiPromise = new CancellablePromise(function ($resolve, $reject) use (&$requestSent) {
            $requestSent = true;
        });

        $apiPromise->setCancelHandler(function () use (&$connectionClosed, &$cacheCleared) {
            $connectionClosed = true;
            $cacheCleared = true;
        });

        $apiPromise->cancel();

        expect($requestSent)->toBeTrue()
            ->and($connectionClosed)->toBeTrue()
            ->and($cacheCleared)->toBeTrue()
        ;
    });
});

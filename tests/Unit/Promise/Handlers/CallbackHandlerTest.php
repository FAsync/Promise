<?php

use Hibla\Promise\Handlers\CallbackHandler;

beforeEach(function () {
    $this->callbackHandler = new CallbackHandler();
});

describe('CallbackHandler', function () {
    describe('then callbacks', function () {
        it('should execute then callbacks with value', function () {
            $executedCallbacks = [];
            $value = 'test value';
            
            $this->callbackHandler->addThenCallback(function ($v) use (&$executedCallbacks) {
                $executedCallbacks[] = "callback1:$v";
            });
            
            $this->callbackHandler->addThenCallback(function ($v) use (&$executedCallbacks) {
                $executedCallbacks[] = "callback2:$v";
            });
            
            $this->callbackHandler->executeThenCallbacks($value);
            
            expect($executedCallbacks)->toBe([
                "callback1:$value",
                "callback2:$value"
            ]);
        });

        it('should handle callback exceptions without stopping other callbacks', function () {
            $executedCallbacks = [];
            $value = 'test';
            
            $this->callbackHandler->addThenCallback(function () {
                throw new Exception('callback error');
            });
            
            $this->callbackHandler->addThenCallback(function ($v) use (&$executedCallbacks) {
                $executedCallbacks[] = "executed:$v";
            });
            
            // Should not throw and should execute second callback
            $this->callbackHandler->executeThenCallbacks($value);
            
            expect($executedCallbacks)->toBe(["executed:$value"]);
        });
    });

    describe('catch callbacks', function () {
        it('should execute catch callbacks with reason', function () {
            $executedCallbacks = [];
            $reason = 'error reason';
            
            $this->callbackHandler->addCatchCallback(function ($r) use (&$executedCallbacks) {
                $executedCallbacks[] = "callback1:$r";
            });
            
            $this->callbackHandler->addCatchCallback(function ($r) use (&$executedCallbacks) {
                $executedCallbacks[] = "callback2:$r";
            });
            
            $this->callbackHandler->executeCatchCallbacks($reason);
            
            expect($executedCallbacks)->toBe([
                "callback1:$reason",
                "callback2:$reason"
            ]);
        });

        it('should handle callback exceptions without stopping other callbacks', function () {
            $executedCallbacks = [];
            $reason = 'error';
            
            $this->callbackHandler->addCatchCallback(function () {
                throw new Exception('callback error');
            });
            
            $this->callbackHandler->addCatchCallback(function ($r) use (&$executedCallbacks) {
                $executedCallbacks[] = "executed:$r";
            });
            
            $this->callbackHandler->executeCatchCallbacks($reason);
            
            expect($executedCallbacks)->toBe(["executed:$reason"]);
        });
    });

    describe('finally callbacks', function () {
        it('should execute finally callbacks without parameters', function () {
            $executedCallbacks = [];
            
            $this->callbackHandler->addFinallyCallback(function () use (&$executedCallbacks) {
                $executedCallbacks[] = 'callback1';
            });
            
            $this->callbackHandler->addFinallyCallback(function () use (&$executedCallbacks) {
                $executedCallbacks[] = 'callback2';
            });
            
            $this->callbackHandler->executeFinallyCallbacks();
            
            expect($executedCallbacks)->toBe(['callback1', 'callback2']);
        });

        it('should handle callback exceptions without stopping other callbacks', function () {
            $executedCallbacks = [];
            
            $this->callbackHandler->addFinallyCallback(function () {
                throw new Exception('callback error');
            });
            
            $this->callbackHandler->addFinallyCallback(function () use (&$executedCallbacks) {
                $executedCallbacks[] = 'executed';
            });
            
            $this->callbackHandler->executeFinallyCallbacks();
            
            expect($executedCallbacks)->toBe(['executed']);
        });
    });

    describe('multiple callback types', function () {
        it('should handle different callback types independently', function () {
            $results = [];
            
            $this->callbackHandler->addThenCallback(function ($v) use (&$results) {
                $results['then'] = $v;
            });
            
            $this->callbackHandler->addCatchCallback(function ($r) use (&$results) {
                $results['catch'] = $r;
            });
            
            $this->callbackHandler->addFinallyCallback(function () use (&$results) {
                $results['finally'] = true;
            });
            
            // Execute only then and finally
            $this->callbackHandler->executeThenCallbacks('value');
            $this->callbackHandler->executeFinallyCallbacks();
            
            expect($results)->toBe([
                'then' => 'value',
                'finally' => true
            ]);
        });
    });
});
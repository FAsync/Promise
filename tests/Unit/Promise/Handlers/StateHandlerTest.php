<?php

use Hibla\Promise\Handlers\StateHandler;

beforeEach(function () {
    $this->stateHandler = new StateHandler();
});

describe('StateHandler', function () {
    describe('initial state', function () {
        it('should be pending initially', function () {
            expect($this->stateHandler->isPending())->toBeTrue()
                ->and($this->stateHandler->isResolved())->toBeFalse()
                ->and($this->stateHandler->isRejected())->toBeFalse()
            ;
        });

        it('should have null value and reason initially', function () {
            expect($this->stateHandler->getValue())->toBeNull()
                ->and($this->stateHandler->getReason())->toBeNull()
            ;
        });

        it('should be settleable initially', function () {
            expect($this->stateHandler->canSettle())->toBeTrue();
        });
    });

    describe('resolution', function () {
        it('should resolve with a value', function () {
            $value = 'test value';

            $this->stateHandler->resolve($value);

            expect($this->stateHandler->isResolved())->toBeTrue()
                ->and($this->stateHandler->isPending())->toBeFalse()
                ->and($this->stateHandler->isRejected())->toBeFalse()
                ->and($this->stateHandler->getValue())->toBe($value)
                ->and($this->stateHandler->canSettle())->toBeFalse()
            ;
        });

        it('should resolve with null value', function () {
            $this->stateHandler->resolve(null);

            expect($this->stateHandler->isResolved())->toBeTrue()
                ->and($this->stateHandler->getValue())->toBeNull()
            ;
        });

        it('should resolve with complex objects', function () {
            $value = (object) ['key' => 'value'];

            $this->stateHandler->resolve($value);

            expect($this->stateHandler->getValue())->toBe($value);
        });

        it('should ignore multiple resolution attempts', function () {
            $firstValue = 'first';
            $secondValue = 'second';

            $this->stateHandler->resolve($firstValue);
            $this->stateHandler->resolve($secondValue);

            expect($this->stateHandler->getValue())->toBe($firstValue);
        });
    });

    describe('rejection', function () {
        it('should reject with throwable reason', function () {
            $reason = new Exception('test error');

            $this->stateHandler->reject($reason);

            expect($this->stateHandler->isRejected())->toBeTrue()
                ->and($this->stateHandler->isPending())->toBeFalse()
                ->and($this->stateHandler->isResolved())->toBeFalse()
                ->and($this->stateHandler->getReason())->toBe($reason)
                ->and($this->stateHandler->canSettle())->toBeFalse()
            ;
        });

        it('should wrap string reason in Exception', function () {
            $reason = 'string error';

            $this->stateHandler->reject($reason);

            expect($this->stateHandler->isRejected())->toBeTrue()
                ->and($this->stateHandler->getReason())->toBeInstanceOf(Exception::class)
                ->and($this->stateHandler->getReason()->getMessage())->toBe($reason)
            ;
        });

        it('should wrap non-string reason in Exception', function () {
            $reason = ['array', 'error'];

            $this->stateHandler->reject($reason);

            expect($this->stateHandler->isRejected())->toBeTrue()
                ->and($this->stateHandler->getReason())->toBeInstanceOf(Exception::class)
                ->and($this->stateHandler->getReason()->getMessage())->toContain('Promise rejected with array:')
            ;
        });

        it('should handle object with toString method', function () {
            $reason = new class () {
                public function __toString(): string
                {
                    return 'custom error';
                }
            };

            $this->stateHandler->reject($reason);

            expect($this->stateHandler->getReason())->toBeInstanceOf(Exception::class)
                ->and($this->stateHandler->getReason()->getMessage())->toBe('custom error')
            ;
        });

        it('should ignore multiple rejection attempts', function () {
            $firstReason = new Exception('first error');
            $secondReason = new Exception('second error');

            $this->stateHandler->reject($firstReason);
            $this->stateHandler->reject($secondReason);

            expect($this->stateHandler->getReason())->toBe($firstReason);
        });

        it('should ignore rejection after resolution', function () {
            $value = 'resolved';
            $reason = new Exception('rejected');

            $this->stateHandler->resolve($value);
            $this->stateHandler->reject($reason);

            expect($this->stateHandler->isResolved())->toBeTrue()
                ->and($this->stateHandler->isRejected())->toBeFalse()
                ->and($this->stateHandler->getValue())->toBe($value)
            ;
        });

        it('should ignore resolution after rejection', function () {
            $reason = new Exception('rejected');
            $value = 'resolved';

            $this->stateHandler->reject($reason);
            $this->stateHandler->resolve($value);

            expect($this->stateHandler->isRejected())->toBeTrue()
                ->and($this->stateHandler->isResolved())->toBeFalse()
                ->and($this->stateHandler->getReason())->toBe($reason)
            ;
        });
    });
});

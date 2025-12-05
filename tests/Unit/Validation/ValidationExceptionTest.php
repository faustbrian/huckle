<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Huckle\Exceptions\ValidationException;

describe('ValidationException', function (): void {
    describe('fromErrors()', function (): void {
        test('creates exception with formatted message', function (): void {
            $errors = [
                [
                    'type' => 'country',
                    'label' => 'INVALID',
                    'message' => "Invalid country code 'INVALID'",
                    'path' => 'division:test > country:INVALID',
                ],
            ];

            $exception = ValidationException::fromErrors($errors);

            expect($exception)->toBeInstanceOf(ValidationException::class);
            expect($exception->getMessage())->toContain('HCL validation failed with 1 error(s)');
            expect($exception->getMessage())->toContain("Invalid country code 'INVALID'");
        });

        test('formats multiple errors', function (): void {
            $errors = [
                [
                    'type' => 'country',
                    'label' => 'XX',
                    'message' => "Invalid country code 'XX'",
                    'path' => 'division:test > country:XX',
                ],
                [
                    'type' => 'zone',
                    'label' => 'invalid',
                    'message' => "Invalid zone 'invalid'",
                    'path' => 'division:test > zone:invalid',
                ],
            ];

            $exception = ValidationException::fromErrors($errors);

            expect($exception->getMessage())->toContain('HCL validation failed with 2 error(s)');
        });
    });

    describe('errors()', function (): void {
        test('returns the original errors array', function (): void {
            $errors = [
                [
                    'type' => 'country',
                    'label' => 'XX',
                    'message' => "Invalid country code 'XX'",
                    'path' => 'division:test > country:XX',
                ],
            ];

            $exception = ValidationException::fromErrors($errors);

            expect($exception->errors())->toBe($errors);
        });
    });

    describe('messages()', function (): void {
        test('returns only the message strings', function (): void {
            $errors = [
                [
                    'type' => 'country',
                    'label' => 'XX',
                    'message' => "Invalid country code 'XX'",
                    'path' => 'division:test > country:XX',
                ],
                [
                    'type' => 'zone',
                    'label' => 'invalid',
                    'message' => "Invalid zone 'invalid'",
                    'path' => 'division:test > zone:invalid',
                ],
            ];

            $exception = ValidationException::fromErrors($errors);
            $messages = $exception->messages();

            expect($messages)->toHaveCount(2);
            expect($messages[0])->toBe("Invalid country code 'XX'");
            expect($messages[1])->toBe("Invalid zone 'invalid'");
        });
    });
});

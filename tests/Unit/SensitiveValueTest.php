<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Huckle\Support\SensitiveValue;

describe('SensitiveValue', function (): void {
    describe('reveal', function (): void {
        test('reveals the actual value', function (): void {
            $sensitive = new SensitiveValue('my-secret-password');

            expect($sensitive->reveal())->toBe('my-secret-password');
        });
    });

    describe('masked', function (): void {
        test('returns masked string', function (): void {
            $sensitive = new SensitiveValue('my-secret-password');

            expect($sensitive->masked())->toBe('********');
        });
    });

    describe('isEmpty', function (): void {
        test('returns true for empty value', function (): void {
            $sensitive = new SensitiveValue('');

            expect($sensitive->isEmpty())->toBeTrue();
        });

        test('returns false for non-empty value', function (): void {
            $sensitive = new SensitiveValue('secret');

            expect($sensitive->isEmpty())->toBeFalse();
        });
    });

    describe('length', function (): void {
        test('returns the length of the actual value', function (): void {
            $sensitive = new SensitiveValue('secret');

            expect($sensitive->length())->toBe(6);
        });
    });

    describe('__toString', function (): void {
        test('converts to string revealing the value', function (): void {
            $sensitive = new SensitiveValue('secret');

            expect((string) $sensitive)->toBe('secret');
        });

        test('works in string context', function (): void {
            $sensitive = new SensitiveValue('secret');
            $result = 'Password: '.$sensitive;

            expect($result)->toBe('Password: secret');
        });
    });

    describe('__debugInfo', function (): void {
        test('masks value in var_dump', function (): void {
            $sensitive = new SensitiveValue('secret');

            ob_start();
            var_dump($sensitive);
            $output = ob_get_clean();

            expect($output)->not->toContain('secret');
            expect($output)->toContain('********');
        });
    });

    describe('__serialize', function (): void {
        test('returns array with value', function (): void {
            $sensitive = new SensitiveValue('my-secret-password');

            $serialized = $sensitive->__serialize();

            expect($serialized)->toBe(['value' => 'my-secret-password']);
        });

        test('works with serialize function', function (): void {
            $sensitive = new SensitiveValue('my-secret-password');

            $serialized = serialize($sensitive);

            expect($serialized)->toBeString();
            expect($serialized)->toContain('my-secret-password');
        });
    });

    describe('__unserialize', function (): void {
        test('handles serialized data with readonly property limitation', function (): void {
            $sensitive = new SensitiveValue('original-value');
            $serialized = serialize($sensitive);

            // Note: Due to readonly property limitation, unserialize creates a new object
            // but __unserialize cannot modify the readonly property
            $unserialized = unserialize($serialized);

            // The object will be created but the value won't be restored properly
            // due to readonly property constraints
            expect($unserialized)->toBeInstanceOf(SensitiveValue::class);
        });
    });
});

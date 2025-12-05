<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Huckle\Exceptions\DirectoryNotFoundException;
use Cline\Huckle\Exceptions\EncryptionFailedException;
use Cline\Huckle\Exceptions\FileNotReadableException;
use Cline\Huckle\Exceptions\FileReadFailedException;
use Cline\Huckle\Exceptions\HuckleException;
use Cline\Huckle\Exceptions\ReadOnlyConfigurationException;

describe('DirectoryNotFoundException', function (): void {
    test('creates exception with formatted message', function (): void {
        $exception = DirectoryNotFoundException::forPath('/path/to/missing');

        expect($exception)->toBeInstanceOf(DirectoryNotFoundException::class);
        expect($exception)->toBeInstanceOf(HuckleException::class);
        expect($exception->getMessage())->toBe('Directory not found: "/path/to/missing"');
    });
});

describe('EncryptionFailedException', function (): void {
    test('creates exception with formatted message', function (): void {
        $exception = EncryptionFailedException::forPath('/path/to/config.hcl', 'Missing key');

        expect($exception)->toBeInstanceOf(EncryptionFailedException::class);
        expect($exception)->toBeInstanceOf(HuckleException::class);
        expect($exception->getMessage())->toBe('Failed to encrypt configuration file "/path/to/config.hcl": Missing key');
    });
});

describe('FileNotReadableException', function (): void {
    test('creates exception with formatted message', function (): void {
        $exception = FileNotReadableException::atPath('/path/to/unreadable');

        expect($exception)->toBeInstanceOf(FileNotReadableException::class);
        expect($exception)->toBeInstanceOf(HuckleException::class);
        expect($exception->getMessage())->toBe('File not readable: /path/to/unreadable');
    });
});

describe('FileReadFailedException', function (): void {
    test('creates exception with formatted message', function (): void {
        $exception = FileReadFailedException::atPath('/path/to/file');

        expect($exception)->toBeInstanceOf(FileReadFailedException::class);
        expect($exception)->toBeInstanceOf(HuckleException::class);
        expect($exception->getMessage())->toBe('Failed to read file: /path/to/file');
    });
});

describe('ReadOnlyConfigurationException', function (): void {
    test('creates exception with formatted message', function (): void {
        $exception = ReadOnlyConfigurationException::forPath('/path/to/readonly.hcl');

        expect($exception)->toBeInstanceOf(ReadOnlyConfigurationException::class);
        expect($exception)->toBeInstanceOf(HuckleException::class);
        expect($exception->getMessage())->toBe('Cannot write to file: "/path/to/readonly.hcl"');
    });
});

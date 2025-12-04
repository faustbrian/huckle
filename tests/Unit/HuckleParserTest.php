<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Huckle\Parser\HuckleParser;

describe('HuckleParser', function (): void {
    beforeEach(function (): void {
        $this->parser = new HuckleParser();
    });

    describe('parseFile()', function (): void {
        test('parses valid HCL file successfully', function (): void {
            $config = $this->parser->parseFile(testFixture('basic.hcl'));

            expect($config)->not->toBeNull();
            expect($config->credentials())->toHaveCount(5);
        });

        test('throws RuntimeException when file not found', function (): void {
            $nonExistentPath = testFixture('does-not-exist.hcl');

            expect(fn () => $this->parser->parseFile($nonExistentPath))
                ->toThrow(RuntimeException::class, 'File not found: '.$nonExistentPath);
        });

        test('throws RuntimeException when file not readable', function (): void {
            // Skip test when running as root (e.g., in Docker)
            if (posix_getuid() === 0) {
                $this->markTestSkipped('Test cannot run as root - root can read any file');
            }

            $unreadablePath = testFixture('unreadable.hcl');

            // Create a file and make it unreadable
            file_put_contents($unreadablePath, 'test');
            chmod($unreadablePath, 0o000);

            try {
                expect(fn () => $this->parser->parseFile($unreadablePath))
                    ->toThrow(RuntimeException::class, 'File not readable: '.$unreadablePath);
            } finally {
                // Cleanup: restore permissions and delete file
                chmod($unreadablePath, 0o644);
                unlink($unreadablePath);
            }
        });
    });

    describe('validate()', function (): void {
        test('returns valid true for valid HCL content', function (): void {
            $validContent = <<<'HCL'
            group "test" "development" {
              credential "simple" {
                key = "value"
              }
            }
            HCL;

            $result = $this->parser->validate($validContent);

            expect($result['valid'])->toBeTrue();
            expect($result['errors'])->toBeArray();
            expect($result['errors'])->toBeEmpty();
        });

        test('returns valid false with errors for invalid content', function (): void {
            $invalidContent = <<<'HCL'
            group "test" "development" {
              credential "broken" {
                key = "value"
              # Missing closing brace
            HCL;

            $result = $this->parser->validate($invalidContent);

            expect($result['valid'])->toBeFalse();
            expect($result['errors'])->toBeArray();
            expect($result['errors'])->not->toBeEmpty();
        });

        test('returns valid false for lexer errors', function (): void {
            // Content that causes lexer issues
            $invalidContent = 'group "test" { invalid @ syntax }';

            $result = $this->parser->validate($invalidContent);

            expect($result['valid'])->toBeFalse();
            expect($result['errors'])->toBeArray();
            expect($result['errors'])->not->toBeEmpty();
        });
    });

    describe('validateFile()', function (): void {
        test('returns valid true for valid file', function (): void {
            $result = $this->parser->validateFile(testFixture('valid-simple.hcl'));

            expect($result['valid'])->toBeTrue();
            expect($result['errors'])->toBeArray();
            expect($result['errors'])->toBeEmpty();
        });

        test('returns valid false for invalid file', function (): void {
            $result = $this->parser->validateFile(testFixture('invalid.hcl'));

            expect($result['valid'])->toBeFalse();
            expect($result['errors'])->toBeArray();
            expect($result['errors'])->not->toBeEmpty();
        });

        test('returns valid false for non-existent file', function (): void {
            $nonExistentPath = testFixture('does-not-exist.hcl');
            $result = $this->parser->validateFile($nonExistentPath);

            expect($result['valid'])->toBeFalse();
            expect($result['errors'])->toBeArray();
            expect($result['errors'])->not->toBeEmpty();
            expect($result['errors'][0])->toContain('File not found');
        });

        test('returns valid false for unreadable file', function (): void {
            // Skip test when running as root (e.g., in Docker)
            if (posix_getuid() === 0) {
                $this->markTestSkipped('Test cannot run as root - root can read any file');
            }

            $unreadablePath = testFixture('unreadable-validate.hcl');

            // Create a file and make it unreadable
            file_put_contents($unreadablePath, 'test');
            chmod($unreadablePath, 0o000);

            try {
                $result = $this->parser->validateFile($unreadablePath);

                expect($result['valid'])->toBeFalse();
                expect($result['errors'])->toBeArray();
                expect($result['errors'])->not->toBeEmpty();
                expect($result['errors'][0])->toContain('File not readable');
            } finally {
                // Cleanup: restore permissions and delete file
                chmod($unreadablePath, 0o644);
                unlink($unreadablePath);
            }
        });
    });

    describe('withGeoValidation()', function (): void {
        test('enables geo validation by default', function (): void {
            $contentWithInvalidCountry = <<<'HCL'
            division "test" {
              country "INVALID" {
                key = "value"
              }
            }
            HCL;

            $result = $this->parser->validate($contentWithInvalidCountry);

            expect($result['valid'])->toBeFalse();
            expect($result['errors'])->not->toBeEmpty();
        });

        test('can disable geo validation', function (): void {
            $contentWithInvalidCountry = <<<'HCL'
            division "test" {
              country "INVALID" {
                key = "value"
              }
            }
            HCL;

            $result = $this->parser->withGeoValidation(false)->validate($contentWithInvalidCountry);

            expect($result['valid'])->toBeTrue();
        });
    });

    describe('withoutGeoValidation()', function (): void {
        test('disables geo validation', function (): void {
            $contentWithInvalidCountry = <<<'HCL'
            division "test" {
              country "INVALID" {
                key = "value"
              }
            }
            HCL;

            $result = $this->parser->withoutGeoValidation()->validate($contentWithInvalidCountry);

            expect($result['valid'])->toBeTrue();
        });
    });

    describe('validateFile() with geo validation', function (): void {
        test('returns validation errors for invalid geo codes', function (): void {
            // Create a temp file with invalid country
            $tempFile = tempnam(sys_get_temp_dir(), 'hcl_');
            file_put_contents($tempFile, <<<'HCL'
            division "test" {
              country "INVALID" {
                key = "value"
              }
            }
            HCL);

            try {
                $result = $this->parser->validateFile($tempFile);

                expect($result['valid'])->toBeFalse();
                expect($result['errors'])->not->toBeEmpty();
            } finally {
                unlink($tempFile);
            }
        });
    });
});

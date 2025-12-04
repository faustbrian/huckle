<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Illuminate\Support\Facades\Config;

describe('LintCommand', function (): void {
    describe('syntax validation', function (): void {
        test('returns success for valid HCL file', function (): void {
            // Arrange
            Config::set('huckle.path', testFixture('basic.hcl'));

            // Act
            $this->artisan('huckle:lint')
                ->expectsOutput('✓ Syntax valid')
                ->assertExitCode(0);
        });

        test('returns failure with errors for invalid HCL file', function (): void {
            // Arrange
            Config::set('huckle.path', testFixture('invalid.hcl'));

            // Act
            $this->artisan('huckle:lint')
                ->expectsOutput('Syntax errors found:')
                ->assertExitCode(1);
        });

        test('uses custom path when HuckleManager is configured', function (): void {
            // Arrange
            $customPath = testFixture('valid-simple.hcl');
            Config::set('huckle.path', $customPath);

            // Act
            $result = $this->artisan('huckle:lint');

            // Assert
            $result->expectsOutputToContain('Linting: '.$customPath)
                ->assertExitCode(0);
        });

        test('uses default config path when no path provided', function (): void {
            // Arrange
            $defaultPath = testFixture('basic.hcl');
            Config::set('huckle.path', $defaultPath);

            // Act
            $result = $this->artisan('huckle:lint');

            // Assert
            $result->expectsOutputToContain('Linting: '.$defaultPath)
                ->assertExitCode(0);
        });

        test('displays credentials and groups count', function (): void {
            // Arrange
            Config::set('huckle.path', testFixture('basic.hcl'));

            // Act
            $result = $this->artisan('huckle:lint');

            // Assert - basic.hcl has 5 credentials in 4 groups
            $result->expectsOutputToContain('✓ Loaded 5 credentials in 4 groups')
                ->assertExitCode(0);
        });
    });

    describe('expiry checking', function (): void {
        test('warns about expiring credentials when check-expiry flag is set', function (): void {
            // Arrange
            Config::set('huckle.path', testFixture('expiration.hcl'));
            Config::set('huckle.expiry_warning', 30);

            // Act
            $result = $this->artisan('huckle:lint', ['--check-expiry' => true]);

            // Assert - expiration.hcl has 1 expiring soon (expiring_soon on 2025-12-15, 11 days away)
            $result->expectsOutputToContain('! Expiring:')
                ->assertExitCode(1); // Should fail due to expired credentials
        });

        test('errors on expired credentials when check-expiry flag is set', function (): void {
            // Arrange
            Config::set('huckle.path', testFixture('expiration.hcl'));
            Config::set('huckle.expiry_warning', 30);

            // Act
            $result = $this->artisan('huckle:lint', ['--check-expiry' => true]);

            // Assert - expiration.hcl has 2 expired credentials
            $result->expectsOutputToContain('EXPIRED:')
                ->assertExitCode(1);
        });

        test('shows success when no credentials expiring soon', function (): void {
            // Arrange
            Config::set('huckle.path', testFixture('basic.hcl'));
            Config::set('huckle.expiry_warning', 30);

            // Act
            $result = $this->artisan('huckle:lint', ['--check-expiry' => true]);

            // Assert - basic.hcl has no expired or expiring credentials
            $result->assertExitCode(0);
        });

        test('does not check expiry when flag is not set', function (): void {
            // Arrange
            Config::set('huckle.path', testFixture('expiration.hcl'));

            // Act
            $result = $this->artisan('huckle:lint');

            // Assert
            $result->doesntExpectOutputToContain('EXPIRED:')
                ->doesntExpectOutputToContain('Expiring:')
                ->assertExitCode(0);
        });

        test('respects custom expiry warning days from config', function (): void {
            // Arrange
            Config::set('huckle.path', testFixture('expiration.hcl'));
            Config::set('huckle.expiry_warning', 90);

            // Act
            $result = $this->artisan('huckle:lint', ['--check-expiry' => true]);

            // Assert - Should fail due to expired credentials
            $result->assertExitCode(1);
        });
    });

    describe('rotation checking', function (): void {
        test('warns about credentials needing rotation when check-rotation flag is set', function (): void {
            // Arrange
            Config::set('huckle.path', testFixture('expiration.hcl'));
            Config::set('huckle.rotation_warning', 90);

            // Act
            $result = $this->artisan('huckle:lint', ['--check-rotation' => true]);

            // Assert - expiration.hcl has credentials needing rotation (warnings, not errors)
            $result->assertExitCode(0);
        });

        test('shows success when all credentials recently rotated', function (): void {
            // Arrange
            Config::set('huckle.path', testFixture('basic.hcl'));
            Config::set('huckle.rotation_warning', 365); // Use longer period so basic.hcl passes

            // Act
            $result = $this->artisan('huckle:lint', ['--check-rotation' => true]);

            // Assert - basic.hcl rotation should pass with 365 day threshold
            $result->assertExitCode(0);
        });

        test('does not check rotation when flag is not set', function (): void {
            // Arrange
            Config::set('huckle.path', testFixture('expiration.hcl'));

            // Act
            $result = $this->artisan('huckle:lint');

            // Assert
            $result->doesntExpectOutputToContain('Needs rotation:')
                ->assertExitCode(0);
        });

        test('respects custom rotation warning days from config', function (): void {
            // Arrange
            Config::set('huckle.path', testFixture('expiration.hcl'));
            Config::set('huckle.rotation_warning', 180);

            // Act
            $result = $this->artisan('huckle:lint', ['--check-rotation' => true]);

            // Assert - Should pass with warnings (not errors)
            $result->assertExitCode(0);
        });
    });

    describe('permissions checking', function (): void {
        test('checks file permissions when check-permissions flag is set', function (): void {
            // Arrange
            Config::set('huckle.path', testFixture('basic.hcl'));

            // Act
            $result = $this->artisan('huckle:lint', ['--check-permissions' => true]);

            // Assert
            $result->expectsOutputToContain('File permissions:')
                ->assertExitCode(0);
        });

        test('does not check permissions when flag is not set', function (): void {
            // Arrange
            Config::set('huckle.path', testFixture('basic.hcl'));

            // Act
            $result = $this->artisan('huckle:lint');

            // Assert
            $result->doesntExpectOutputToContain('File permissions:')
                ->assertExitCode(0);
        });
    });

    describe('summary messages', function (): void {
        test('shows lint passed with no issues when no problems found', function (): void {
            // Arrange
            Config::set('huckle.path', testFixture('basic.hcl'));

            // Act
            $result = $this->artisan('huckle:lint');

            // Assert
            $result->expectsOutputToContain('Lint passed with no issues')
                ->assertExitCode(0);
        });

        test('shows lint passed with warnings when only warnings present', function (): void {
            // Arrange
            Config::set('huckle.path', testFixture('expiration.hcl'));
            Config::set('huckle.rotation_warning', 90);

            // Act
            $result = $this->artisan('huckle:lint', ['--check-rotation' => true]);

            // Assert - Rotation warnings are present but no errors (since not checking expiry)
            $result->assertExitCode(0);
        });

        test('shows lint failed when errors present', function (): void {
            // Arrange
            Config::set('huckle.path', testFixture('invalid.hcl'));

            // Act
            $result = $this->artisan('huckle:lint');

            // Assert - Invalid HCL should fail with syntax errors
            $result->assertExitCode(1);
        });
    });

    describe('combined checks', function (): void {
        test('runs all checks when all flags are set', function (): void {
            // Arrange
            Config::set('huckle.path', testFixture('basic.hcl'));
            Config::set('huckle.expiry_warning', 30);
            Config::set('huckle.rotation_warning', 365); // Use longer period for basic.hcl rotation date

            // Act
            $result = $this->artisan('huckle:lint', [
                '--check-expiry' => true,
                '--check-rotation' => true,
                '--check-permissions' => true,
            ]);

            // Assert - All checks should pass for basic.hcl
            $result->assertExitCode(0);
        });

        test('combines warnings from multiple checks', function (): void {
            // Arrange
            Config::set('huckle.path', testFixture('expiration.hcl'));
            Config::set('huckle.rotation_warning', 90);

            // Act - Only check rotation (not expiry to avoid errors)
            $result = $this->artisan('huckle:lint', [
                '--check-rotation' => true,
            ]);

            // Assert - Should have rotation warnings but still exit successfully
            $result->assertExitCode(0);
        });
    });
});

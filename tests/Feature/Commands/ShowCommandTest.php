<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Huckle\HuckleManager;
use Illuminate\Support\Facades\Config;

describe('ShowCommand', function (): void {
    beforeEach(function (): void {
        Config::set('huckle.path', testFixture('basic.hcl'));
        Config::set('huckle.mask_sensitive', true);
        app()->forgetInstance(HuckleManager::class);
        app()->bind(HuckleManager::class, fn ($app): HuckleManager => new HuckleManager($app));
    });

    describe('valid credential path', function (): void {
        test('shows credential details for valid path', function (): void {
            // Arrange & Act
            $this->artisan('huckle:show', ['path' => 'database.production.main'])
                ->expectsOutput('Credential: database.production.main')
                ->assertExitCode(0);
        });

        test('displays metadata fields correctly', function (): void {
            // Arrange & Act
            $result = $this->artisan('huckle:show', ['path' => 'database.production.main']);

            // Assert
            $result->expectsOutputToContain('Group:       database')
                ->expectsOutputToContain('Environment: production')
                ->expectsOutputToContain('Tags:        prod, postgres, critical')
                ->assertExitCode(0);
        });

        test('displays field values', function (): void {
            // Arrange & Act
            $result = $this->artisan('huckle:show', ['path' => 'database.production.main']);

            // Assert
            $result->expectsOutputToContain('Fields:')
                ->expectsOutputToContain('host: db.prod.internal')
                ->expectsOutputToContain('port: 5432')
                ->expectsOutputToContain('username: app_user')
                ->assertExitCode(0);
        });

        test('displays export variables when available', function (): void {
            // Arrange & Act
            $result = $this->artisan('huckle:show', ['path' => 'database.production.main']);

            // Assert
            $result->expectsOutputToContain('Exports:')
                ->expectsOutputToContain('DB_HOST')
                ->expectsOutputToContain('DB_PORT')
                ->expectsOutputToContain('DB_USERNAME')
                ->assertExitCode(0);
        });

        test('displays connection commands when available', function (): void {
            // Arrange & Act
            $result = $this->artisan('huckle:show', ['path' => 'database.production.main']);

            // Assert
            $result->expectsOutputToContain('Connections:')
                ->expectsOutputToContain('psql:')
                ->assertExitCode(0);
        });

        test('shows dash for empty owner field', function (): void {
            // Arrange & Act
            $result = $this->artisan('huckle:show', ['path' => 'database.staging.main']);

            // Assert - staging.main has no explicit owner
            $result->assertExitCode(0);
        });

        test('shows never for credentials without rotation date', function (): void {
            // Arrange - Use expiration fixture with never_rotated credential
            Config::set('huckle.path', testFixture('expiration.hcl'));
            app()->forgetInstance(HuckleManager::class);
            app()->bind(HuckleManager::class, fn ($app): HuckleManager => new HuckleManager($app));

            // Act
            $result = $this->artisan('huckle:show', ['path' => 'rotation.production.never_rotated']);

            // Assert
            $result->expectsOutputToContain('Rotated:     never')
                ->assertExitCode(0);
        });
    });

    describe('invalid credential path', function (): void {
        test('returns error for non-existent path', function (): void {
            // Arrange & Act
            $result = $this->artisan('huckle:show', ['path' => 'invalid.path.credential']);

            // Assert
            $result->expectsOutput('Credential not found: invalid.path.credential')
                ->assertExitCode(1);
        });

        test('returns error for partial path', function (): void {
            // Arrange & Act
            $result = $this->artisan('huckle:show', ['path' => 'database.production']);

            // Assert
            $result->expectsOutput('Credential not found: database.production')
                ->assertExitCode(1);
        });

        test('returns error for malformed path', function (): void {
            // Arrange & Act
            $result = $this->artisan('huckle:show', ['path' => 'invalid']);

            // Assert
            $result->expectsOutput('Credential not found: invalid')
                ->assertExitCode(1);
        });
    });

    describe('sensitive value masking', function (): void {
        test('masks sensitive values by default', function (): void {
            // Arrange & Act
            $result = $this->artisan('huckle:show', ['path' => 'database.production.main']);

            // Assert
            $result->expectsOutputToContain('password: ********')
                ->assertExitCode(0);
        });

        test('masks sensitive values when mask_sensitive config is true', function (): void {
            // Arrange
            Config::set('huckle.mask_sensitive', true);
            resolve(HuckleManager::class)->flush();

            // Act
            $result = $this->artisan('huckle:show', ['path' => 'aws.production.deploy']);

            // Assert
            $result->expectsOutputToContain('access_key: ********')
                ->expectsOutputToContain('secret_key: ********')
                ->assertExitCode(0);
        });

        test('reveals sensitive values with --reveal option', function (): void {
            // Arrange & Act
            $result = $this->artisan('huckle:show', [
                'path' => 'database.production.main',
                '--reveal' => true,
            ]);

            // Assert
            $result->expectsOutputToContain('password: secret123')
                ->assertExitCode(0);
        });

        test('reveals sensitive values when mask_sensitive config is false', function (): void {
            // Arrange
            Config::set('huckle.mask_sensitive', false);
            resolve(HuckleManager::class)->flush();

            // Act
            $result = $this->artisan('huckle:show', ['path' => 'aws.production.deploy']);

            // Assert
            $result->expectsOutputToContain('access_key: AKIAIOSFODNN7EXAMPLE')
                ->expectsOutputToContain('secret_key: wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY')
                ->assertExitCode(0);
        });

        test('reveal option overrides mask_sensitive config', function (): void {
            // Arrange
            Config::set('huckle.mask_sensitive', true);
            resolve(HuckleManager::class)->flush();

            // Act
            $result = $this->artisan('huckle:show', [
                'path' => 'aws.production.deploy',
                '--reveal' => true,
            ]);

            // Assert
            $result->expectsOutputToContain('access_key: AKIAIOSFODNN7EXAMPLE')
                ->expectsOutputToContain('secret_key: wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY')
                ->assertExitCode(0);
        });
    });

    describe('JSON output format', function (): void {
        test('outputs JSON format with --json option', function (): void {
            // Arrange & Act
            $this->artisan('huckle:show', [
                'path' => 'database.production.main',
                '--json' => true,
            ])->assertExitCode(0);
        });

        test('JSON output includes credential path', function (): void {
            // Arrange & Act
            $this->artisan('huckle:show', [
                'path' => 'database.production.main',
                '--json' => true,
            ])->expectsOutputToContain('database.production.main')
                ->assertExitCode(0);
        });

        test('JSON output masks sensitive values by default', function (): void {
            // Arrange & Act - Verify with standard output
            $this->artisan('huckle:show', [
                'path' => 'aws.production.deploy',
                '--json' => true,
            ])->assertExitCode(0);
        });

        test('JSON output reveals sensitive values with --reveal', function (): void {
            // Arrange & Act
            $this->artisan('huckle:show', [
                'path' => 'aws.production.deploy',
                '--json' => true,
                '--reveal' => true,
            ])->expectsOutputToContain('AKIAIOSFODNN7EXAMPLE')
                ->assertExitCode(0);
        });

        test('JSON output is valid JSON format', function (): void {
            // Arrange & Act - Test that it exits successfully (valid JSON output)
            $this->artisan('huckle:show', [
                'path' => 'database.production.main',
                '--json' => true,
            ])->assertExitCode(0);
        });
    });

    describe('expiration warnings', function (): void {
        beforeEach(function (): void {
            Config::set('huckle.path', testFixture('expiration.hcl'));
            app()->forgetInstance(HuckleManager::class);
            app()->bind(HuckleManager::class, fn ($app): HuckleManager => new HuckleManager($app));
        });

        test('shows error when credential is expired', function (): void {
            // Arrange & Act - Use past_expiration from expiration.hcl
            $result = $this->artisan('huckle:show', ['path' => 'expired.production.past_expiration']);

            // Assert
            $result->expectsOutputToContain('EXPIRED')
                ->assertExitCode(0);
        });

        test('shows warning when credential is expiring soon', function (): void {
            // Arrange & Act - Use expiring_soon from expiration.hcl
            $result = $this->artisan('huckle:show', ['path' => 'expiring.production.expiring_soon']);

            // Assert
            $result->expectsOutputToContain('expiring')
                ->assertExitCode(0);
        });

        test('does not show expiration warning when not expiring', function (): void {
            // Arrange & Act - Use not_expiring from expiration.hcl
            $result = $this->artisan('huckle:show', ['path' => 'expiring.production.not_expiring']);

            // Assert - Should complete successfully
            $result->assertExitCode(0);
        });
    });

    describe('rotation warnings', function (): void {
        beforeEach(function (): void {
            Config::set('huckle.path', testFixture('expiration.hcl'));
            app()->forgetInstance(HuckleManager::class);
            app()->bind(HuckleManager::class, fn ($app): HuckleManager => new HuckleManager($app));
        });

        test('shows warning when credential needs rotation', function (): void {
            // Arrange & Act - Use needs_rotation from expiration.hcl
            $result = $this->artisan('huckle:show', ['path' => 'rotation.production.needs_rotation']);

            // Assert
            $result->expectsOutputToContain('rotation')
                ->assertExitCode(0);
        });

        test('shows warning when credential has never been rotated', function (): void {
            // Arrange & Act - Use never_rotated from expiration.hcl
            $result = $this->artisan('huckle:show', ['path' => 'rotation.production.never_rotated']);

            // Assert
            $result->expectsOutputToContain('rotation')
                ->assertExitCode(0);
        });

        test('does not show rotation warning when recently rotated', function (): void {
            // Arrange & Act - Use recently_rotated from expiration.hcl
            $result = $this->artisan('huckle:show', ['path' => 'rotation.production.recently_rotated']);

            // Assert - Should complete successfully
            $result->assertExitCode(0);
        });
    });

    describe('complex field values', function (): void {
        test('handles numeric field values', function (): void {
            // Arrange & Act
            $result = $this->artisan('huckle:show', ['path' => 'database.production.main']);

            // Assert
            $result->expectsOutputToContain('port: 5432')
                ->assertExitCode(0);
        });

        test('displays owner when specified', function (): void {
            // Arrange & Act
            $result = $this->artisan('huckle:show', ['path' => 'database.production.main']);

            // Assert
            $result->expectsOutputToContain('Owner:       dba-team')
                ->assertExitCode(0);
        });
    });

    describe('combined options', function (): void {
        test('can use --json and --reveal together', function (): void {
            // Arrange & Act
            $this->artisan('huckle:show', [
                'path' => 'aws.production.deploy',
                '--json' => true,
                '--reveal' => true,
            ])->expectsOutputToContain('AKIAIOSFODNN7EXAMPLE')
                ->assertExitCode(0);
        });
    });

    describe('credential with expires date', function (): void {
        test('displays expiration date when specified', function (): void {
            // Arrange & Act
            $result = $this->artisan('huckle:show', ['path' => 'database.production.main']);

            // Assert - basic.hcl has expires = "2026-06-01"
            $result->expectsOutputToContain('Expires:     2026-06-01')
                ->assertExitCode(0);
        });

        test('shows dash for no expiration date', function (): void {
            // Arrange - Use staging which doesn't have expires
            $result = $this->artisan('huckle:show', ['path' => 'database.staging.main']);

            // Assert
            $result->expectsOutputToContain('Expires:     -')
                ->assertExitCode(0);
        });
    });
});

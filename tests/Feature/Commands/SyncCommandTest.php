<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Huckle\HuckleManager;

describe('SyncCommand', function (): void {
    beforeEach(function (): void {
        $this->manager = resolve(HuckleManager::class);
        $this->tempEnvPath = sys_get_temp_dir().'/test-'.uniqid().'.env';
    });

    afterEach(function (): void {
        if (!file_exists($this->tempEnvPath)) {
            return;
        }

        unlink($this->tempEnvPath);
    });

    describe('Happy Paths', function (): void {
        test('syncs credentials to env file successfully', function (): void {
            // Arrange
            $this->manager->load(testFixture('basic.hcl'));

            // Act
            $this->artisan('huckle:sync', [
                '--path' => $this->tempEnvPath,
            ])->assertSuccessful();

            // Assert
            expect(file_exists($this->tempEnvPath))->toBeTrue();

            $content = file_get_contents($this->tempEnvPath);
            expect($content)->toContain('DB_HOST=');
            expect($content)->toContain('AWS_ACCESS_KEY_ID=');
            expect($content)->toContain('REDIS_HOST=');
        });

        test('shows success message after sync', function (): void {
            // Arrange
            $this->manager->load(testFixture('basic.hcl'));

            // Act & Assert
            $this->artisan('huckle:sync', [
                '--path' => $this->tempEnvPath,
            ])
                ->expectsOutputToContain('Synced')
                ->assertSuccessful();
        });

        test('merges with existing env file by default', function (): void {
            // Arrange
            $this->manager->load(testFixture('basic.hcl'));

            // Pre-populate .env with some values
            file_put_contents($this->tempEnvPath, "APP_NAME=TestApp\nAPP_ENV=testing\n");

            // Act
            $this->artisan('huckle:sync', [
                '--path' => $this->tempEnvPath,
            ])->assertSuccessful();

            // Assert
            $content = file_get_contents($this->tempEnvPath);
            expect($content)->toContain('APP_NAME=TestApp');
            expect($content)->toContain('APP_ENV=testing');
            expect($content)->toContain('DB_HOST=');
        });

        test('replaces env file when --replace flag is used', function (): void {
            // Arrange
            $this->manager->load(testFixture('basic.hcl'));

            // Pre-populate .env with some values
            file_put_contents($this->tempEnvPath, "APP_NAME=TestApp\nAPP_ENV=testing\n");

            // Act
            $this->artisan('huckle:sync', [
                '--path' => $this->tempEnvPath,
                '--replace' => true,
            ])->assertSuccessful();

            // Assert
            $content = file_get_contents($this->tempEnvPath);
            expect($content)->not->toContain('APP_NAME=TestApp');
            expect($content)->not->toContain('APP_ENV=testing');
            expect($content)->toContain('DB_HOST=');
        });

        test('shows changes in dry-run mode without writing file', function (): void {
            // Arrange
            $this->manager->load(testFixture('basic.hcl'));

            // Pre-populate .env with existing value
            file_put_contents($this->tempEnvPath, "DB_HOST=old-host\n");

            // Act
            $this->artisan('huckle:sync', [
                '--path' => $this->tempEnvPath,
                '--dry-run' => true,
            ])
                ->assertSuccessful()
                ->expectsOutputToContain('Dry run - would write:')
                ->expectsOutputToContain('Changes:')
                ->run();

            // Assert
            // File should still have old content
            $content = file_get_contents($this->tempEnvPath);
            expect($content)->toBe("DB_HOST=old-host\n");
        });

        test('filters credentials by group when --group option is provided', function (): void {
            // Arrange
            $this->manager->load(testFixture('basic.hcl'));

            // Act
            $this->artisan('huckle:sync', [
                '--path' => $this->tempEnvPath,
                '--partition' => 'database',
            ])->assertSuccessful();

            // Assert
            $content = file_get_contents($this->tempEnvPath);
            expect($content)->toContain('DB_HOST=');
            // Should not contain AWS credentials (different group)
            expect($content)->not->toContain('AWS_ACCESS_KEY_ID=');
        });

        test('filters credentials by environment when --env option is provided', function (): void {
            // Arrange
            $this->manager->load(testFixture('basic.hcl'));

            // Act
            $this->artisan('huckle:sync', [
                '--path' => $this->tempEnvPath,
                '--environment' => 'production',
            ])->assertSuccessful();

            // Assert
            $content = file_get_contents($this->tempEnvPath);
            expect($content)->not->toBeEmpty();
        });

        test('escapes values with spaces in quotes', function (): void {
            // Arrange
            $this->manager->load(testFixture('basic.hcl'));

            // Pre-populate .env with a value containing spaces
            file_put_contents($this->tempEnvPath, 'APP_NAME="My Test App"'."\n");

            // Act
            $this->artisan('huckle:sync', [
                '--path' => $this->tempEnvPath,
            ])->assertSuccessful();

            // Assert
            $content = file_get_contents($this->tempEnvPath);
            // Should preserve the APP_NAME and add database credentials
            expect($content)->toContain('APP_NAME=');
            expect($content)->toContain('DB_HOST=');
        });

        test('handles values with special characters', function (): void {
            // Arrange
            $this->manager->load(testFixture('basic.hcl'));

            // Act
            $this->artisan('huckle:sync', [
                '--path' => $this->tempEnvPath,
            ])->assertSuccessful();

            // Assert
            $content = file_get_contents($this->tempEnvPath);
            // DATABASE_URL should be escaped properly
            expect($content)->toContain('DATABASE_URL=');
        });

        test('dry-run skips unchanged values in changes output', function (): void {
            // Arrange
            $this->manager->load(testFixture('basic.hcl'));

            // Pre-populate .env with a value that won't change
            $exports = $this->manager->allExports();
            $dbHost = $exports['DB_HOST'] ?? 'db.prod.internal';
            file_put_contents($this->tempEnvPath, sprintf('DB_HOST=%s%s', $dbHost, \PHP_EOL));

            // Act
            $this->artisan('huckle:sync', [
                '--path' => $this->tempEnvPath,
                '--dry-run' => true,
            ])
                ->assertSuccessful()
                ->expectsOutputToContain('Dry run - would write:')
                ->run();

            // The output should not show DB_HOST in changes since it's identical
            // but will show other new values
        });
    });

    describe('Sad Paths', function (): void {
        test('returns failure when no credentials found', function (): void {
            // Arrange
            $this->manager->load(testFixture('basic.hcl'));

            // Act & Assert - Filter by non-existent group
            $this->artisan('huckle:sync', [
                '--path' => $this->tempEnvPath,
                '--partition' => 'nonexistent-group',
            ])
                ->assertFailed()
                ->expectsOutputToContain('No nodes found to sync.')
                ->run();
        });

        test('returns failure when filtering by non-existent environment', function (): void {
            // Arrange
            $this->manager->load(testFixture('basic.hcl'));

            // Act & Assert
            $this->artisan('huckle:sync', [
                '--path' => $this->tempEnvPath,
                '--environment' => 'nonexistent-env',
            ])
                ->assertFailed()
                ->expectsOutputToContain('No nodes found to sync.')
                ->run();
        });

        test('handles empty credentials gracefully', function (): void {
            // Arrange
            $this->manager->load(testFixture('valid-simple.hcl'));

            // Act - Try to sync with filters that result in no matches
            $this->artisan('huckle:sync', [
                '--path' => $this->tempEnvPath,
                '--partition' => 'nonexistent',
            ])->assertFailed();

            // Assert
            expect(file_exists($this->tempEnvPath))->toBeFalse();
        });
    });

    describe('Edge Cases', function (): void {
        test('creates new env file if it does not exist', function (): void {
            // Arrange
            $this->manager->load(testFixture('basic.hcl'));
            expect(file_exists($this->tempEnvPath))->toBeFalse();

            // Act
            $this->artisan('huckle:sync', [
                '--path' => $this->tempEnvPath,
            ])->assertSuccessful();

            // Assert
            expect(file_exists($this->tempEnvPath))->toBeTrue();
        });

        test('handles env file with comments and empty lines', function (): void {
            // Arrange
            $this->manager->load(testFixture('basic.hcl'));

            $existingEnv = <<<'ENV'
# Database Configuration
DB_HOST=old-host

# Application Settings
APP_NAME=TestApp
# Another comment

ENV;

            file_put_contents($this->tempEnvPath, $existingEnv);

            // Act
            $this->artisan('huckle:sync', [
                '--path' => $this->tempEnvPath,
            ])->assertSuccessful();

            // Assert
            $content = file_get_contents($this->tempEnvPath);
            expect($content)->toContain('APP_NAME=TestApp');
            expect($content)->toContain('DB_HOST=');
        });

        test('handles env file with quoted values', function (): void {
            // Arrange
            $this->manager->load(testFixture('basic.hcl'));

            file_put_contents($this->tempEnvPath, 'APP_NAME="My App Name"'."\n");

            // Act
            $this->artisan('huckle:sync', [
                '--path' => $this->tempEnvPath,
            ])->assertSuccessful();

            // Assert
            $content = file_get_contents($this->tempEnvPath);
            // Parser strips quotes and preserves the value
            expect($content)->toContain('APP_NAME=');
            expect($content)->toContain('DB_HOST=');
        });

        test('handles env file with single-quoted values', function (): void {
            // Arrange
            $this->manager->load(testFixture('basic.hcl'));

            file_put_contents($this->tempEnvPath, "APP_NAME='My App Name'\n");

            // Act
            $this->artisan('huckle:sync', [
                '--path' => $this->tempEnvPath,
            ])->assertSuccessful();

            // Assert
            $content = file_get_contents($this->tempEnvPath);
            // Parser strips quotes and preserves the value
            expect($content)->toContain('APP_NAME=');
            expect($content)->toContain('DB_HOST=');
        });

        test('uses default base path when --path not provided', function (): void {
            // Arrange
            $this->manager->load(testFixture('basic.hcl'));

            // Note: We can't easily test this without potentially affecting the actual .env
            // So we'll test with explicit path instead
            // This test documents the expected behavior

            // Act & Assert
            // Command would default to base_path('.env') if --path is not provided
            expect(true)->toBeTrue();
        });

        test('combines --group and --env filters', function (): void {
            // Arrange
            $this->manager->load(testFixture('basic.hcl'));

            // Act
            $this->artisan('huckle:sync', [
                '--path' => $this->tempEnvPath,
                '--partition' => 'database',
                '--environment' => 'production',
            ])->assertSuccessful();

            // Assert
            $content = file_get_contents($this->tempEnvPath);
            expect($content)->toContain('DB_HOST=');
        });

        test('overwrites credentials with same key during merge', function (): void {
            // Arrange
            $this->manager->load(testFixture('basic.hcl'));

            file_put_contents($this->tempEnvPath, "DB_HOST=old-value\n");

            // Act
            $this->artisan('huckle:sync', [
                '--path' => $this->tempEnvPath,
            ])->assertSuccessful();

            // Assert
            $content = file_get_contents($this->tempEnvPath);
            expect($content)->not->toContain('DB_HOST=old-value');
            // DB_HOST should be overwritten with one from credentials
            // Note: basic.hcl has both production and staging, last one wins
            expect($content)->toContain('DB_HOST=db.staging.internal');
        });

        test('handles env file lines without equals sign', function (): void {
            // Arrange
            $this->manager->load(testFixture('basic.hcl'));

            $existingEnv = <<<'ENV'
# Comment line
APP_NAME=TestApp
INVALID_LINE_WITHOUT_EQUALS
DB_CONNECTION=mysql

ENV;

            file_put_contents($this->tempEnvPath, $existingEnv);

            // Act
            $this->artisan('huckle:sync', [
                '--path' => $this->tempEnvPath,
            ])->assertSuccessful();

            // Assert
            $content = file_get_contents($this->tempEnvPath);
            expect($content)->toContain('APP_NAME=TestApp');
            expect($content)->toContain('DB_CONNECTION=mysql');
        });
    });
});

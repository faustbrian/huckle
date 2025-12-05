<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Huckle\HuckleManager;
use Illuminate\Support\Facades\Config;

describe('ExportCommand', function (): void {
    beforeEach(function (): void {
        Config::set('huckle.path', testFixture('basic.hcl'));
        $this->manager = resolve(HuckleManager::class);
        $this->manager->flush()->load();
    });

    describe('exports specific credential path', function (): void {
        test('exports credentials in dotenv format by default', function (): void {
            // Arrange & Act
            $this->artisan('huckle:export', ['path' => 'database.production.main'])
                ->expectsOutputToContain('DB_HOST=db.prod.internal')
                ->assertExitCode(0);
        });

        test('exports credentials with special characters properly escaped', function (): void {
            // Arrange
            Config::set('huckle.path', testFixture('special-chars.hcl'));
            $this->manager->flush()->load();

            // Act & Assert
            $this->artisan('huckle:export', ['path' => 'test.production.special'])
                ->expectsOutputToContain('PASSWORD="my pass#word"')
                ->assertExitCode(0);
        });

        test('returns failure for non-existent credential path', function (): void {
            // Arrange & Act
            $this->artisan('huckle:export', ['path' => 'nonexistent.path.here'])
                ->expectsOutputToContain('No nodes found matching the criteria.')
                ->assertExitCode(1);
        });
    });

    describe('exports with format option', function (): void {
        test('exports credentials in json format', function (): void {
            // Arrange & Act & Assert
            $this->artisan('huckle:export', [
                'path' => 'database.production.main',
                '--format' => 'json',
            ])
                ->assertExitCode(0);
        });

        test('exports credentials in shell format', function (): void {
            // Arrange & Act
            $result = $this->artisan('huckle:export', [
                'path' => 'database.production.main',
                '--format' => 'shell',
            ]);

            // Assert
            $result->expectsOutputToContain("export DB_HOST='db.prod.internal'")
                ->assertExitCode(0);
        });

        test('exports credentials in dotenv format when explicitly specified', function (): void {
            // Arrange & Act
            $result = $this->artisan('huckle:export', [
                'path' => 'database.production.main',
                '--format' => 'dotenv',
            ]);

            // Assert
            $result->expectsOutputToContain('DB_HOST=db.prod.internal')
                ->assertExitCode(0);
        });
    });

    describe('exports all credentials with filters', function (): void {
        test('exports all credentials when no path provided', function (): void {
            // Arrange & Act & Assert
            $this->artisan('huckle:export')
                ->expectsOutputToContain('DB_HOST')
                ->assertExitCode(0);
        });

        test('filters credentials by group', function (): void {
            // Arrange & Act & Assert
            $this->artisan('huckle:export', [
                '--partition' => 'database',
            ])
                ->expectsOutputToContain('DB_HOST')
                ->doesntExpectOutputToContain('AWS_ACCESS_KEY_ID')
                ->assertExitCode(0);
        });

        test('filters credentials by environment', function (): void {
            // Arrange & Act & Assert
            $this->artisan('huckle:export', [
                '--env' => 'production',
            ])
                ->expectsOutputToContain('DB_HOST')
                ->assertExitCode(0);
        });

        test('filters credentials by single tag', function (): void {
            // Arrange & Act & Assert
            $this->artisan('huckle:export', [
                '--tag' => ['prod'],
            ])
                ->expectsOutputToContain('DB_HOST')
                ->assertExitCode(0);
        });

        test('filters credentials by multiple tags', function (): void {
            // Arrange & Act & Assert
            $this->artisan('huckle:export', [
                '--tag' => ['prod', 'postgres'],
            ])
                ->expectsOutputToContain('DB_HOST')
                ->assertExitCode(0);
        });

        test('combines multiple filters', function (): void {
            // Arrange & Act & Assert
            $this->artisan('huckle:export', [
                '--partition' => 'database',
                '--env' => 'production',
                '--tag' => ['prod'],
            ])
                ->expectsOutputToContain('DB_HOST')
                ->assertExitCode(0);
        });
    });

    describe('error cases', function (): void {
        test('shows warning when no credentials match filters', function (): void {
            // Arrange & Act
            $this->artisan('huckle:export', [
                '--partition' => 'nonexistent-group',
            ])
                ->expectsOutput('No nodes found matching the criteria.')
                ->assertExitCode(1);
        });

        test('shows warning when tag filter matches nothing', function (): void {
            // Arrange & Act
            $this->artisan('huckle:export', [
                '--tag' => ['nonexistent-tag'],
            ])
                ->expectsOutput('No nodes found matching the criteria.')
                ->assertExitCode(1);
        });

        test('shows warning when environment filter matches nothing', function (): void {
            // Arrange & Act
            $this->artisan('huckle:export', [
                '--env' => 'nonexistent-env',
            ])
                ->expectsOutput('No nodes found matching the criteria.')
                ->assertExitCode(1);
        });
    });

    describe('value escaping', function (): void {
        test('escapes values with spaces in dotenv format', function (): void {
            // Arrange
            Config::set('huckle.path', testFixture('special-chars.hcl'));
            $this->manager->flush()->load();

            // Act & Assert
            $this->artisan('huckle:export', [
                'path' => 'test.production.spaces',
                '--format' => 'dotenv',
            ])
                ->expectsOutputToContain('"')
                ->assertExitCode(0);
        });

        test('escapes values with quotes in dotenv format', function (): void {
            // Arrange
            Config::set('huckle.path', testFixture('special-chars.hcl'));
            $this->manager->flush()->load();

            // Act & Assert
            $this->artisan('huckle:export', [
                'path' => 'test.production.quotes',
                '--format' => 'dotenv',
            ])
                ->expectsOutputToContain('\\')
                ->assertExitCode(0);
        });

        test('escapes single quotes in shell format', function (): void {
            // Arrange
            Config::set('huckle.path', testFixture('special-chars.hcl'));
            $this->manager->flush()->load();

            // Act & Assert
            $this->artisan('huckle:export', [
                'path' => 'test.production.single-quote',
                '--format' => 'shell',
            ])
                ->expectsOutputToContain("'\\''")
                ->assertExitCode(0);
        });

        test('handles values with hash symbols in dotenv format', function (): void {
            // Arrange
            Config::set('huckle.path', testFixture('special-chars.hcl'));
            $this->manager->flush()->load();

            // Act & Assert
            $this->artisan('huckle:export', [
                'path' => 'test.production.hash',
                '--format' => 'dotenv',
            ])
                ->expectsOutputToContain('"')
                ->assertExitCode(0);
        });

        test('does not escape simple values without special characters', function (): void {
            // Arrange & Act & Assert
            $this->artisan('huckle:export', [
                'path' => 'database.production.main',
                '--format' => 'dotenv',
            ])
                ->expectsOutputToContain('DB_HOST=db.prod.internal')
                ->assertExitCode(0);
        });
    });
});

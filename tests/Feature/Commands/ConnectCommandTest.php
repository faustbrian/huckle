<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Huckle\HuckleManager;

describe('ConnectCommand', function (): void {
    beforeEach(function (): void {
        $this->manager = resolve(HuckleManager::class);
    });

    describe('lists available connections', function (): void {
        test('lists connections when --list option is used', function (): void {
            $this->artisan('huckle:connect', [
                'path' => 'database.production.main',
                '--list' => true,
            ])
                ->expectsOutput('Available connections for database.production.main:')
                ->expectsOutputToContain('psql:')
                ->assertSuccessful();
        });

        test('lists connections when no connection name is provided', function (): void {
            $this->artisan('huckle:connect', [
                'path' => 'database.production.main',
            ])
                ->expectsOutput('Available connections for database.production.main:')
                ->expectsOutputToContain('psql:')
                ->assertSuccessful();
        });

        test('shows connection command in list output', function (): void {
            $this->artisan('huckle:connect', [
                'path' => 'database.production.main',
                '--list' => true,
            ])
                ->expectsOutputToContain('psql -h db.prod.internal')
                ->assertSuccessful();
        });
    });

    describe('error handling', function (): void {
        test('returns error for invalid path', function (): void {
            $this->artisan('huckle:connect', [
                'path' => 'nonexistent.path',
            ])
                ->expectsOutput('Node not found: nonexistent.path')
                ->assertFailed();
        });

        test('returns error for non-existent connection name', function (): void {
            $this->artisan('huckle:connect', [
                'path' => 'database.production.main',
                'connection' => 'nonexistent',
            ])
                ->expectsOutput("Connection 'nonexistent' not found for: database.production.main")
                ->expectsOutput('Available connections: psql')
                ->assertFailed();
        });

        test('returns warning when no connections are defined', function (): void {
            $this->artisan('huckle:connect', [
                'path' => 'database.production.readonly',
                '--list' => true,
            ])
                ->expectsOutput('No connections defined for: database.production.readonly')
                ->assertFailed();
        });
    });

    describe('shows correct connection command', function (): void {
        test('displays correct psql connection command', function (): void {
            $this->artisan('huckle:connect', [
                'path' => 'database.production.main',
                'connection' => 'psql',
                '--copy' => true,
            ])
                ->expectsOutputToContain('psql -h db.prod.internal -p 5432 -U app_user -d myapp_production')
                ->assertSuccessful();
        });

        test('displays correct redis-cli connection command and resolves self references', function (): void {
            $this->artisan('huckle:connect', [
                'path' => 'redis.production.cache',
                'connection' => 'redis-cli',
                '--copy' => true,
            ])
                ->expectsOutputToContain('redis-cli -h redis.prod.internal -p 6379')
                ->assertSuccessful();
        });
    });

    describe('copy to clipboard option', function (): void {
        test('shows message when copying to clipboard', function (): void {
            $this->artisan('huckle:connect', [
                'path' => 'database.production.main',
                'connection' => 'psql',
                '--copy' => true,
            ])
                ->expectsOutputToContain('Command copied to clipboard:')
                ->assertSuccessful();
        });

        test('displays full command when copying', function (): void {
            $this->artisan('huckle:connect', [
                'path' => 'database.production.main',
                'connection' => 'psql',
                '--copy' => true,
            ])
                ->expectsOutputToContain('Command copied to clipboard: psql -h db.prod.internal')
                ->assertSuccessful();
        });
    });

    describe('multiple connections per credential', function (): void {
        test('lists all available connection types', function (): void {
            // In basic.hcl, database.production.main has psql connection
            // redis.production.cache has redis-cli connection
            $this->artisan('huckle:connect', [
                'path' => 'database.production.main',
                '--list' => true,
            ])
                ->expectsOutputToContain('psql:')
                ->assertSuccessful();

            $this->artisan('huckle:connect', [
                'path' => 'redis.production.cache',
                '--list' => true,
            ])
                ->expectsOutputToContain('redis-cli:')
                ->assertSuccessful();
        });
    });
});

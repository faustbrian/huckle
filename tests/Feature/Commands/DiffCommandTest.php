<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

describe('DiffCommand', function (): void {
    describe('formatted output', function (): void {
        test('shows credentials only in first environment', function (): void {
            $this->artisan('huckle:diff', ['env1' => 'production', 'env2' => 'staging'])
                ->expectsOutput('Comparing environments: production vs staging')
                ->expectsOutputToContain('Only in production:')
                ->expectsOutputToContain('+ database.readonly')
                ->expectsOutputToContain('+ aws.deploy')
                ->expectsOutputToContain('+ redis.cache')
                ->assertSuccessful();
        });

        test('shows credentials only in second environment', function (): void {
            $this->artisan('huckle:diff', ['env1' => 'staging', 'env2' => 'production'])
                ->expectsOutput('Comparing environments: staging vs production')
                ->expectsOutputToContain('Only in production:')
                ->expectsOutputToContain('+ database.readonly')
                ->expectsOutputToContain('+ aws.deploy')
                ->expectsOutputToContain('+ redis.cache')
                ->assertSuccessful();
        });

        test('shows field differences between environments', function (): void {
            $this->artisan('huckle:diff', ['env1' => 'production', 'env2' => 'staging'])
                ->expectsOutput('Comparing environments: production vs staging')
                ->expectsOutputToContain('Field differences:')
                ->expectsOutputToContain('database.main:')
                ->expectsOutputToContain('host:')
                ->expectsOutputToContain('production: db.prod.internal')
                ->expectsOutputToContain('staging: db.staging.internal')
                ->expectsOutputToContain('password:')
                ->expectsOutputToContain('production: secret123')
                ->expectsOutputToContain('staging: staging_secret')
                ->expectsOutputToContain('database:')
                ->expectsOutputToContain('production: myapp_production')
                ->expectsOutputToContain('staging: myapp_staging')
                ->assertSuccessful();
        });

        test('shows differences summary', function (): void {
            $this->artisan('huckle:diff', ['env1' => 'production', 'env2' => 'staging'])
                ->expectsOutput('Comparing environments: production vs staging')
                ->expectsOutputToContain('Only in production:')
                ->expectsOutputToContain('Field differences:')
                ->assertSuccessful();
        });

        test('compares same environment against itself', function (): void {
            $this->artisan('huckle:diff', ['env1' => 'production', 'env2' => 'production'])
                ->expectsOutput('Comparing environments: production vs production')
                ->expectsOutput('✓ Environments are identical')
                ->assertSuccessful();
        });
    });

    describe('json output', function (): void {
        test('outputs JSON format with --json option', function (): void {
            $this->artisan('huckle:diff', [
                'env1' => 'production',
                'env2' => 'staging',
                '--json' => true,
            ])
                ->assertSuccessful();
        });

        test('json output contains structured data', function (): void {
            $this->artisan('huckle:diff', [
                'env1' => 'production',
                'env2' => 'staging',
                '--json' => true,
            ])
                ->expectsOutputToContain('only_in')
                ->assertSuccessful();
        });

        test('json output includes credentials from environments', function (): void {
            $this->artisan('huckle:diff', [
                'env1' => 'production',
                'env2' => 'staging',
                '--json' => true,
            ])
                ->expectsOutputToContain('database')
                ->assertSuccessful();
        });
    });

    describe('edge cases', function (): void {
        test('handles non-existent environments gracefully', function (): void {
            $this->artisan('huckle:diff', ['env1' => 'production', 'env2' => 'nonexistent'])
                ->expectsOutput('Comparing environments: production vs nonexistent')
                ->expectsOutputToContain('Only in production:')
                ->assertSuccessful();
        });
    });
});

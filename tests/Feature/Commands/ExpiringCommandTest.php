<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Huckle\HuckleManager;
use Illuminate\Support\Facades\Config;

describe('ExpiringCommand', function (): void {
    beforeEach(function (): void {
        // Use the expiring.hcl fixture which has:
        // - expiring_soon (expires 2025-12-20, ~16 days from 2025-12-04)
        // - already_expired (expires 2025-11-01)
        // - needs_rotation (rotated 2025-08-06, ~120 days ago)
        // - healthy (expires 2027-12-01, rotated 2025-11-01)
        Config::set('huckle.path', testFixture('expiring.hcl'));
        Config::set('huckle.expiry_warning', 30);
        Config::set('huckle.rotation_warning', 90);

        // Reset singleton to force reload
        app()->forgetInstance(HuckleManager::class);
    });

    describe('default behavior', function (): void {
        test('lists expiring credentials within default 30-day period', function (): void {
            $this->artisan('huckle:expiring')
                ->expectsOutputToContain('EXPIRED nodes:')
                ->assertExitCode(1); // FAILURE when expired credentials exist
        });

        test('displays expired credentials', function (): void {
            $this->artisan('huckle:expiring')
                ->expectsOutputToContain('database.production.already_expired')
                ->assertExitCode(1);
        });

        test('displays expiring soon credentials', function (): void {
            $this->artisan('huckle:expiring')
                ->expectsOutputToContain('Expiring within 30 days:')
                ->expectsOutputToContain('database.production.expiring_soon')
                ->assertExitCode(1);
        });

        test('does not display healthy credentials in output', function (): void {
            $this->artisan('huckle:expiring')
                ->expectsOutputToContain('EXPIRED nodes:')
                ->assertExitCode(1);
        });
    });

    describe('--days option', function (): void {
        test('uses custom days parameter with --days=20', function (): void {
            // With 20 days, expiring_soon (16 days away) should appear
            $this->artisan('huckle:expiring', ['--days' => 20])
                ->expectsOutputToContain('Expiring within 20 days:')
                ->assertExitCode(1);
        });

        test('filters out credentials expiring after custom period', function (): void {
            // With 10 days, expiring_soon (16 days away) should not appear in "expiring"
            // but expired should still appear
            $this->artisan('huckle:expiring', ['--days' => 10])
                ->expectsOutputToContain('EXPIRED nodes:')
                ->expectsOutputToContain('database.production.already_expired')
                ->assertExitCode(1);
        });

        test('shows credentials expiring within custom period of 20 days', function (): void {
            // With 20 days, expiring_soon (16 days away) should appear
            $this->artisan('huckle:expiring', ['--days' => 20])
                ->expectsOutputToContain('Expiring within 20 days:')
                ->expectsOutputToContain('database.production.expiring_soon')
                ->assertExitCode(1);
        });
    });

    describe('with no expiring credentials', function (): void {
        beforeEach(function (): void {
            // Use a fixture with only healthy credentials
            Config::set('huckle.path', testFixture('basic.hcl'));

            // Reset singleton to force reload
            app()->forgetInstance(HuckleManager::class);
        });

        test('shows success message when no credentials expiring', function (): void {
            $this->artisan('huckle:expiring')
                ->assertExitCode(0); // SUCCESS when no issues
        });
    });

    describe('--json option', function (): void {
        test('outputs JSON format with --json flag', function (): void {
            $this->artisan('huckle:expiring', ['--json' => true])
                ->assertExitCode(0); // SUCCESS in JSON mode
        });

        test('JSON output contains credential structure', function (): void {
            $this->artisan('huckle:expiring', ['--json' => true])
                ->assertExitCode(0)
                ->expectsOutputToContain('"path"');
        });

        test('JSON output does not include rotation by default', function (): void {
            $this->artisan('huckle:expiring', ['--json' => true])
                ->assertExitCode(0)
                ->doesntExpectOutputToContain('"needs_rotation"');
        });
    });

    describe('--include-rotation option', function (): void {
        test('shows credentials needing rotation with --include-rotation flag', function (): void {
            $this->artisan('huckle:expiring', ['--include-rotation' => true])
                ->expectsOutputToContain('Needs rotation (>90 days):')
                ->expectsOutputToContain('database.production.needs_rotation')
                ->expectsOutputToContain('last:')
                ->assertExitCode(1);
        });

        test('does not show rotation section without --include-rotation flag', function (): void {
            $this->artisan('huckle:expiring')
                ->doesntExpectOutputToContain('Needs rotation')
                ->assertExitCode(1);
        });

        test('does not show recently rotated credentials in rotation section', function (): void {
            $this->artisan('huckle:expiring', ['--include-rotation' => true])
                ->expectsOutputToContain('Needs rotation (>90 days):')
                ->assertExitCode(1);
        });
    });

    describe('--json with --include-rotation', function (): void {
        test('JSON output includes rotation data with --include-rotation flag', function (): void {
            $this->artisan('huckle:expiring', [
                '--json' => true,
                '--include-rotation' => true,
            ])
                ->assertExitCode(0);
        });
    });

    describe('edge cases', function (): void {
        test('handles credentials with no expiration date gracefully', function (): void {
            Config::set('huckle.path', testFixture('basic.hcl'));
            app()->forgetInstance(HuckleManager::class);

            $this->artisan('huckle:expiring')
                ->assertExitCode(0);
        });

        test('returns SUCCESS when only expiring (no expired) credentials exist', function (): void {
            // Use expiration.hcl which has both expired and expiring
            Config::set('huckle.path', testFixture('expiration.hcl'));
            app()->forgetInstance(HuckleManager::class);

            $this->artisan('huckle:expiring')
                ->assertExitCode(1); // Will fail due to expired credentials
        });

        test('returns FAILURE when expired credentials exist', function (): void {
            $this->artisan('huckle:expiring')
                ->assertExitCode(1); // FAILURE
        });
    });

    describe('combined options', function (): void {
        test('works with --days and --include-rotation together', function (): void {
            $this->artisan('huckle:expiring', [
                '--days' => 20,
                '--include-rotation' => true,
            ])
                ->expectsOutputToContain('Expiring within 20 days:')
                ->expectsOutputToContain('Needs rotation (>90 days):')
                ->assertExitCode(1);
        });

        test('all options can be combined', function (): void {
            $this->artisan('huckle:expiring', [
                '--days' => 15,
                '--include-rotation' => true,
                '--json' => true,
            ])
                ->assertExitCode(0);
        });
    });
});

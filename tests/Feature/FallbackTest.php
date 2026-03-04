<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Huckle\HuckleManager;
use Cline\Huckle\Parser\HuckleParser;

describe('Fallback', function (): void {
    beforeEach(function (): void {
        // Clear env vars before each test
        $vars = [
            'SERVICE_A_KEY', 'SERVICE_A_SECRET', 'SERVICE_B_API_KEY',
            'SHARED_SERVICE_API_KEY', 'PROVIDER_FI_CUSTOMER_NUMBER', 'PROVIDER_FI_API_KEY',
            'PROVIDER_SE_CUSTOMER_NUMBER', 'PROVIDER_SE_API_KEY',
        ];

        foreach ($vars as $var) {
            putenv($var);
            unset($_ENV[$var], $_SERVER[$var]);
        }
    });

    describe('HuckleParser', function (): void {
        test('parses fallback blocks from HCL file', function (): void {
            $parser = new HuckleParser();
            $config = $parser->parseFile(__DIR__.'/../Fixtures/fallback.hcl');

            expect($config->fallbacks())->toHaveCount(1);
            expect($config->fallback('default'))->not->toBeNull();
        });

        test('parses both partitions and fallbacks', function (): void {
            $parser = new HuckleParser();
            $config = $parser->parseFile(__DIR__.'/../Fixtures/fallback.hcl');

            expect($config->partitions())->toHaveCount(2);
            expect($config->fallbacks())->toHaveCount(1);
            expect($config->partition('FI'))->not->toBeNull();
            expect($config->partition('SE'))->not->toBeNull();
        });

        test('fallback has environments', function (): void {
            $parser = new HuckleParser();
            $config = $parser->parseFile(__DIR__.'/../Fixtures/fallback.hcl');

            $fallback = $config->fallback('default');

            // In unified model, environments are children of the fallback node
            $envNames = array_keys($fallback->children);
            expect($envNames)->toContain('production');
            expect($envNames)->toContain('staging');
            expect($envNames)->toContain('local');
            expect($envNames)->toContain('sandbox');
        });
    });

    describe('exportsForContext', function (): void {
        test('returns fallback exports when no partition matches', function (): void {
            $parser = new HuckleParser();
            $config = $parser->parseFile(__DIR__.'/../Fixtures/fallback.hcl');

            // Request non-existent partition - should still get fallback exports
            $exports = $config->exportsForContext([
                'partition' => 'NO',
                'environment' => 'production',
                'provider' => 'service_a',
            ]);

            expect($exports)->toBe([
                'SERVICE_A_KEY' => 'pk_test_default',
                'SERVICE_A_SECRET' => 'sk_test_default',
            ]);
        });

        test('merges fallback exports with partition exports', function (): void {
            $parser = new HuckleParser();
            $config = $parser->parseFile(__DIR__.'/../Fixtures/fallback.hcl');

            // SE partition + fallback providers
            $exports = $config->exportsForContext([
                'partition' => 'SE',
                'environment' => 'production',
            ]);

            // Should have fallback exports (service_a, service_b, shared_service)
            expect($exports)->toHaveKey('SERVICE_A_KEY');
            expect($exports)->toHaveKey('SERVICE_A_SECRET');
            expect($exports)->toHaveKey('SERVICE_B_API_KEY');
            expect($exports)->toHaveKey('SHARED_SERVICE_API_KEY');

            // Should also have SE-specific exports
            expect($exports)->toHaveKey('PROVIDER_SE_CUSTOMER_NUMBER');
            expect($exports)->toHaveKey('PROVIDER_SE_API_KEY');

            // Values should be from fallback for shared services
            expect($exports['SERVICE_A_KEY'])->toBe('pk_test_default');
            expect($exports['SERVICE_B_API_KEY'])->toBe('default-service-b-key');
            expect($exports['SHARED_SERVICE_API_KEY'])->toBe('shared-default-key');

            // Values should be from partition for partition-specific
            expect($exports['PROVIDER_SE_CUSTOMER_NUMBER'])->toBe('67890-SE');
        });

        test('partition exports override fallback exports with same key', function (): void {
            $parser = new HuckleParser();
            $config = $parser->parseFile(__DIR__.'/../Fixtures/fallback.hcl');

            // FI partition overrides shared_service
            $exports = $config->exportsForContext([
                'partition' => 'FI',
                'environment' => 'production',
            ]);

            // SHARED_SERVICE_API_KEY should be overridden by FI
            expect($exports['SHARED_SERVICE_API_KEY'])->toBe('fi-specific-key');

            // Other fallback exports should remain
            expect($exports['SERVICE_A_KEY'])->toBe('pk_test_default');
            expect($exports['SERVICE_B_API_KEY'])->toBe('default-service-b-key');

            // FI-specific exports should be present
            expect($exports['PROVIDER_FI_CUSTOMER_NUMBER'])->toBe('12345-FI');
        });

        test('fallback exports match environment context', function (): void {
            $parser = new HuckleParser();
            $config = $parser->parseFile(__DIR__.'/../Fixtures/fallback.hcl');

            // Request staging environment
            $exports = $config->exportsForContext([
                'partition' => 'SE',
                'environment' => 'staging',
                'provider' => 'service_a',
            ]);

            // Should get staging fallback values
            expect($exports['SERVICE_A_KEY'])->toBe('pk_test_staging');
            expect($exports['SERVICE_A_SECRET'])->toBe('sk_test_staging');
        });

        test('fallback works with multiple environment labels', function (): void {
            $parser = new HuckleParser();
            $config = $parser->parseFile(__DIR__.'/../Fixtures/fallback.hcl');

            // All these environments should get same fallback values
            $localExports = $config->exportsForContext([
                'partition' => 'SE',
                'environment' => 'local',
                'provider' => 'service_a',
            ]);

            $sandboxExports = $config->exportsForContext([
                'partition' => 'SE',
                'environment' => 'sandbox',
                'provider' => 'service_a',
            ]);

            expect($localExports)->toBe($sandboxExports);
            expect($localExports['SERVICE_A_KEY'])->toBe('pk_test_staging');
        });
    });

    describe('HuckleManager::loadEnv()', function (): void {
        test('loads fallback and partition exports together', function (): void {
            $manager = resolve(HuckleManager::class);
            $exports = $manager->loadEnv(__DIR__.'/../Fixtures/fallback.hcl', [
                'partition' => 'SE',
                'environment' => 'production',
            ]);

            // Should have both fallback and partition exports
            expect($exports)->toHaveKey('SERVICE_A_KEY');
            expect($exports)->toHaveKey('PROVIDER_SE_CUSTOMER_NUMBER');

            // Env vars should be set
            expect(getenv('SERVICE_A_KEY'))->toBe('pk_test_default');
            expect(getenv('PROVIDER_SE_CUSTOMER_NUMBER'))->toBe('67890-SE');
        });

        test('partition overrides in env vars', function (): void {
            $manager = resolve(HuckleManager::class);
            $manager->loadEnv(__DIR__.'/../Fixtures/fallback.hcl', [
                'partition' => 'FI',
                'environment' => 'production',
            ]);

            // Override should be applied
            expect(getenv('SHARED_SERVICE_API_KEY'))->toBe('fi-specific-key');

            // Fallback values should still be present
            expect(getenv('SERVICE_A_KEY'))->toBe('pk_test_default');
        });

        test('returns fallback exports when partition does not match', function (): void {
            $manager = resolve(HuckleManager::class);
            $exports = $manager->loadEnv(__DIR__.'/../Fixtures/fallback.hcl', [
                'partition' => 'NONEXISTENT',
                'environment' => 'production',
            ]);

            // Should still get fallback exports
            expect($exports)->toHaveKey('SERVICE_A_KEY');
            expect(getenv('SERVICE_A_KEY'))->toBe('pk_test_default');
        });
    });
});

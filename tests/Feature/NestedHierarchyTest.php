<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Huckle\HuckleManager;
use Cline\Huckle\Parser\HuckleParser;

describe('Nested Hierarchy', function (): void {
    beforeEach(function (): void {
        // Clear env vars before each test
        $envVars = [
            'PROVIDER_A_USERNAME',
            'PROVIDER_A_PASSWORD',
            'PROVIDER_A_CUSTOMER_NUMBER',
            'PROVIDER_B_API_KEY',
            'PROVIDER_B_CUSTOMER_NUMBER',
            'PROVIDER_C_USERNAME',
            'PROVIDER_C_PASSWORD',
            'PROVIDER_C_BEARER_TOKEN',
            'PROVIDER_C_BASE_URL',
            'PROVIDER_D_API_UID',
            'PROVIDER_D_API_KEY',
            'PROVIDER_D_CUSTOMER_NUMBER',
        ];

        foreach ($envVars as $var) {
            putenv($var);
            unset($_ENV[$var], $_SERVER[$var]);
        }
    });

    describe('HuckleParser', function (): void {
        test('parses nested hierarchy HCL file', function (): void {
            $parser = new HuckleParser();
            $config = $parser->parseFile(__DIR__.'/../Fixtures/nested-hierarchy.hcl');

            // Divisions are now partitions in the unified model
            expect($config->partitions())->toHaveCount(3);
            expect($config->partition('FI'))->not->toBeNull();
            expect($config->partition('SE'))->not->toBeNull();
            expect($config->partition('EE'))->not->toBeNull();
        });

        test('division has environments', function (): void {
            $parser = new HuckleParser();
            $config = $parser->parseFile(__DIR__.'/../Fixtures/nested-hierarchy.hcl');

            $division = $config->partition('FI');

            // In unified model, environments are children of the partition
            $envNames = \array_keys($division->children);
            expect($envNames)->toContain('production');
            expect($envNames)->toContain('staging');
        });
    });

    describe('exportsForContext', function (): void {
        test('returns provider-level exports for division/environment/provider context', function (): void {
            $parser = new HuckleParser();
            $config = $parser->parseFile(__DIR__.'/../Fixtures/nested-hierarchy.hcl');

            $exports = $config->exportsForContext([
                'division' => 'FI',
                'environment' => 'production',
                'provider' => 'provider_b',
            ]);

            expect($exports)->toBe([
                'PROVIDER_B_API_KEY' => 'provider-b-fi-prod-key',
                'PROVIDER_B_CUSTOMER_NUMBER' => 'provider-b-fi-prod-customer',
            ]);
        });

        test('returns provider-level exports plus country-level exports when country specified', function (): void {
            $parser = new HuckleParser();
            $config = $parser->parseFile(__DIR__.'/../Fixtures/nested-hierarchy.hcl');

            $exports = $config->exportsForContext([
                'division' => 'FI',
                'environment' => 'production',
                'provider' => 'provider_a',
                'country' => 'EE',
            ]);

            expect($exports)->toBe([
                'PROVIDER_A_USERNAME' => 'provider-a-fi-prod-user',
                'PROVIDER_A_PASSWORD' => 'provider-a-fi-prod-pass',
                'PROVIDER_A_CUSTOMER_NUMBER' => 'provider-a-ee-customer',
            ]);
        });

        test('returns different country exports based on country context', function (): void {
            $parser = new HuckleParser();
            $config = $parser->parseFile(__DIR__.'/../Fixtures/nested-hierarchy.hcl');

            $exportsLV = $config->exportsForContext([
                'division' => 'FI',
                'environment' => 'production',
                'provider' => 'provider_a',
                'country' => 'LV',
            ]);

            expect($exportsLV['PROVIDER_A_CUSTOMER_NUMBER'])->toBe('provider-a-lv-customer');

            $exportsLT = $config->exportsForContext([
                'division' => 'FI',
                'environment' => 'production',
                'provider' => 'provider_a',
                'country' => 'LT',
            ]);

            expect($exportsLT['PROVIDER_A_CUSTOMER_NUMBER'])->toBe('provider-a-lt-customer');
        });

        test('returns only provider-level exports when no country specified', function (): void {
            $parser = new HuckleParser();
            $config = $parser->parseFile(__DIR__.'/../Fixtures/nested-hierarchy.hcl');

            $exports = $config->exportsForContext([
                'division' => 'FI',
                'environment' => 'production',
                'provider' => 'provider_a',
            ]);

            // Should include provider exports but also all country exports (since no country filter)
            expect($exports)->toHaveKey('PROVIDER_A_USERNAME');
            expect($exports)->toHaveKey('PROVIDER_A_PASSWORD');
            expect($exports)->toHaveKey('PROVIDER_A_CUSTOMER_NUMBER'); // Last country wins
        });

        test('returns empty when division does not match', function (): void {
            $parser = new HuckleParser();
            $config = $parser->parseFile(__DIR__.'/../Fixtures/nested-hierarchy.hcl');

            $exports = $config->exportsForContext([
                'division' => 'NONEXISTENT',
                'environment' => 'production',
            ]);

            expect($exports)->toBe([]);
        });

        test('returns empty when environment does not match', function (): void {
            $parser = new HuckleParser();
            $config = $parser->parseFile(__DIR__.'/../Fixtures/nested-hierarchy.hcl');

            $exports = $config->exportsForContext([
                'division' => 'FI',
                'environment' => 'nonexistent',
            ]);

            expect($exports)->toBe([]);
        });

        test('returns empty when provider does not match', function (): void {
            $parser = new HuckleParser();
            $config = $parser->parseFile(__DIR__.'/../Fixtures/nested-hierarchy.hcl');

            $exports = $config->exportsForContext([
                'division' => 'FI',
                'environment' => 'production',
                'provider' => 'nonexistent',
            ]);

            expect($exports)->toBe([]);
        });

        test('returns staging exports when staging environment specified', function (): void {
            $parser = new HuckleParser();
            $config = $parser->parseFile(__DIR__.'/../Fixtures/nested-hierarchy.hcl');

            $exports = $config->exportsForContext([
                'division' => 'FI',
                'environment' => 'staging',
                'provider' => 'provider_b',
            ]);

            expect($exports)->toBe([
                'PROVIDER_B_API_KEY' => 'provider-b-fi-staging-key',
                'PROVIDER_B_CUSTOMER_NUMBER' => 'provider-b-fi-staging-customer',
            ]);
        });

        test('returns SE division exports', function (): void {
            $parser = new HuckleParser();
            $config = $parser->parseFile(__DIR__.'/../Fixtures/nested-hierarchy.hcl');

            $exports = $config->exportsForContext([
                'division' => 'SE',
                'environment' => 'production',
                'provider' => 'provider_d',
            ]);

            expect($exports)->toBe([
                'PROVIDER_D_API_UID' => 'provider-d-se-prod-uid',
                'PROVIDER_D_API_KEY' => 'provider-d-se-prod-key',
                'PROVIDER_D_CUSTOMER_NUMBER' => 'provider-d-se-prod-customer',
            ]);
        });

        test('returns provider_c exports with country-specific bearer token', function (): void {
            $parser = new HuckleParser();
            $config = $parser->parseFile(__DIR__.'/../Fixtures/nested-hierarchy.hcl');

            $exports = $config->exportsForContext([
                'division' => 'FI',
                'environment' => 'production',
                'provider' => 'provider_c',
                'country' => 'EE',
            ]);

            expect($exports)->toBe([
                'PROVIDER_C_USERNAME' => 'provider-c-global-user',
                'PROVIDER_C_PASSWORD' => 'provider-c-global-pass',
                'PROVIDER_C_BEARER_TOKEN' => 'provider-c-ee-token',
                'PROVIDER_C_BASE_URL' => 'https://example.com/provider-c/ee',
            ]);
        });

        test('returns different provider_c bearer token for different country', function (): void {
            $parser = new HuckleParser();
            $config = $parser->parseFile(__DIR__.'/../Fixtures/nested-hierarchy.hcl');

            $exportsLT = $config->exportsForContext([
                'division' => 'FI',
                'environment' => 'production',
                'provider' => 'provider_c',
                'country' => 'LT',
            ]);

            expect($exportsLT['PROVIDER_C_BEARER_TOKEN'])->toBe('provider-c-lt-token');
            expect($exportsLT['PROVIDER_C_BASE_URL'])->toBe('https://example.com/provider-c/lt');

            $exportsLV = $config->exportsForContext([
                'division' => 'FI',
                'environment' => 'production',
                'provider' => 'provider_c',
                'country' => 'LV',
            ]);

            expect($exportsLV['PROVIDER_C_BEARER_TOKEN'])->toBe('provider-c-lv-token');
            expect($exportsLV['PROVIDER_C_BASE_URL'])->toBe('https://example.com/provider-c/lv');
        });
    });

    describe('HuckleManager::loadEnv()', function (): void {
        test('loads FI production provider_b and sets env vars', function (): void {
            $manager = resolve(HuckleManager::class);
            $exports = $manager->loadEnv(__DIR__.'/../Fixtures/nested-hierarchy.hcl', [
                'division' => 'FI',
                'environment' => 'production',
                'provider' => 'provider_b',
            ]);

            expect($exports)->toBe([
                'PROVIDER_B_API_KEY' => 'provider-b-fi-prod-key',
                'PROVIDER_B_CUSTOMER_NUMBER' => 'provider-b-fi-prod-customer',
            ]);

            expect(getenv('PROVIDER_B_API_KEY'))->toBe('provider-b-fi-prod-key');
            expect(getenv('PROVIDER_B_CUSTOMER_NUMBER'))->toBe('provider-b-fi-prod-customer');
            expect($_ENV['PROVIDER_B_API_KEY'])->toBe('provider-b-fi-prod-key');
            expect($_SERVER['PROVIDER_B_API_KEY'])->toBe('provider-b-fi-prod-key');
        });

        test('loads FI production provider_a with EE country and sets env vars', function (): void {
            $manager = resolve(HuckleManager::class);
            $exports = $manager->loadEnv(__DIR__.'/../Fixtures/nested-hierarchy.hcl', [
                'division' => 'FI',
                'environment' => 'production',
                'provider' => 'provider_a',
                'country' => 'EE',
            ]);

            expect($exports)->toBe([
                'PROVIDER_A_USERNAME' => 'provider-a-fi-prod-user',
                'PROVIDER_A_PASSWORD' => 'provider-a-fi-prod-pass',
                'PROVIDER_A_CUSTOMER_NUMBER' => 'provider-a-ee-customer',
            ]);

            expect(getenv('PROVIDER_A_USERNAME'))->toBe('provider-a-fi-prod-user');
            expect(getenv('PROVIDER_A_PASSWORD'))->toBe('provider-a-fi-prod-pass');
            expect(getenv('PROVIDER_A_CUSTOMER_NUMBER'))->toBe('provider-a-ee-customer');
        });

        test('loads SE production provider_d and sets env vars', function (): void {
            $manager = resolve(HuckleManager::class);
            $exports = $manager->loadEnv(__DIR__.'/../Fixtures/nested-hierarchy.hcl', [
                'division' => 'SE',
                'environment' => 'production',
                'provider' => 'provider_d',
            ]);

            expect(getenv('PROVIDER_D_API_UID'))->toBe('provider-d-se-prod-uid');
            expect(getenv('PROVIDER_D_API_KEY'))->toBe('provider-d-se-prod-key');
            expect(getenv('PROVIDER_D_CUSTOMER_NUMBER'))->toBe('provider-d-se-prod-customer');
        });

        test('loads FI staging provider_b with different values than production', function (): void {
            $manager = resolve(HuckleManager::class);

            $prodExports = $manager->loadEnv(__DIR__.'/../Fixtures/nested-hierarchy.hcl', [
                'division' => 'FI',
                'environment' => 'production',
                'provider' => 'provider_b',
            ]);

            $manager->flush();

            $stagingExports = $manager->loadEnv(__DIR__.'/../Fixtures/nested-hierarchy.hcl', [
                'division' => 'FI',
                'environment' => 'staging',
                'provider' => 'provider_b',
            ]);

            expect($prodExports['PROVIDER_B_API_KEY'])->toBe('provider-b-fi-prod-key');
            expect($stagingExports['PROVIDER_B_API_KEY'])->toBe('provider-b-fi-staging-key');
        });
    });
});

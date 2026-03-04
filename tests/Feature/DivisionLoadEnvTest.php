<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Huckle\HuckleManager;
use Cline\Huckle\Parser\HuckleParser;

describe('Division loadEnv', function (): void {
    beforeEach(function (): void {
        // Clear env vars before each test
        putenv('SERVICE_A_CUSTOMER_NUMBER');
        putenv('SERVICE_A_API_KEY');
        unset($_ENV['SERVICE_A_CUSTOMER_NUMBER'], $_ENV['SERVICE_A_API_KEY'], $_SERVER['SERVICE_A_CUSTOMER_NUMBER'], $_SERVER['SERVICE_A_API_KEY']);
    });

    describe('HuckleParser', function (): void {
        test('parses division blocks from HCL file', function (): void {
            $parser = new HuckleParser();
            $config = $parser->parseFile(__DIR__.'/../Fixtures/divisions.hcl');

            // Divisions are now partitions in the unified model
            expect($config->partitions())->toHaveCount(3);
            expect($config->partition('FI'))->not->toBeNull();
            expect($config->partition('SE'))->not->toBeNull();
            expect($config->partition('EE'))->not->toBeNull();
        });

        test('division has environments', function (): void {
            $parser = new HuckleParser();
            $config = $parser->parseFile(__DIR__.'/../Fixtures/divisions.hcl');

            $division = $config->partition('FI');

            // In the unified model, environments are children of the partition
            expect(array_keys($division->children))->toContain('production');
        });
    });

    describe('HuckleConfig', function (): void {
        test('matching returns only nodes matching context', function (): void {
            $parser = new HuckleParser();
            $config = $parser->parseFile(__DIR__.'/../Fixtures/divisions.hcl');

            // 'division' context key maps to 'partition' in the unified model
            $matching = $config->matching(['partition' => 'FI']);

            expect($matching)->not->toBeEmpty();
            $matching->each(fn ($n) => expect($n->path[0])->toBe('FI'));
        });

        test('exportsForContext returns resolved exports for matching division', function (): void {
            $parser = new HuckleParser();
            $config = $parser->parseFile(__DIR__.'/../Fixtures/divisions.hcl');

            $exports = $config->exportsForContext([
                'partition' => 'SE',
                'environment' => 'production',
                'provider' => 'service_a',
            ]);

            expect($exports)->toBe([
                'SERVICE_A_CUSTOMER_NUMBER' => '67890-SE',
                'SERVICE_A_API_KEY' => 'se-secret-key',
            ]);
        });

        test('exportsForContext returns empty array when no division matches', function (): void {
            $parser = new HuckleParser();
            $config = $parser->parseFile(__DIR__.'/../Fixtures/divisions.hcl');

            $exports = $config->exportsForContext(['partition' => 'NO']);

            expect($exports)->toBe([]);
        });
    });

    describe('HuckleManager::loadEnv()', function (): void {
        test('loads FI division and sets env vars', function (): void {
            $manager = resolve(HuckleManager::class);
            $exports = $manager->loadEnv(__DIR__.'/../Fixtures/divisions.hcl', [
                'partition' => 'FI',
                'environment' => 'production',
                'provider' => 'service_a',
            ]);

            expect($exports)->toBe([
                'SERVICE_A_CUSTOMER_NUMBER' => '12345-FI',
                'SERVICE_A_API_KEY' => 'fi-secret-key',
            ]);

            expect(getenv('SERVICE_A_CUSTOMER_NUMBER'))->toBe('12345-FI');
            expect(getenv('SERVICE_A_API_KEY'))->toBe('fi-secret-key');
            expect($_ENV['SERVICE_A_CUSTOMER_NUMBER'])->toBe('12345-FI');
            expect($_SERVER['SERVICE_A_CUSTOMER_NUMBER'])->toBe('12345-FI');
        });

        test('loads SE division and sets env vars', function (): void {
            $manager = resolve(HuckleManager::class);
            $exports = $manager->loadEnv(__DIR__.'/../Fixtures/divisions.hcl', [
                'partition' => 'SE',
                'environment' => 'production',
                'provider' => 'service_a',
            ]);

            expect($exports)->toBe([
                'SERVICE_A_CUSTOMER_NUMBER' => '67890-SE',
                'SERVICE_A_API_KEY' => 'se-secret-key',
            ]);

            expect(getenv('SERVICE_A_CUSTOMER_NUMBER'))->toBe('67890-SE');
            expect(getenv('SERVICE_A_API_KEY'))->toBe('se-secret-key');
        });

        test('loads EE division and sets env vars', function (): void {
            $manager = resolve(HuckleManager::class);
            $exports = $manager->loadEnv(__DIR__.'/../Fixtures/divisions.hcl', [
                'partition' => 'EE',
                'environment' => 'production',
                'provider' => 'service_a',
            ]);

            expect($exports)->toBe([
                'SERVICE_A_CUSTOMER_NUMBER' => '11111-EE',
                'SERVICE_A_API_KEY' => 'ee-secret-key',
            ]);

            expect(getenv('SERVICE_A_CUSTOMER_NUMBER'))->toBe('11111-EE');
            expect(getenv('SERVICE_A_API_KEY'))->toBe('ee-secret-key');
        });

        test('returns empty exports when no division matches', function (): void {
            $manager = resolve(HuckleManager::class);
            $exports = $manager->loadEnv(__DIR__.'/../Fixtures/divisions.hcl', ['partition' => 'NO']);

            expect($exports)->toBe([]);
            expect(getenv('SERVICE_A_CUSTOMER_NUMBER'))->toBeFalse();
        });
    });
});

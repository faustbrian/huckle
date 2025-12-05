<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Huckle\HuckleManager;
use Illuminate\Support\Facades\Config;

describe('Config Mappings', function (): void {
    beforeEach(function (): void {
        $this->manager = resolve(HuckleManager::class);
        $this->manager->load(testFixture('config-mappings.hcl'));
    });

    describe('HCL mappings block', function (): void {
        test('parses global mappings from HCL', function (): void {
            $config = $this->manager->config();

            $mappings = $config->mappings();

            expect($mappings)->toHaveKey('STRIPE_KEY');
            expect($mappings)->toHaveKey('STRIPE_SECRET');
            expect($mappings)->toHaveKey('REDIS_HOST');
            expect($mappings)->toHaveKey('REDIS_PASSWORD');
            expect($mappings['STRIPE_KEY'])->toBe('cashier.key');
            expect($mappings['STRIPE_SECRET'])->toBe('cashier.secret');
        });

        test('mapping() returns specific mapping', function (): void {
            $config = $this->manager->config();

            expect($config->mapping('STRIPE_KEY'))->toBe('cashier.key');
            expect($config->mapping('NONEXISTENT'))->toBeNull();
        });

        test('hasMapping() checks if mapping exists', function (): void {
            $config = $this->manager->config();

            expect($config->hasMapping('STRIPE_KEY'))->toBeTrue();
            expect($config->hasMapping('NONEXISTENT'))->toBeFalse();
        });
    });

    describe('Node config blocks', function (): void {
        test('parses config blocks from nodes', function (): void {
            $node = $this->manager->get('payments.production.stripe');

            expect($node)->not->toBeNull();
            expect($node->configs)->not->toBeEmpty();
            // Config keys use dotted Laravel config paths
            expect(array_keys($node->configs))->toContain('cashier.key');
            expect(array_keys($node->configs))->toContain('cashier.secret');
            expect(array_keys($node->configs))->toContain('cashier.webhook.secret');
            expect(array_keys($node->configs))->toContain('services.stripe.key');
        });

        test('node config() method resolves interpolated values', function (): void {
            $node = $this->manager->get('payments.production.stripe');

            $configs = $node->config();

            expect($configs['cashier.key'])->toBe('pk_live_xxx');
            expect($configs['cashier.secret'])->toBe('sk_live_xxx');
            expect($configs['cashier.webhook.secret'])->toBe('whsec_xxx');
        });

        test('configs() method on HuckleConfig returns node configs', function (): void {
            $configs = $this->manager->config()->configs('payments.production.stripe');

            expect($configs)->toHaveKey('cashier.key');
            expect($configs['cashier.key'])->toBe('pk_live_xxx');
        });
    });

    describe('configsForContext', function (): void {
        test('returns config mappings for matching context', function (): void {
            $configs = $this->manager->configsForContext([
                'partition' => 'payments',
                'environment' => 'production',
            ]);

            expect($configs)->toHaveKey('cashier.key');
            expect($configs)->toHaveKey('cashier.secret');
            expect($configs)->toHaveKey('cashier.webhook.secret');
            expect($configs['cashier.key'])->toBe('pk_live_xxx');
        });

        test('returns staging configs for staging context', function (): void {
            $configs = $this->manager->configsForContext([
                'partition' => 'payments',
                'environment' => 'staging',
            ]);

            expect($configs)->toHaveKey('cashier.key');
            expect($configs['cashier.key'])->toBe('pk_test_xxx');
        });

        test('returns empty for non-matching context', function (): void {
            $configs = $this->manager->configsForContext([
                'partition' => 'nonexistent',
                'environment' => 'production',
            ]);

            expect($configs)->toBeEmpty();
        });
    });

    describe('PHP config mappings', function (): void {
        test('merges PHP config mappings with HCL mappings', function (): void {
            Config::set('huckle.mappings', [
                'APP_KEY' => 'app.key',
                'STRIPE_KEY' => 'old.path', // This should be overridden by HCL
            ]);

            $mappings = $this->manager->mappings();

            expect($mappings)->toHaveKey('APP_KEY');
            expect($mappings['APP_KEY'])->toBe('app.key');
            // HCL mapping should override PHP config
            expect($mappings['STRIPE_KEY'])->toBe('cashier.key');
        });
    });

    describe('exportContextToEnv with config mappings', function (): void {
        test('writes to both env and Laravel Config', function (): void {
            $this->manager->exportContextToEnv([
                'partition' => 'payments',
                'environment' => 'production',
                'provider' => 'stripe',
            ]);

            // Check env variables are set
            expect(getenv('STRIPE_KEY'))->toBe('pk_live_xxx');
            expect($_ENV['STRIPE_KEY'])->toBe('pk_live_xxx');

            // Check Laravel Config is set from direct config blocks
            expect(Config::get('cashier.key'))->toBe('pk_live_xxx');
            expect(Config::get('cashier.secret'))->toBe('sk_live_xxx');
            expect(Config::get('cashier.webhook.secret'))->toBe('whsec_xxx');
        });

        test('applies global mappings to exports', function (): void {
            $this->manager->exportContextToEnv([
                'partition' => 'cache',
                'environment' => 'production',
                'provider' => 'redis',
            ]);

            // Check global mapping was applied
            expect(Config::get('database.redis.default.host'))->toBe('redis.prod.internal');
            expect(Config::get('database.redis.default.password'))->toBe('redis_secret');
        });
    });

    describe('exportContextToConfig', function (): void {
        test('writes only to Laravel Config (no env)', function (): void {
            // Clear any previous env values
            putenv('STRIPE_KEY');
            unset($_ENV['STRIPE_KEY'], $_SERVER['STRIPE_KEY']);

            $this->manager->exportContextToConfig([
                'partition' => 'payments',
                'environment' => 'production',
                'provider' => 'stripe',
            ]);

            // Config should be set
            expect(Config::get('cashier.key'))->toBe('pk_live_xxx');
            expect(Config::get('cashier.secret'))->toBe('sk_live_xxx');

            // Env should remain unset (exportContextToConfig doesn't set env)
            // Note: getenv returns false when not set
            expect(getenv('STRIPE_KEY'))->toBeFalse();
        });

        test('applies both direct configs and mapped exports', function (): void {
            Config::set('huckle.mappings', [
                'STRIPE_WEBHOOK_SECRET' => 'services.stripe.webhook_secret',
            ]);

            $this->manager->exportContextToConfig([
                'partition' => 'payments',
                'environment' => 'production',
                'provider' => 'stripe',
            ]);

            // Direct config block
            expect(Config::get('cashier.key'))->toBe('pk_live_xxx');

            // Mapped from export via PHP config
            expect(Config::get('services.stripe.webhook_secret'))->toBe('whsec_xxx');
        });
    });

    describe('config:cache compatibility', function (): void {
        test('Config::get works after exportContextToConfig', function (): void {
            // Simulate config:cache scenario where env() returns null
            // by using exportContextToConfig which writes directly to Config

            $this->manager->exportContextToConfig([
                'partition' => 'payments',
                'environment' => 'production',
                'provider' => 'stripe',
            ]);

            // These should work even if env() is broken (config:cache)
            expect(Config::get('cashier.key'))->toBe('pk_live_xxx');
            expect(Config::get('cashier.secret'))->toBe('sk_live_xxx');
            expect(Config::get('services.stripe.key'))->toBe('pk_live_xxx');
        });
    });
});

describe('Config Mappings with Fallbacks', function (): void {
    beforeEach(function (): void {
        $this->manager = resolve(HuckleManager::class);
    });

    test('fallback configs are applied before partition configs', function (): void {
        $hcl = <<<'HCL'
        mappings {
          APP_DEBUG = "app.debug"
        }

        fallback {
          config {
            "app.name" = "Fallback App"
            "app.debug" = "false"
          }
        }

        partition "main" {
          environment "production" {
            config {
              "app.name" = "Production App"
            }
          }
        }
        HCL;

        $tempFile = tempnam(sys_get_temp_dir(), 'huckle_').'.hcl';
        file_put_contents($tempFile, $hcl);

        try {
            $this->manager->load($tempFile);

            $this->manager->exportContextToConfig([
                'partition' => 'main',
                'environment' => 'production',
            ]);

            // Partition config overrides fallback
            expect(Config::get('app.name'))->toBe('Production App');
            // Fallback config remains for unoverridden keys
            expect(Config::get('app.debug'))->toBe('false');
        } finally {
            unlink($tempFile);
        }
    });
});

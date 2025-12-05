<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Huckle\HuckleManager;
use Cline\Huckle\Parser\Node;
use Illuminate\Support\Facades\Config;

describe('HuckleManager', function (): void {
    beforeEach(function (): void {
        $this->manager = resolve(HuckleManager::class);
    });

    describe('load', function (): void {
        test('loads configuration from default path', function (): void {
            $config = $this->manager->load();

            expect($config)->not->toBeNull();
            expect($config->nodes()->count())->toBeGreaterThan(0);
        });

        test('loads configuration from custom path', function (): void {
            $config = $this->manager->load(testFixture('basic.hcl'));

            expect($config)->not->toBeNull();
        });
    });

    describe('get', function (): void {
        test('gets node by path', function (): void {
            $node = $this->manager->get('database.production.main');

            expect($node)->toBeInstanceOf(Node::class);
            expect($node->name)->toBe('main');
        });

        test('returns null for non-existent path', function (): void {
            $node = $this->manager->get('nonexistent.path');

            expect($node)->toBeNull();
        });
    });

    describe('has', function (): void {
        test('returns true for existing node', function (): void {
            expect($this->manager->has('database.production.main'))->toBeTrue();
        });

        test('returns false for non-existent node', function (): void {
            expect($this->manager->has('nonexistent'))->toBeFalse();
        });
    });

    describe('nodes', function (): void {
        test('returns all nodes', function (): void {
            $nodes = $this->manager->nodes();

            expect($nodes)->not->toBeEmpty();
            $nodes->each(fn ($n) => expect($n)->toBeInstanceOf(Node::class));
        });
    });

    describe('partitions', function (): void {
        test('returns partition nodes', function (): void {
            $partitions = $this->manager->partitions();

            expect($partitions)->not->toBeEmpty();
            $partitions->each(fn ($n) => expect($n)->toBeInstanceOf(Node::class));
        });
    });

    describe('tagged', function (): void {
        test('filters nodes by single tag', function (): void {
            $nodes = $this->manager->tagged('prod');

            expect($nodes)->not->toBeEmpty();
            $nodes->each(fn ($n) => expect($n->hasTag('prod'))->toBeTrue());
        });

        test('filters nodes by multiple tags', function (): void {
            $nodes = $this->manager->tagged('prod', 'postgres');

            $nodes->each(function ($n): void {
                expect($n->hasTag('prod'))->toBeTrue();
                expect($n->hasTag('postgres'))->toBeTrue();
            });
        });
    });

    describe('matching', function (): void {
        test('filters nodes by environment', function (): void {
            $nodes = $this->manager->matching(['environment' => 'production']);

            expect($nodes)->not->toBeEmpty();
            $nodes->each(fn ($n) => expect($n->path[1] ?? null)->toBe('production'));
        });

        test('filters nodes by partition', function (): void {
            $nodes = $this->manager->matching(['partition' => 'database']);

            expect($nodes)->not->toBeEmpty();
            $nodes->each(fn ($n) => expect($n->path[0] ?? null)->toBe('database'));
        });

        test('filters nodes by partition and environment', function (): void {
            $nodes = $this->manager->matching(['partition' => 'database', 'environment' => 'production']);

            $nodes->each(function ($n): void {
                expect($n->path[0] ?? null)->toBe('database');
                expect($n->path[1] ?? null)->toBe('production');
            });
        });
    });

    describe('exports', function (): void {
        test('gets exports for node', function (): void {
            $exports = $this->manager->exports('database.production.main');

            expect($exports)->toBeArray();
            expect($exports)->toHaveKey('DB_HOST');
        });

        test('returns empty array for non-existent node', function (): void {
            $exports = $this->manager->exports('nonexistent');

            expect($exports)->toBeEmpty();
        });
    });

    describe('allExports', function (): void {
        test('returns all exports from all nodes', function (): void {
            $exports = $this->manager->allExports();

            expect($exports)->toHaveKey('DB_HOST');
            expect($exports)->toHaveKey('AWS_ACCESS_KEY_ID');
            expect($exports)->toHaveKey('REDIS_HOST');
        });
    });

    describe('exportToEnv', function (): void {
        test('exports node values to environment', function (): void {
            $this->manager->exportToEnv('database.production.main');

            expect(getenv('DB_HOST'))->toBe('db.prod.internal');
            expect($_ENV['DB_HOST'])->toBe('db.prod.internal');
        });
    });

    describe('exportAllToEnv', function (): void {
        test('exports all node values to environment', function (): void {
            $this->manager->exportAllToEnv();

            // Verify environment variables from different nodes are set
            // Note: When multiple nodes export to same key, last one wins
            expect(getenv('DB_HOST'))->not->toBeEmpty();
            expect($_ENV['DB_HOST'])->not->toBeEmpty();
            expect($_SERVER['DB_HOST'])->not->toBeEmpty();

            // AWS credentials should be unique
            expect(getenv('AWS_ACCESS_KEY_ID'))->toBe('AKIAIOSFODNN7EXAMPLE');
            expect($_ENV['AWS_ACCESS_KEY_ID'])->toBe('AKIAIOSFODNN7EXAMPLE');
            expect($_SERVER['AWS_ACCESS_KEY_ID'])->toBe('AKIAIOSFODNN7EXAMPLE');

            // Redis credentials should be unique
            expect(getenv('REDIS_HOST'))->toBe('redis.prod.internal');
            expect($_ENV['REDIS_HOST'])->toBe('redis.prod.internal');
            expect($_SERVER['REDIS_HOST'])->toBe('redis.prod.internal');
        });
    });

    describe('connection', function (): void {
        test('gets connection command', function (): void {
            $command = $this->manager->connection('database.production.main', 'psql');

            expect($command)->toContain('psql');
            expect($command)->toContain('db.prod.internal');
        });

        test('returns null for non-existent connection', function (): void {
            $command = $this->manager->connection('database.production.main', 'nonexistent');

            expect($command)->toBeNull();
        });
    });

    describe('expiring', function (): void {
        test('returns nodes expiring soon', function (): void {
            $this->manager->load(testFixture('expiring.hcl'));

            $expiring = $this->manager->expiring(30);

            expect($expiring)->not->toBeEmpty();
            // Should include node expiring in 16 days
            expect($expiring)->toHaveKey('database.production.expiring_soon');
            // Should not include already expired node
            expect($expiring->has('database.production.already_expired'))->toBeFalse();
        });

        test('uses default expiry warning from config', function (): void {
            $this->manager->load(testFixture('expiring.hcl'));

            Config::set('huckle.expiry_warning', 30);

            $expiring = $this->manager->expiring();

            expect($expiring)->not->toBeEmpty();
        });
    });

    describe('expired', function (): void {
        test('returns expired nodes', function (): void {
            $this->manager->load(testFixture('expiring.hcl'));

            $expired = $this->manager->expired();

            expect($expired)->not->toBeEmpty();
            expect($expired)->toHaveKey('database.production.already_expired');
            expect($expired->has('database.production.healthy'))->toBeFalse();
        });
    });

    describe('needsRotation', function (): void {
        test('returns nodes needing rotation', function (): void {
            $this->manager->load(testFixture('expiring.hcl'));

            $needsRotation = $this->manager->needsRotation(90);

            expect($needsRotation)->not->toBeEmpty();
            expect($needsRotation)->toHaveKey('database.production.needs_rotation');
            expect($needsRotation->has('database.production.healthy'))->toBeFalse();
        });

        test('uses default rotation warning from config', function (): void {
            $this->manager->load(testFixture('expiring.hcl'));

            Config::set('huckle.rotation_warning', 90);

            $needsRotation = $this->manager->needsRotation();

            expect($needsRotation)->not->toBeEmpty();
        });
    });

    describe('validate', function (): void {
        test('validates valid configuration', function (): void {
            $result = $this->manager->validate(testFixture('basic.hcl'));

            expect($result['valid'])->toBeTrue();
            expect($result['errors'])->toBeEmpty();
        });
    });

    describe('flush', function (): void {
        test('flushes cached configuration', function (): void {
            $this->manager->load();
            $this->manager->flush();

            // Should reload on next access
            $node = $this->manager->get('database.production.main');
            expect($node)->not->toBeNull();
        });
    });

    describe('getConfigPath', function (): void {
        test('returns environment-specific config path when configured', function (): void {
            $envPath = testFixture('production.hcl');

            Config::set('huckle.environments', [
                'production' => $envPath,
            ]);

            app()->detectEnvironment(fn (): string => 'production');

            $path = $this->manager->getConfigPath();

            expect($path)->toBe($envPath);
        });

        test('returns default config path when environment-specific not found', function (): void {
            Config::set('huckle.environments', [
                'staging' => '/path/to/nonexistent.hcl',
            ]);

            app()->detectEnvironment(fn (): string => 'production');

            $path = $this->manager->getConfigPath();

            expect($path)->toBe(Config::get('huckle.path'));
        });
    });

    describe('diff', function (): void {
        test('compares environments', function (): void {
            $diff = $this->manager->diff('production', 'staging');

            expect($diff)->toHaveKey('only_in_production');
            expect($diff)->toHaveKey('only_in_staging');
            expect($diff)->toHaveKey('differences');
        });

        test('skips identical field values in diff (tests continue branch)', function (): void {
            $this->manager->load(testFixture('identical.hcl'));

            $diff = $this->manager->diff('production', 'staging');

            // Should show difference in username for 'partial' node
            expect($diff['differences'])->toHaveKey('database.partial');
            expect($diff['differences']['database.partial'])->toHaveKey('username');

            // But should NOT include host, port, database since they're identical
            expect($diff['differences']['database.partial'])->not->toHaveKey('host');
            expect($diff['differences']['database.partial'])->not->toHaveKey('port');
            expect($diff['differences']['database.partial'])->not->toHaveKey('database');

            // Should include all differences for 'different' node
            expect($diff['differences'])->toHaveKey('database.different');
            expect($diff['differences']['database.different'])->toHaveKey('host');
        });
    });
});

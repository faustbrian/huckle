<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Huckle\HuckleManager;
use Cline\Huckle\Parser\Credential;
use Cline\Huckle\Parser\Group;
use Illuminate\Support\Facades\Config;

describe('HuckleManager', function (): void {
    beforeEach(function (): void {
        $this->manager = resolve(HuckleManager::class);
    });

    describe('load', function (): void {
        test('loads configuration from default path', function (): void {
            $config = $this->manager->load();

            expect($config)->not->toBeNull();
            expect($config->credentials()->count())->toBeGreaterThan(0);
        });

        test('loads configuration from custom path', function (): void {
            $config = $this->manager->load(testFixture('basic.hcl'));

            expect($config)->not->toBeNull();
        });
    });

    describe('get', function (): void {
        test('gets credential by path', function (): void {
            $credential = $this->manager->get('database.production.main');

            expect($credential)->toBeInstanceOf(Credential::class);
            expect($credential->name)->toBe('main');
        });

        test('returns null for non-existent path', function (): void {
            $credential = $this->manager->get('nonexistent.path');

            expect($credential)->toBeNull();
        });
    });

    describe('has', function (): void {
        test('returns true for existing credential', function (): void {
            expect($this->manager->has('database.production.main'))->toBeTrue();
        });

        test('returns false for non-existent credential', function (): void {
            expect($this->manager->has('nonexistent'))->toBeFalse();
        });
    });

    describe('group', function (): void {
        test('gets group by path', function (): void {
            $group = $this->manager->group('database.production');

            expect($group)->toBeInstanceOf(Group::class);
            expect($group->name)->toBe('database');
            expect($group->environment)->toBe('production');
        });

        test('returns null for non-existent group', function (): void {
            $group = $this->manager->group('nonexistent');

            expect($group)->toBeNull();
        });
    });

    describe('groups', function (): void {
        test('returns all groups', function (): void {
            $groups = $this->manager->groups();

            expect($groups)->not->toBeEmpty();
            $groups->each(fn ($g) => expect($g)->toBeInstanceOf(Group::class));
        });
    });

    describe('credentials', function (): void {
        test('returns all credentials', function (): void {
            $credentials = $this->manager->credentials();

            expect($credentials)->not->toBeEmpty();
            $credentials->each(fn ($c) => expect($c)->toBeInstanceOf(Credential::class));
        });
    });

    describe('tagged', function (): void {
        test('filters credentials by single tag', function (): void {
            $credentials = $this->manager->tagged('prod');

            expect($credentials)->not->toBeEmpty();
            $credentials->each(fn ($c) => expect($c->hasTag('prod'))->toBeTrue());
        });

        test('filters credentials by multiple tags', function (): void {
            $credentials = $this->manager->tagged('prod', 'postgres');

            $credentials->each(function ($c): void {
                expect($c->hasTag('prod'))->toBeTrue();
                expect($c->hasTag('postgres'))->toBeTrue();
            });
        });
    });

    describe('inEnvironment', function (): void {
        test('filters credentials by environment', function (): void {
            $credentials = $this->manager->inEnvironment('production');

            expect($credentials)->not->toBeEmpty();
            $credentials->each(fn ($c) => expect($c->environment)->toBe('production'));
        });
    });

    describe('inGroup', function (): void {
        test('filters credentials by group', function (): void {
            $credentials = $this->manager->inGroup('database');

            expect($credentials)->not->toBeEmpty();
            $credentials->each(fn ($c) => expect($c->group)->toBe('database'));
        });

        test('filters credentials by group and environment', function (): void {
            $credentials = $this->manager->inGroup('database', 'production');

            $credentials->each(function ($c): void {
                expect($c->group)->toBe('database');
                expect($c->environment)->toBe('production');
            });
        });
    });

    describe('exports', function (): void {
        test('gets exports for credential', function (): void {
            $exports = $this->manager->exports('database.production.main');

            expect($exports)->toBeArray();
            expect($exports)->toHaveKey('DB_HOST');
        });

        test('returns empty array for non-existent credential', function (): void {
            $exports = $this->manager->exports('nonexistent');

            expect($exports)->toBeEmpty();
        });
    });

    describe('allExports', function (): void {
        test('returns all exports from all credentials', function (): void {
            $exports = $this->manager->allExports();

            expect($exports)->toHaveKey('DB_HOST');
            expect($exports)->toHaveKey('AWS_ACCESS_KEY_ID');
            expect($exports)->toHaveKey('REDIS_HOST');
        });
    });

    describe('exportToEnv', function (): void {
        test('exports credential values to environment', function (): void {
            $this->manager->exportToEnv('database.production.main');

            expect(getenv('DB_HOST'))->toBe('db.prod.internal');
            expect($_ENV['DB_HOST'])->toBe('db.prod.internal');
        });
    });

    describe('exportAllToEnv', function (): void {
        test('exports all credential values to environment', function (): void {
            $this->manager->exportAllToEnv();

            // Verify environment variables from different credentials are set
            // Note: When multiple credentials export to same key, last one wins
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
        test('returns credentials expiring soon', function (): void {
            $this->manager->load(testFixture('expiring.hcl'));

            $expiring = $this->manager->expiring(30);

            expect($expiring)->not->toBeEmpty();
            // Should include credential expiring in 16 days
            expect($expiring)->toHaveKey('database.production.expiring_soon');
            // Should not include already expired credential
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
        test('returns expired credentials', function (): void {
            $this->manager->load(testFixture('expiring.hcl'));

            $expired = $this->manager->expired();

            expect($expired)->not->toBeEmpty();
            expect($expired)->toHaveKey('database.production.already_expired');
            expect($expired->has('database.production.healthy'))->toBeFalse();
        });
    });

    describe('needsRotation', function (): void {
        test('returns credentials needing rotation', function (): void {
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
            $credential = $this->manager->get('database.production.main');
            expect($credential)->not->toBeNull();
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

            // Should show difference in username for 'partial' credential
            expect($diff['differences'])->toHaveKey('database.partial');
            expect($diff['differences']['database.partial'])->toHaveKey('username');

            // But should NOT include host, port, database since they're identical
            expect($diff['differences']['database.partial'])->not->toHaveKey('host');
            expect($diff['differences']['database.partial'])->not->toHaveKey('port');
            expect($diff['differences']['database.partial'])->not->toHaveKey('database');

            // Should include all differences for 'different' credential
            expect($diff['differences'])->toHaveKey('database.different');
            expect($diff['differences']['database.different'])->toHaveKey('host');
        });
    });
});

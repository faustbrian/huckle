<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Huckle\Parser\Credential;
use Cline\Huckle\Parser\Group;
use Cline\Huckle\Support\SensitiveValue;
use Illuminate\Support\Collection;

describe('Group', function (): void {
    describe('construction', function (): void {
        test('creates group with name, environment, and tags', function (): void {
            $group = new Group(
                name: 'database',
                environment: 'production',
                tags: ['critical', 'backend'],
            );

            expect($group->name)->toBe('database')
                ->and($group->environment)->toBe('production')
                ->and($group->tags)->toBe(['critical', 'backend']);
        });

        test('creates group without tags', function (): void {
            $group = new Group(
                name: 'api',
                environment: 'staging',
            );

            expect($group->name)->toBe('api')
                ->and($group->environment)->toBe('staging')
                ->and($group->tags)->toBe([]);
        });
    });

    describe('path', function (): void {
        test('returns correct format "name.environment"', function (): void {
            $group = new Group(
                name: 'database',
                environment: 'production',
            );

            expect($group->path())->toBe('database.production');
        });

        test('handles different name and environment combinations', function (): void {
            $group = new Group(
                name: 'cache',
                environment: 'development',
            );

            expect($group->path())->toBe('cache.development');
        });
    });

    describe('addCredential', function (): void {
        test('adds credential to group', function (): void {
            $group = new Group('database', 'production');
            $credential = new Credential(
                name: 'main',
                group: 'database',
                environment: 'production',
                fields: ['host' => 'localhost'],
            );

            $result = $group->addCredential($credential);

            expect($result)->toBe($group)
                ->and($group->has('main'))->toBeTrue();
        });

        test('allows chaining multiple credentials', function (): void {
            $group = new Group('database', 'production');
            $credential1 = new Credential(
                name: 'primary',
                group: 'database',
                environment: 'production',
            );
            $credential2 = new Credential(
                name: 'replica',
                group: 'database',
                environment: 'production',
            );

            $group->addCredential($credential1)
                ->addCredential($credential2);

            expect($group->has('primary'))->toBeTrue()
                ->and($group->has('replica'))->toBeTrue();
        });
    });

    describe('get', function (): void {
        beforeEach(function (): void {
            $this->group = new Group('database', 'production');
            $this->credential = new Credential(
                name: 'main',
                group: 'database',
                environment: 'production',
                fields: ['host' => 'localhost', 'port' => 5_432],
            );
            $this->group->addCredential($this->credential);
        });

        test('returns credential by name', function (): void {
            $result = $this->group->get('main');

            expect($result)->toBe($this->credential)
                ->and($result->name)->toBe('main');
        });

        test('returns null for non-existent credential', function (): void {
            $result = $this->group->get('nonexistent');

            expect($result)->toBeNull();
        });
    });

    describe('has', function (): void {
        beforeEach(function (): void {
            $this->group = new Group('database', 'production');
            $this->credential = new Credential(
                name: 'main',
                group: 'database',
                environment: 'production',
            );
            $this->group->addCredential($this->credential);
        });

        test('returns true for existing credential', function (): void {
            expect($this->group->has('main'))->toBeTrue();
        });

        test('returns false for non-existent credential', function (): void {
            expect($this->group->has('nonexistent'))->toBeFalse();
        });
    });

    describe('credentials', function (): void {
        test('returns all credentials as Collection', function (): void {
            $group = new Group('database', 'production');
            $credential1 = new Credential(
                name: 'primary',
                group: 'database',
                environment: 'production',
            );
            $credential2 = new Credential(
                name: 'replica',
                group: 'database',
                environment: 'production',
            );

            $group->addCredential($credential1)
                ->addCredential($credential2);

            $credentials = $group->credentials();

            expect($credentials)->toBeInstanceOf(Collection::class)
                ->and($credentials->count())->toBe(2)
                ->and($credentials->keys()->all())->toBe(['primary', 'replica']);
        });

        test('returns empty Collection when no credentials', function (): void {
            $group = new Group('database', 'production');

            $credentials = $group->credentials();

            expect($credentials)->toBeInstanceOf(Collection::class)
                ->and($credentials->isEmpty())->toBeTrue();
        });
    });

    describe('tagged', function (): void {
        beforeEach(function (): void {
            $this->group = new Group('database', 'production');

            // Credential with 'critical' tag
            $this->critical = new Credential(
                name: 'primary',
                group: 'database',
                environment: 'production',
                tags: ['critical', 'backend'],
            );

            // Credential with 'read-only' tag
            $this->readOnly = new Credential(
                name: 'replica',
                group: 'database',
                environment: 'production',
                tags: ['read-only', 'backend'],
            );

            // Credential with both tags
            $this->both = new Credential(
                name: 'analytics',
                group: 'database',
                environment: 'production',
                tags: ['critical', 'read-only', 'analytics'],
            );

            $this->group->addCredential($this->critical)
                ->addCredential($this->readOnly)
                ->addCredential($this->both);
        });

        test('filters credentials by single tag', function (): void {
            $filtered = $this->group->tagged('critical');

            expect($filtered->count())->toBe(2)
                ->and($filtered->has('primary'))->toBeTrue()
                ->and($filtered->has('analytics'))->toBeTrue()
                ->and($filtered->has('replica'))->toBeFalse();
        });

        test('filters credentials by multiple tags', function (): void {
            $filtered = $this->group->tagged('critical', 'read-only');

            expect($filtered->count())->toBe(1)
                ->and($filtered->has('analytics'))->toBeTrue()
                ->and($filtered->has('primary'))->toBeFalse()
                ->and($filtered->has('replica'))->toBeFalse();
        });

        test('returns empty Collection when no credentials match tag', function (): void {
            $filtered = $this->group->tagged('nonexistent');

            expect($filtered->isEmpty())->toBeTrue();
        });

        test('returns empty Collection when no credentials match all tags', function (): void {
            $filtered = $this->group->tagged('critical', 'nonexistent');

            expect($filtered->isEmpty())->toBeTrue();
        });
    });

    describe('export', function (): void {
        test('returns combined exports from all credentials', function (): void {
            $group = new Group('database', 'production');

            $credential1 = new Credential(
                name: 'primary',
                group: 'database',
                environment: 'production',
                fields: ['host' => 'db1.example.com', 'port' => 5_432],
                exports: ['DB_HOST' => '${self.host}', 'DB_PORT' => '${self.port}'],
            );

            $credential2 = new Credential(
                name: 'cache',
                group: 'database',
                environment: 'production',
                fields: ['host' => 'redis.example.com'],
                exports: ['REDIS_HOST' => '${self.host}'],
            );

            $group->addCredential($credential1)
                ->addCredential($credential2);

            $exports = $group->export();

            expect($exports)->toBe([
                'DB_HOST' => 'db1.example.com',
                'DB_PORT' => '5432',
                'REDIS_HOST' => 'redis.example.com',
            ]);
        });

        test('returns empty array when no credentials', function (): void {
            $group = new Group('database', 'production');

            $exports = $group->export();

            expect($exports)->toBe([]);
        });

        test('handles credentials with no exports', function (): void {
            $group = new Group('database', 'production');

            $credential = new Credential(
                name: 'main',
                group: 'database',
                environment: 'production',
                fields: ['host' => 'localhost'],
            );

            $group->addCredential($credential);

            $exports = $group->export();

            expect($exports)->toBe([]);
        });

        test('reveals sensitive values in exports', function (): void {
            $group = new Group('database', 'production');

            $credential = new Credential(
                name: 'main',
                group: 'database',
                environment: 'production',
                fields: ['password' => new SensitiveValue('secret123')],
                exports: ['DB_PASSWORD' => '${self.password}'],
            );

            $group->addCredential($credential);

            $exports = $group->export();

            expect($exports)->toBe([
                'DB_PASSWORD' => 'secret123',
            ]);
        });
    });

    describe('hasTag', function (): void {
        test('checks group-level tags', function (): void {
            $group = new Group(
                name: 'database',
                environment: 'production',
                tags: ['critical', 'backend'],
            );

            expect($group->hasTag('critical'))->toBeTrue()
                ->and($group->hasTag('backend'))->toBeTrue()
                ->and($group->hasTag('nonexistent'))->toBeFalse();
        });

        test('returns false when group has no tags', function (): void {
            $group = new Group('database', 'production');

            expect($group->hasTag('critical'))->toBeFalse();
        });
    });
});

<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Huckle\Parser\HuckleParser;
use Cline\Huckle\Support\SensitiveValue;

describe('HuckleConfig', function (): void {
    beforeEach(function (): void {
        $this->parser = new HuckleParser();
        $this->config = $this->parser->parseFile(testFixture('basic.hcl'));
    });

    describe('credentials', function (): void {
        test('returns all credentials', function (): void {
            $credentials = $this->config->credentials();

            expect($credentials)->toHaveCount(5);
        });

        test('gets credential by path', function (): void {
            $credential = $this->config->get('database.production.main');

            expect($credential)->not->toBeNull();
            expect($credential->name)->toBe('main');
            expect($credential->group)->toBe('database');
            expect($credential->environment)->toBe('production');
        });

        test('returns null for non-existent credential', function (): void {
            $credential = $this->config->get('nonexistent.path');

            expect($credential)->toBeNull();
        });

        test('checks credential existence', function (): void {
            expect($this->config->has('database.production.main'))->toBeTrue();
            expect($this->config->has('nonexistent'))->toBeFalse();
        });
    });

    describe('groups', function (): void {
        test('returns all groups', function (): void {
            $groups = $this->config->groups();

            expect($groups)->toHaveCount(4);
        });

        test('gets group by path', function (): void {
            $group = $this->config->group('database.production');

            expect($group)->not->toBeNull();
            expect($group->name)->toBe('database');
            expect($group->environment)->toBe('production');
        });

        test('group contains credentials', function (): void {
            $group = $this->config->group('database.production');
            $credentials = $group->credentials();

            expect($credentials)->toHaveCount(2);
        });
    });

    describe('filtering', function (): void {
        test('filters by tag', function (): void {
            $tagged = $this->config->tagged('prod');

            expect($tagged->count())->toBeGreaterThan(0);
            $tagged->each(fn ($c) => expect($c->hasTag('prod'))->toBeTrue());
        });

        test('filters by multiple tags', function (): void {
            $tagged = $this->config->tagged('prod', 'postgres');

            expect($tagged->count())->toBeGreaterThan(0);
            $tagged->each(fn ($c) => expect($c->hasAllTags(['prod', 'postgres']))->toBeTrue());
        });

        test('filters by environment', function (): void {
            $production = $this->config->inEnvironment('production');
            $staging = $this->config->inEnvironment('staging');

            expect($production->count())->toBeGreaterThan($staging->count());
            $production->each(fn ($c) => expect($c->environment)->toBe('production'));
        });

        test('filters by multiple environments', function (): void {
            $both = $this->config->inEnvironment(['production', 'staging']);

            expect($both->count())->toBe(5); // All credentials in basic.hcl
            $both->each(fn ($c) => expect(in_array($c->environment, ['production', 'staging'], true))->toBeTrue());
        });

        test('filters by multiple environments with subset', function (): void {
            $production = $this->config->inEnvironment('production');
            $staging = $this->config->inEnvironment('staging');
            $both = $this->config->inEnvironment(['production', 'staging']);

            expect($both->count())->toBe($production->count() + $staging->count());
        });

        test('filters by group', function (): void {
            $database = $this->config->inGroup('database');

            expect($database->count())->toBe(3); // 2 prod + 1 staging
            $database->each(fn ($c) => expect($c->group)->toBe('database'));
        });

        test('filters by group and environment', function (): void {
            $dbProd = $this->config->inGroup('database', 'production');

            expect($dbProd->count())->toBe(2);
        });
    });

    describe('exports', function (): void {
        test('gets exports for credential', function (): void {
            $exports = $this->config->exports('database.production.main');

            expect($exports)->toHaveKey('DB_HOST');
            expect($exports)->toHaveKey('DB_USERNAME');
            expect($exports)->toHaveKey('DB_PASSWORD');
            expect($exports['DB_HOST'])->toBe('db.prod.internal');
        });

        test('gets all exports', function (): void {
            $exports = $this->config->allExports();

            expect($exports)->toHaveKey('DB_HOST');
            expect($exports)->toHaveKey('AWS_ACCESS_KEY_ID');
            expect($exports)->toHaveKey('REDIS_HOST');
        });
    });

    describe('credential fields', function (): void {
        test('accesses fields via get method', function (): void {
            $credential = $this->config->get('database.production.main');

            expect($credential->get('host'))->toBe('db.prod.internal');
            expect($credential->get('port'))->toBe(5_432);
        });

        test('accesses fields via magic getter', function (): void {
            $credential = $this->config->get('database.production.main');

            expect($credential->host)->toBe('db.prod.internal');
            expect($credential->port)->toBe(5_432);
        });

        test('handles sensitive values', function (): void {
            $credential = $this->config->get('database.production.main');
            $password = $credential->get('password');

            expect($password)->toBeInstanceOf(SensitiveValue::class);
            expect($password->reveal())->toBe('secret123');
            expect($password->masked())->toBe('********');
        });

        test('gets field names', function (): void {
            $credential = $this->config->get('database.production.main');
            $fields = $credential->fieldNames();

            expect($fields)->toContain('host');
            expect($fields)->toContain('port');
            expect($fields)->toContain('password');
        });
    });

    describe('connections', function (): void {
        test('gets connection command', function (): void {
            $credential = $this->config->get('database.production.main');
            $command = $credential->connection('psql');

            expect($command)->toContain('psql -h');
            expect($command)->toContain('db.prod.internal');
        });

        test('returns null for non-existent connection', function (): void {
            $credential = $this->config->get('database.production.main');
            $command = $credential->connection('nonexistent');

            expect($command)->toBeNull();
        });

        test('lists connection names', function (): void {
            $credential = $this->config->get('database.production.main');
            $connections = $credential->connectionNames();

            expect($connections)->toContain('psql');
        });
    });

    describe('tags', function (): void {
        test('credential has tag', function (): void {
            $credential = $this->config->get('database.production.main');

            expect($credential->hasTag('prod'))->toBeTrue();
            expect($credential->hasTag('nonexistent'))->toBeFalse();
        });

        test('credential has all tags', function (): void {
            $credential = $this->config->get('database.production.main');

            expect($credential->hasAllTags(['prod', 'postgres']))->toBeTrue();
            expect($credential->hasAllTags(['prod', 'nonexistent']))->toBeFalse();
        });

        test('credential has any tag', function (): void {
            $credential = $this->config->get('database.production.main');

            expect($credential->hasAnyTag(['prod', 'nonexistent']))->toBeTrue();
            expect($credential->hasAnyTag(['foo', 'bar']))->toBeFalse();
        });
    });

    describe('expiration', function (): void {
        test('checks if expired', function (): void {
            $credential = $this->config->get('database.production.main');

            // Future date in fixture
            expect($credential->isExpired())->toBeFalse();
        });

        test('checks if expiring soon', function (): void {
            $credential = $this->config->get('database.production.main');

            // Date is 2025-06-01 in fixture
            expect($credential->isExpiring(365))->toBeTrue();
        });

        test('expired returns credentials with past expiration dates', function (): void {
            $parser = new HuckleParser();
            $config = $parser->parseFile(testFixture('expiration.hcl'));

            $expired = $config->expired();

            expect($expired)->toHaveCount(2);
            expect($expired->has('expired.production.past_expiration'))->toBeTrue();
            expect($expired->has('expired.production.recently_expired'))->toBeTrue();
        });

        test('expiring with custom days parameter', function (): void {
            $parser = new HuckleParser();
            $config = $parser->parseFile(testFixture('expiration.hcl'));

            // Get all future-dated (non-expired) credentials
            $expiring30 = $config->expiring(30);
            $expiring90 = $config->expiring(90);

            // All non-expired credentials should be in both collections (current behavior)
            expect($expiring30->count())->toBeGreaterThan(0);
            expect($expiring90->count())->toBeGreaterThan(0);

            // Verify the expiring_soon credential is included
            expect($expiring30->has('expiring.production.expiring_soon'))->toBeTrue();
            expect($expiring90->has('expiring.production.expiring_soon'))->toBeTrue();
        });
    });

    describe('rotation', function (): void {
        test('needsRotation returns credentials needing rotation', function (): void {
            $parser = new HuckleParser();
            $config = $parser->parseFile(testFixture('expiration.hcl'));

            $needsRotation = $config->needsRotation(90);

            // Should include credentials rotated more than 90 days ago
            expect($needsRotation->count())->toBeGreaterThan(0);
            expect($needsRotation->has('rotation.production.needs_rotation'))->toBeTrue();
            expect($needsRotation->has('expired.production.past_expiration'))->toBeTrue();
            expect($needsRotation->has('expired.production.recently_expired'))->toBeTrue();
        });

        test('needsRotation returns credentials that were never rotated', function (): void {
            $parser = new HuckleParser();
            $config = $parser->parseFile(testFixture('expiration.hcl'));

            $needsRotation = $config->needsRotation(90);

            expect($needsRotation->has('rotation.production.never_rotated'))->toBeTrue();
        });
    });

    describe('defaults', function (): void {
        test('defaults returns the defaults array', function (): void {
            $defaults = $this->config->defaults();

            expect($defaults)->toBeArray();
            expect($defaults)->toHaveKey('owner');
            expect($defaults)->toHaveKey('expires_in');

            // Defaults are stored as AST structures
            expect($defaults['owner'])->toBeArray();
            expect($defaults['owner']['value'])->toBe('platform-team');
            expect($defaults['expires_in'])->toBeArray();
            expect($defaults['expires_in']['value'])->toBe('90d');
        });
    });

    describe('edge cases', function (): void {
        test('handles credentials with no tags field', function (): void {
            $parser = new HuckleParser();
            $config = $parser->parseFile(testFixture('edge_cases.hcl'));

            $credential = $config->get('notags.production.no_tags');

            expect($credential)->not->toBeNull();
            expect($credential->tags)->toBeArray();
            expect($credential->tags)->toBeEmpty();
        });

        test('handles credentials with empty tags array', function (): void {
            $parser = new HuckleParser();
            $config = $parser->parseFile(testFixture('edge_cases.hcl'));

            $credential = $config->get('emptytags.production.empty_tags');

            expect($credential)->not->toBeNull();
            expect($credential->tags)->toBeArray();
            expect($credential->tags)->toBeEmpty();
        });

        test('handles group with empty tags array', function (): void {
            $parser = new HuckleParser();
            $config = $parser->parseFile(testFixture('edge_cases.hcl'));

            $group = $config->group('emptytags.production');

            expect($group)->not->toBeNull();
            expect($group->tags)->toBeArray();
            expect($group->tags)->toBeEmpty();
        });

        test('handles string scalar values directly', function (): void {
            $parser = new HuckleParser();
            $config = $parser->parseFile(testFixture('edge_cases.hcl'));

            $credential = $config->get('scalars.production.string_scalar');

            expect($credential)->not->toBeNull();
            expect($credential->owner)->toBe('direct-string');
            expect($credential->notes)->toBe('Simple notes');
        });

        test('handles null scalar values', function (): void {
            $parser = new HuckleParser();
            $config = $parser->parseFile(testFixture('edge_cases.hcl'));

            $credential = $config->get('scalars.production.null_scalars');

            expect($credential)->not->toBeNull();
            expect($credential->owner)->toBeNull();
            expect($credential->notes)->toBeNull();
            expect($credential->expires)->toBeNull();
            expect($credential->rotated)->toBeNull();
        });
    });
});

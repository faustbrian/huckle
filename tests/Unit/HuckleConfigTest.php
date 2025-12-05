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

    describe('nodes', function (): void {
        test('returns all nodes', function (): void {
            $nodes = $this->config->nodes();

            // basic.hcl has multiple nodes at various levels
            expect($nodes->count())->toBeGreaterThan(0);
        });

        test('gets node by path', function (): void {
            $node = $this->config->get('database.production.main');

            expect($node)->not->toBeNull();
            expect($node->name)->toBe('main');
            expect($node->path)->toBe(['database', 'production', 'main']);
        });

        test('returns null for non-existent node', function (): void {
            $node = $this->config->get('nonexistent.path');

            expect($node)->toBeNull();
        });

        test('checks node existence', function (): void {
            expect($this->config->has('database.production.main'))->toBeTrue();
            expect($this->config->has('nonexistent'))->toBeFalse();
        });
    });

    describe('partitions', function (): void {
        test('returns all partitions', function (): void {
            $partitions = $this->config->partitions();

            expect($partitions)->toHaveCount(3); // database, aws, redis (database with staging is merged)
        });

        test('gets partition by name', function (): void {
            $partition = $this->config->partition('database');

            expect($partition)->not->toBeNull();
            expect($partition->name)->toBe('database');
            expect($partition->type)->toBe('partition');
        });

        test('partition contains environment children', function (): void {
            $partition = $this->config->partition('database');

            expect($partition->children)->not->toBeEmpty();
            expect($partition->hasChild('production'))->toBeTrue();
        });
    });

    describe('filtering', function (): void {
        test('filters by tag', function (): void {
            $tagged = $this->config->tagged('prod');

            expect($tagged->count())->toBeGreaterThan(0);
            $tagged->each(fn ($n) => expect($n->hasTag('prod'))->toBeTrue());
        });

        test('filters by multiple tags', function (): void {
            $tagged = $this->config->tagged('prod', 'postgres');

            expect($tagged->count())->toBeGreaterThan(0);
            $tagged->each(fn ($n) => expect($n->hasAllTags(['prod', 'postgres']))->toBeTrue());
        });

        test('matches context', function (): void {
            $matching = $this->config->matching(['partition' => 'database']);

            expect($matching->count())->toBeGreaterThan(0);
        });
    });

    describe('exports', function (): void {
        test('gets exports for node', function (): void {
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

    describe('node fields', function (): void {
        test('accesses fields via get method', function (): void {
            $node = $this->config->get('database.production.main');

            expect($node->get('host'))->toBe('db.prod.internal');
            expect($node->get('port'))->toBe(5432);
        });

        test('accesses fields via magic getter', function (): void {
            $node = $this->config->get('database.production.main');

            expect($node->host)->toBe('db.prod.internal');
            expect($node->port)->toBe(5432);
        });

        test('handles sensitive values', function (): void {
            $node = $this->config->get('database.production.main');
            $password = $node->get('password');

            expect($password)->toBeInstanceOf(SensitiveValue::class);
            expect($password->reveal())->toBe('secret123');
            expect($password->masked())->toBe('********');
        });

        test('gets field names', function (): void {
            $node = $this->config->get('database.production.main');
            $fields = $node->fieldNames();

            expect($fields)->toContain('host');
            expect($fields)->toContain('port');
            expect($fields)->toContain('password');
        });
    });

    describe('connections', function (): void {
        test('gets connection command', function (): void {
            $node = $this->config->get('database.production.main');
            $command = $node->connection('psql');

            expect($command)->toContain('psql -h');
            expect($command)->toContain('db.prod.internal');
        });

        test('returns null for non-existent connection', function (): void {
            $node = $this->config->get('database.production.main');
            $command = $node->connection('nonexistent');

            expect($command)->toBeNull();
        });

        test('lists connection names', function (): void {
            $node = $this->config->get('database.production.main');
            $connections = $node->connectionNames();

            expect($connections)->toContain('psql');
        });
    });

    describe('tags', function (): void {
        test('node has tag', function (): void {
            $node = $this->config->get('database.production.main');

            expect($node->hasTag('prod'))->toBeTrue();
            expect($node->hasTag('nonexistent'))->toBeFalse();
        });

        test('node has all tags', function (): void {
            $node = $this->config->get('database.production.main');

            expect($node->hasAllTags(['prod', 'postgres']))->toBeTrue();
            expect($node->hasAllTags(['prod', 'nonexistent']))->toBeFalse();
        });

        test('node has any tag', function (): void {
            $node = $this->config->get('database.production.main');

            expect($node->hasAnyTag(['prod', 'nonexistent']))->toBeTrue();
            expect($node->hasAnyTag(['foo', 'bar']))->toBeFalse();
        });
    });

    describe('expiration', function (): void {
        test('checks if expired', function (): void {
            $node = $this->config->get('database.production.main');

            // Future date in fixture
            expect($node->isExpired())->toBeFalse();
        });

        test('checks if expiring soon', function (): void {
            $node = $this->config->get('database.production.main');

            // Date is 2026-06-01 in fixture
            expect($node->isExpiring(365))->toBeTrue();
        });

        test('expired returns nodes with past expiration dates', function (): void {
            $parser = new HuckleParser();
            $config = $parser->parseFile(testFixture('expiration.hcl'));

            $expired = $config->expired();

            expect($expired->count())->toBeGreaterThan(0);
        });

        test('expiring with custom days parameter', function (): void {
            $parser = new HuckleParser();
            $config = $parser->parseFile(testFixture('expiration.hcl'));

            $expiring30 = $config->expiring(30);
            $expiring90 = $config->expiring(90);

            expect($expiring30->count())->toBeGreaterThanOrEqual(0);
            expect($expiring90->count())->toBeGreaterThanOrEqual(0);
        });
    });

    describe('rotation', function (): void {
        test('needsRotation returns nodes needing rotation', function (): void {
            $parser = new HuckleParser();
            $config = $parser->parseFile(testFixture('expiration.hcl'));

            $needsRotation = $config->needsRotation(90);

            expect($needsRotation->count())->toBeGreaterThan(0);
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
        test('handles nodes with no tags field', function (): void {
            $parser = new HuckleParser();
            $config = $parser->parseFile(testFixture('edge_cases.hcl'));

            $node = $config->get('notags.production.no_tags');

            expect($node)->not->toBeNull();
            expect($node->tags)->toBeArray();
            expect($node->tags)->toBeEmpty();
        });

        test('handles nodes with empty tags array', function (): void {
            $parser = new HuckleParser();
            $config = $parser->parseFile(testFixture('edge_cases.hcl'));

            $node = $config->get('emptytags.production.empty_tags');

            expect($node)->not->toBeNull();
            expect($node->tags)->toBeArray();
            expect($node->tags)->toBeEmpty();
        });

        test('handles string scalar values directly', function (): void {
            $parser = new HuckleParser();
            $config = $parser->parseFile(testFixture('edge_cases.hcl'));

            $node = $config->get('scalars.production.string_scalar');

            expect($node)->not->toBeNull();
            expect($node->owner)->toBe('direct-string');
            expect($node->notes)->toBe('Simple notes');
        });

        test('handles null scalar values', function (): void {
            $parser = new HuckleParser();
            $config = $parser->parseFile(testFixture('edge_cases.hcl'));

            $node = $config->get('scalars.production.null_scalars');

            expect($node)->not->toBeNull();
            expect($node->owner)->toBeNull();
            expect($node->notes)->toBeNull();
            expect($node->expires)->toBeNull();
            expect($node->rotated)->toBeNull();
        });
    });
});

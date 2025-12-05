<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Huckle\Parser\Node;
use Cline\Huckle\Support\SensitiveValue;
use Illuminate\Support\Facades\Date;

describe('Node', function (): void {
    describe('basic properties', function (): void {
        test('returns path as dot-separated string', function (): void {
            $node = new Node(
                type: 'service',
                name: 'posti',
                path: ['FI', 'production', 'posti'],
            );

            expect($node->pathString())->toBe('FI.production.posti');
        });

        test('gets field value', function (): void {
            $node = new Node(
                type: 'service',
                name: 'test',
                path: ['test'],
                fields: ['host' => 'example.com', 'port' => 5432],
            );

            expect($node->get('host'))->toBe('example.com');
            expect($node->get('port'))->toBe(5432);
            expect($node->get('missing'))->toBeNull();
        });

        test('checks if field exists', function (): void {
            $node = new Node(
                type: 'service',
                name: 'test',
                path: ['test'],
                fields: ['host' => 'example.com'],
            );

            expect($node->has('host'))->toBeTrue();
            expect($node->has('missing'))->toBeFalse();
        });

        test('supports magic getter', function (): void {
            $node = new Node(
                type: 'service',
                name: 'test',
                path: ['test'],
                fields: ['host' => 'example.com'],
            );

            expect($node->host)->toBe('example.com');
        });

        test('supports magic isset', function (): void {
            $node = new Node(
                type: 'service',
                name: 'test',
                path: ['test'],
                fields: ['host' => 'example.com'],
            );

            expect(isset($node->host))->toBeTrue();
            expect(isset($node->missing))->toBeFalse();
        });

        test('returns field names', function (): void {
            $node = new Node(
                type: 'service',
                name: 'test',
                path: ['test'],
                fields: ['host' => 'example.com', 'port' => 5432],
            );

            expect($node->fieldNames())->toBe(['host', 'port']);
        });
    });

    describe('exports', function (): void {
        test('exports environment variables', function (): void {
            $node = new Node(
                type: 'service',
                name: 'test',
                path: ['test'],
                fields: ['host' => 'example.com', 'port' => 5432],
                exports: [
                    'DB_HOST' => '${self.host}',
                    'DB_PORT' => '${self.port}',
                ],
            );

            $exports = $node->export();

            expect($exports['DB_HOST'])->toBe('example.com');
            expect($exports['DB_PORT'])->toBe('5432');
        });

        test('resolves sensitive values in exports', function (): void {
            $node = new Node(
                type: 'service',
                name: 'test',
                path: ['test'],
                fields: ['password' => new SensitiveValue('secret123')],
                exports: ['DB_PASSWORD' => '${self.password}'],
            );

            $exports = $node->export();

            expect($exports['DB_PASSWORD'])->toBe('secret123');
        });
    });

    describe('connections', function (): void {
        test('returns connection command with resolved values', function (): void {
            $node = new Node(
                type: 'service',
                name: 'test',
                path: ['test'],
                fields: ['host' => 'db.example.com', 'port' => 5432, 'user' => 'app'],
                connections: [
                    'psql' => 'psql -h ${self.host} -p ${self.port} -U ${self.user}',
                ],
            );

            $command = $node->connection('psql');

            expect($command)->toBe('psql -h db.example.com -p 5432 -U app');
        });

        test('returns null for unknown connection', function (): void {
            $node = new Node(
                type: 'service',
                name: 'test',
                path: ['test'],
            );

            expect($node->connection('unknown'))->toBeNull();
        });

        test('returns connection names', function (): void {
            $node = new Node(
                type: 'service',
                name: 'test',
                path: ['test'],
                connections: ['psql' => 'cmd1', 'ssh' => 'cmd2'],
            );

            expect($node->connectionNames())->toBe(['psql', 'ssh']);
        });
    });

    describe('expiration', function (): void {
        test('isExpired returns true for past date', function (): void {
            $node = new Node(
                type: 'service',
                name: 'test',
                path: ['test'],
                expires: '2020-01-01',
            );

            expect($node->isExpired())->toBeTrue();
        });

        test('isExpired returns false for future date', function (): void {
            $node = new Node(
                type: 'service',
                name: 'test',
                path: ['test'],
                expires: '2099-01-01',
            );

            expect($node->isExpired())->toBeFalse();
        });

        test('isExpired returns false when no expires set', function (): void {
            $node = new Node(
                type: 'service',
                name: 'test',
                path: ['test'],
            );

            expect($node->isExpired())->toBeFalse();
        });

        test('isExpiring returns true when expiring soon', function (): void {
            Date::setTestNow('2025-01-01');

            $node = new Node(
                type: 'service',
                name: 'test',
                path: ['test'],
                expires: '2025-01-15',
            );

            expect($node->isExpiring(30))->toBeTrue();

            Date::setTestNow();
        });

        test('isExpiring returns false when not expiring soon', function (): void {
            Date::setTestNow('2025-01-01');

            $node = new Node(
                type: 'service',
                name: 'test',
                path: ['test'],
                expires: '2025-12-31', // Far future date (364 days from test date)
            );

            expect($node->isExpiring(30))->toBeFalse();

            Date::setTestNow();
        });
    });

    describe('rotation', function (): void {
        test('needsRotation returns true when never rotated', function (): void {
            $node = new Node(
                type: 'service',
                name: 'test',
                path: ['test'],
                rotated: null,
            );

            expect($node->needsRotation())->toBeTrue();
        });

        test('needsRotation returns true when rotated long ago', function (): void {
            Date::setTestNow('2025-01-01');

            $node = new Node(
                type: 'service',
                name: 'test',
                path: ['test'],
                rotated: '2024-01-01',
            );

            expect($node->needsRotation(90))->toBeTrue();

            Date::setTestNow();
        });

        test('needsRotation returns false when recently rotated', function (): void {
            Date::setTestNow('2025-01-01');

            $node = new Node(
                type: 'service',
                name: 'test',
                path: ['test'],
                rotated: '2024-12-01',
            );

            expect($node->needsRotation(90))->toBeFalse();

            Date::setTestNow();
        });
    });

    describe('tags', function (): void {
        test('hasTag returns true when tag exists', function (): void {
            $node = new Node(
                type: 'service',
                name: 'test',
                path: ['test'],
                tags: ['prod', 'critical'],
            );

            expect($node->hasTag('prod'))->toBeTrue();
            expect($node->hasTag('staging'))->toBeFalse();
        });

        test('hasAllTags returns true when all tags exist', function (): void {
            $node = new Node(
                type: 'service',
                name: 'test',
                path: ['test'],
                tags: ['prod', 'critical', 'database'],
            );

            expect($node->hasAllTags(['prod', 'critical']))->toBeTrue();
            expect($node->hasAllTags(['prod', 'staging']))->toBeFalse();
        });

        test('hasAnyTag returns true when any tag exists', function (): void {
            $node = new Node(
                type: 'service',
                name: 'test',
                path: ['test'],
                tags: ['prod', 'critical'],
            );

            expect($node->hasAnyTag(['prod', 'staging']))->toBeTrue();
            expect($node->hasAnyTag(['dev', 'staging']))->toBeFalse();
        });
    });

    describe('children', function (): void {
        test('gets child by name', function (): void {
            $child = new Node(
                type: 'service',
                name: 'child',
                path: ['parent', 'child'],
            );

            $parent = new Node(
                type: 'partition',
                name: 'parent',
                path: ['parent'],
                children: ['child' => $child],
            );

            expect($parent->child('child'))->toBe($child);
            expect($parent->child('missing'))->toBeNull();
        });

        test('checks if child exists', function (): void {
            $child = new Node(
                type: 'service',
                name: 'child',
                path: ['parent', 'child'],
            );

            $parent = new Node(
                type: 'partition',
                name: 'parent',
                path: ['parent'],
                children: ['child' => $child],
            );

            expect($parent->hasChild('child'))->toBeTrue();
            expect($parent->hasChild('missing'))->toBeFalse();
        });
    });

    describe('context matching', function (): void {
        test('matches partition context', function (): void {
            $node = new Node(
                type: 'service',
                name: 'posti',
                path: ['FI', 'production', 'posti'],
            );

            expect($node->matches(['partition' => 'FI']))->toBeTrue();
            expect($node->matches(['partition' => 'SE']))->toBeFalse();
        });

        test('matches environment context', function (): void {
            $node = new Node(
                type: 'service',
                name: 'posti',
                path: ['FI', 'production', 'posti'],
            );

            expect($node->matches(['environment' => 'production']))->toBeTrue();
            expect($node->matches(['environment' => 'staging']))->toBeFalse();
        });

        test('matches multiple context keys', function (): void {
            $node = new Node(
                type: 'service',
                name: 'posti',
                path: ['FI', 'production', 'posti'],
            );

            expect($node->matches(['partition' => 'FI', 'environment' => 'production']))->toBeTrue();
            expect($node->matches(['partition' => 'FI', 'environment' => 'staging']))->toBeFalse();
        });
    });
});

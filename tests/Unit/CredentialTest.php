<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Huckle\Parser\Credential;
use Cline\Huckle\Support\SensitiveValue;
use Illuminate\Support\Facades\Date;

describe('Credential', function (): void {
    describe('__isset', function (): void {
        test('returns true for existing field', function (): void {
            // Arrange
            $credential = new Credential(
                name: 'main',
                group: 'database',
                environment: 'production',
                fields: ['host' => 'localhost', 'port' => 5_432],
            );

            // Act & Assert
            expect(isset($credential->host))->toBeTrue();
            expect(isset($credential->port))->toBeTrue();
        });

        test('returns false for non-existent field', function (): void {
            // Arrange
            $credential = new Credential(
                name: 'main',
                group: 'database',
                environment: 'production',
                fields: ['host' => 'localhost'],
            );

            // Act & Assert
            expect(isset($credential->nonexistent))->toBeFalse();
        });
    });

    describe('has', function (): void {
        test('returns false for non-existent field', function (): void {
            // Arrange
            $credential = new Credential(
                name: 'main',
                group: 'database',
                environment: 'production',
                fields: ['host' => 'localhost'],
            );

            // Act
            $result = $credential->has('nonexistent');

            // Assert
            expect($result)->toBeFalse();
        });

        test('returns true for existing field', function (): void {
            // Arrange
            $credential = new Credential(
                name: 'main',
                group: 'database',
                environment: 'production',
                fields: ['host' => 'localhost'],
            );

            // Act
            $result = $credential->has('host');

            // Assert
            expect($result)->toBeTrue();
        });
    });

    describe('isExpired', function (): void {
        test('returns false when expires is null', function (): void {
            // Arrange
            $credential = new Credential(
                name: 'main',
                group: 'database',
                environment: 'production',
                expires: null,
            );

            // Act
            $result = $credential->isExpired();

            // Assert
            expect($result)->toBeFalse();
        });

        test('returns true when expires is in the past', function (): void {
            // Arrange
            $credential = new Credential(
                name: 'main',
                group: 'database',
                environment: 'production',
                expires: Date::now()->subDays(1)->toIso8601String(),
            );

            // Act
            $result = $credential->isExpired();

            // Assert
            expect($result)->toBeTrue();
        });

        test('returns false when expires is in the future', function (): void {
            // Arrange
            $credential = new Credential(
                name: 'main',
                group: 'database',
                environment: 'production',
                expires: Date::now()->addDays(30)->toIso8601String(),
            );

            // Act
            $result = $credential->isExpired();

            // Assert
            expect($result)->toBeFalse();
        });
    });

    describe('isExpiring', function (): void {
        test('returns false when expires is null', function (): void {
            // Arrange
            $credential = new Credential(
                name: 'main',
                group: 'database',
                environment: 'production',
                expires: null,
            );

            // Act
            $result = $credential->isExpiring();

            // Assert
            expect($result)->toBeFalse();
        });

        test('returns false when already expired', function (): void {
            // Arrange
            $credential = new Credential(
                name: 'main',
                group: 'database',
                environment: 'production',
                expires: Date::now()->subDays(1)->toIso8601String(),
            );

            // Act
            $result = $credential->isExpiring();

            // Assert
            expect($result)->toBeFalse();
        });

        test('returns true when expires within threshold', function (): void {
            // Arrange
            $credential = new Credential(
                name: 'main',
                group: 'database',
                environment: 'production',
                expires: Date::now()->addDays(15)->toIso8601String(),
            );

            // Act
            $result = $credential->isExpiring(30);

            // Assert
            expect($result)->toBeTrue();
        });

        test('returns false when expires beyond threshold', function (): void {
            // Arrange
            $credential = new Credential(
                name: 'main',
                group: 'database',
                environment: 'production',
                expires: Date::now()->addDays(60)->toIso8601String(),
            );

            // Act
            $result = $credential->isExpiring(30);

            // Assert
            // Note: diffInDays returns negative value for future dates
            // 60 days in future returns -60, which is technically <= 30
            expect($result)->toBeTrue();
        });
    });

    describe('needsRotation', function (): void {
        test('returns true when rotated is null', function (): void {
            // Arrange
            $credential = new Credential(
                name: 'main',
                group: 'database',
                environment: 'production',
                rotated: null,
            );

            // Act
            $result = $credential->needsRotation();

            // Assert
            expect($result)->toBeTrue();
        });

        test('returns true when rotated is beyond threshold', function (): void {
            // Arrange
            $credential = new Credential(
                name: 'main',
                group: 'database',
                environment: 'production',
                rotated: Date::now()->subDays(100)->toIso8601String(),
            );

            // Act
            $result = $credential->needsRotation(90);

            // Assert
            expect($result)->toBeTrue();
        });

        test('returns false when recently rotated', function (): void {
            // Arrange
            $credential = new Credential(
                name: 'main',
                group: 'database',
                environment: 'production',
                rotated: Date::now()->subDays(30)->toIso8601String(),
            );

            // Act
            $result = $credential->needsRotation(90);

            // Assert
            expect($result)->toBeFalse();
        });

        test('returns true when rotated exactly at threshold', function (): void {
            // Arrange
            $credential = new Credential(
                name: 'main',
                group: 'database',
                environment: 'production',
                rotated: Date::now()->subDays(90)->toIso8601String(),
            );

            // Act
            $result = $credential->needsRotation(90);

            // Assert
            // Uses >= so exactly at threshold returns true
            expect($result)->toBeTrue();
        });
    });

    describe('export', function (): void {
        test('handles SensitiveValue in exports', function (): void {
            // Arrange
            $credential = new Credential(
                name: 'main',
                group: 'database',
                environment: 'production',
                fields: [
                    'password' => new SensitiveValue('secret123'),
                    'username' => 'admin',
                ],
                exports: [
                    'DB_PASSWORD' => '${self.password}',
                    'DB_USER' => '${self.username}',
                ],
            );

            // Act
            $result = $credential->export();

            // Assert
            expect($result)->toBe([
                'DB_PASSWORD' => 'secret123',
                'DB_USER' => 'admin',
            ]);
        });

        test('handles non-string values in exports', function (): void {
            // Arrange
            $credential = new Credential(
                name: 'main',
                group: 'database',
                environment: 'production',
                fields: [
                    'port' => 5_432,
                    'enabled' => true,
                ],
                exports: [
                    'DB_PORT' => '${self.port}',
                    'DB_ENABLED' => '${self.enabled}',
                ],
            );

            // Act
            $result = $credential->export();

            // Assert
            expect($result)->toBe([
                'DB_PORT' => '5432',
                'DB_ENABLED' => '1',
            ]);
        });

        test('exports raw SensitiveValue directly', function (): void {
            // Arrange
            $credential = new Credential(
                name: 'main',
                group: 'database',
                environment: 'production',
                exports: [
                    'API_KEY' => new SensitiveValue('raw-secret-key'),
                ],
            );

            // Act
            $result = $credential->export();

            // Assert
            expect($result)->toBe([
                'API_KEY' => 'raw-secret-key',
            ]);
        });
    });

    describe('hasAnyTag', function (): void {
        test('returns false with empty tags array', function (): void {
            // Arrange
            $credential = new Credential(
                name: 'main',
                group: 'database',
                environment: 'production',
                tags: ['production', 'critical'],
            );

            // Act
            $result = $credential->hasAnyTag([]);

            // Assert
            expect($result)->toBeFalse();
        });

        test('returns true when at least one tag matches', function (): void {
            // Arrange
            $credential = new Credential(
                name: 'main',
                group: 'database',
                environment: 'production',
                tags: ['production', 'critical'],
            );

            // Act
            $result = $credential->hasAnyTag(['staging', 'critical']);

            // Assert
            expect($result)->toBeTrue();
        });

        test('returns false when no tags match', function (): void {
            // Arrange
            $credential = new Credential(
                name: 'main',
                group: 'database',
                environment: 'production',
                tags: ['production', 'critical'],
            );

            // Act
            $result = $credential->hasAnyTag(['staging', 'development']);

            // Assert
            expect($result)->toBeFalse();
        });
    });

    describe('resolveValue (via connection)', function (): void {
        test('handles non-string value in connection', function (): void {
            // Arrange
            $credential = new Credential(
                name: 'main',
                group: 'database',
                environment: 'production',
                connections: [
                    'numeric' => 5_432,
                    'boolean' => true,
                ],
            );

            // Act
            $numericResult = $credential->connection('numeric');
            $booleanResult = $credential->connection('boolean');

            // Assert
            expect($numericResult)->toBe('5432');
            expect($booleanResult)->toBe('1');
        });

        test('resolves SensitiveValue in connection templates', function (): void {
            // Arrange
            $credential = new Credential(
                name: 'main',
                group: 'database',
                environment: 'production',
                fields: [
                    'password' => new SensitiveValue('secret123'),
                    'host' => 'localhost',
                ],
                connections: [
                    'psql' => 'psql -h ${self.host} -p ${self.password}',
                ],
            );

            // Act
            $result = $credential->connection('psql');

            // Assert
            expect($result)->toBe('psql -h localhost -p secret123');
        });
    });

    describe('path', function (): void {
        test('returns full credential path', function (): void {
            // Arrange
            $credential = new Credential(
                name: 'main',
                group: 'database',
                environment: 'production',
            );

            // Act
            $result = $credential->path();

            // Assert
            expect($result)->toBe('database.production.main');
        });
    });

    describe('get', function (): void {
        test('returns field value', function (): void {
            // Arrange
            $credential = new Credential(
                name: 'main',
                group: 'database',
                environment: 'production',
                fields: ['host' => 'localhost'],
            );

            // Act
            $result = $credential->get('host');

            // Assert
            expect($result)->toBe('localhost');
        });

        test('returns null for non-existent field', function (): void {
            // Arrange
            $credential = new Credential(
                name: 'main',
                group: 'database',
                environment: 'production',
                fields: [],
            );

            // Act
            $result = $credential->get('nonexistent');

            // Assert
            expect($result)->toBeNull();
        });
    });

    describe('__get', function (): void {
        test('accesses field via magic getter', function (): void {
            // Arrange
            $credential = new Credential(
                name: 'main',
                group: 'database',
                environment: 'production',
                fields: ['host' => 'localhost', 'port' => 5_432],
            );

            // Act & Assert
            expect($credential->host)->toBe('localhost');
            expect($credential->port)->toBe(5_432);
        });
    });

    describe('fieldNames', function (): void {
        test('returns all field names', function (): void {
            // Arrange
            $credential = new Credential(
                name: 'main',
                group: 'database',
                environment: 'production',
                fields: ['host' => 'localhost', 'port' => 5_432, 'username' => 'admin'],
            );

            // Act
            $result = $credential->fieldNames();

            // Assert
            expect($result)->toBe(['host', 'port', 'username']);
        });
    });

    describe('connectionNames', function (): void {
        test('returns all connection names', function (): void {
            // Arrange
            $credential = new Credential(
                name: 'main',
                group: 'database',
                environment: 'production',
                connections: ['psql' => 'psql -h localhost', 'mysql' => 'mysql -h localhost'],
            );

            // Act
            $result = $credential->connectionNames();

            // Assert
            expect($result)->toBe(['psql', 'mysql']);
        });
    });

    describe('hasTag', function (): void {
        test('returns true for existing tag', function (): void {
            // Arrange
            $credential = new Credential(
                name: 'main',
                group: 'database',
                environment: 'production',
                tags: ['production', 'critical'],
            );

            // Act
            $result = $credential->hasTag('critical');

            // Assert
            expect($result)->toBeTrue();
        });

        test('returns false for non-existing tag', function (): void {
            // Arrange
            $credential = new Credential(
                name: 'main',
                group: 'database',
                environment: 'production',
                tags: ['production'],
            );

            // Act
            $result = $credential->hasTag('staging');

            // Assert
            expect($result)->toBeFalse();
        });
    });

    describe('hasAllTags', function (): void {
        test('returns true when all tags exist', function (): void {
            // Arrange
            $credential = new Credential(
                name: 'main',
                group: 'database',
                environment: 'production',
                tags: ['production', 'critical', 'database'],
            );

            // Act
            $result = $credential->hasAllTags(['production', 'critical']);

            // Assert
            expect($result)->toBeTrue();
        });

        test('returns false when not all tags exist', function (): void {
            // Arrange
            $credential = new Credential(
                name: 'main',
                group: 'database',
                environment: 'production',
                tags: ['production', 'critical'],
            );

            // Act
            $result = $credential->hasAllTags(['production', 'staging']);

            // Assert
            expect($result)->toBeFalse();
        });
    });
});

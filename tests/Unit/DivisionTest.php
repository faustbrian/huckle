<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Huckle\Parser\Division;

describe('Division', function (): void {
    describe('matches()', function (): void {
        test('returns true when division matches context', function (): void {
            $division = new Division(
                name: 'FI',
            );

            expect($division->matches(['division' => 'FI']))->toBeTrue();
        });

        test('returns false when division does not match context', function (): void {
            $division = new Division(
                name: 'FI',
            );

            expect($division->matches(['division' => 'SE']))->toBeFalse();
        });

        test('returns true when no division in context', function (): void {
            $division = new Division(
                name: 'FI',
            );

            expect($division->matches([]))->toBeTrue();
        });
    });

    describe('exportsForContext()', function (): void {
        test('returns division-level exports', function (): void {
            $division = new Division(
                name: 'FI',
                fields: [
                    'region_code' => 'fi-001',
                ],
                exports: [
                    'REGION_CODE' => '${self.region_code}',
                ],
            );

            $exports = $division->exportsForContext(['division' => 'FI']);

            expect($exports)->toBe([
                'REGION_CODE' => 'fi-001',
            ]);
        });

        test('returns empty array when division does not match', function (): void {
            $division = new Division(
                name: 'FI',
                exports: [
                    'TEST' => 'value',
                ],
            );

            expect($division->exportsForContext(['division' => 'SE']))->toBe([]);
        });

        test('returns environment-level exports', function (): void {
            $division = new Division(
                name: 'FI',
                environments: [
                    'production' => [
                        'fields' => ['env_mode' => 'prod'],
                        'exports' => ['ENV_MODE' => '${self.env_mode}'],
                        'providers' => [],
                    ],
                ],
            );

            $exports = $division->exportsForContext([
                'division' => 'FI',
                'environment' => 'production',
            ]);

            expect($exports)->toBe(['ENV_MODE' => 'prod']);
        });

        test('returns provider-level exports', function (): void {
            $division = new Division(
                name: 'FI',
                environments: [
                    'production' => [
                        'fields' => [],
                        'exports' => [],
                        'providers' => [
                            'provider_a' => [
                                'fields' => ['api_key' => 'secret-key'],
                                'exports' => ['PROVIDER_A_API_KEY' => '${self.api_key}'],
                                'countries' => [],
                            ],
                        ],
                    ],
                ],
            );

            $exports = $division->exportsForContext([
                'division' => 'FI',
                'environment' => 'production',
                'provider' => 'provider_a',
            ]);

            expect($exports)->toBe(['PROVIDER_A_API_KEY' => 'secret-key']);
        });

        test('returns country-level exports', function (): void {
            $division = new Division(
                name: 'FI',
                environments: [
                    'production' => [
                        'fields' => [],
                        'exports' => [],
                        'providers' => [
                            'provider_b' => [
                                'fields' => ['username' => 'user'],
                                'exports' => ['PROVIDER_B_USER' => '${self.username}'],
                                'countries' => [
                                    'EE' => [
                                        'fields' => ['customer' => 'ee-customer'],
                                        'exports' => ['PROVIDER_B_CUSTOMER' => '${self.customer}'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            );

            $exports = $division->exportsForContext([
                'division' => 'FI',
                'environment' => 'production',
                'provider' => 'provider_b',
                'country' => 'EE',
            ]);

            expect($exports)->toBe([
                'PROVIDER_B_USER' => 'user',
                'PROVIDER_B_CUSTOMER' => 'ee-customer',
            ]);
        });

        test('accumulates exports from all matching levels', function (): void {
            $division = new Division(
                name: 'FI',
                fields: ['region' => 'nordic'],
                exports: ['REGION' => '${self.region}'],
                environments: [
                    'production' => [
                        'fields' => ['env' => 'prod'],
                        'exports' => ['ENV' => '${self.env}'],
                        'providers' => [
                            'provider_a' => [
                                'fields' => ['key' => 'secret'],
                                'exports' => ['API_KEY' => '${self.key}'],
                                'countries' => [],
                            ],
                        ],
                    ],
                ],
            );

            $exports = $division->exportsForContext([
                'division' => 'FI',
                'environment' => 'production',
                'provider' => 'provider_a',
            ]);

            expect($exports)->toBe([
                'REGION' => 'nordic',
                'ENV' => 'prod',
                'API_KEY' => 'secret',
            ]);
        });

        test('deeper levels override parent exports with same key', function (): void {
            $division = new Division(
                name: 'FI',
                fields: ['mode' => 'division-mode'],
                exports: ['MODE' => '${self.mode}'],
                environments: [
                    'production' => [
                        'fields' => ['mode' => 'env-mode'],
                        'exports' => ['MODE' => '${self.mode}'],
                        'providers' => [],
                    ],
                ],
            );

            $exports = $division->exportsForContext([
                'division' => 'FI',
                'environment' => 'production',
            ]);

            expect($exports['MODE'])->toBe('env-mode');
        });

        test('skips non-matching environments', function (): void {
            $division = new Division(
                name: 'FI',
                environments: [
                    'production' => [
                        'fields' => [],
                        'exports' => ['ENV' => 'prod'],
                        'providers' => [],
                    ],
                    'staging' => [
                        'fields' => [],
                        'exports' => ['ENV' => 'staging'],
                        'providers' => [],
                    ],
                ],
            );

            $exports = $division->exportsForContext([
                'division' => 'FI',
                'environment' => 'staging',
            ]);

            expect($exports['ENV'])->toBe('staging');
        });

        test('skips non-matching providers', function (): void {
            $division = new Division(
                name: 'FI',
                environments: [
                    'production' => [
                        'fields' => [],
                        'exports' => [],
                        'providers' => [
                            'provider_a' => [
                                'fields' => [],
                                'exports' => ['PROVIDER' => 'provider_a'],
                                'countries' => [],
                            ],
                            'provider_c' => [
                                'fields' => [],
                                'exports' => ['PROVIDER' => 'provider_c'],
                                'countries' => [],
                            ],
                        ],
                    ],
                ],
            );

            $exports = $division->exportsForContext([
                'division' => 'FI',
                'environment' => 'production',
                'provider' => 'provider_c',
            ]);

            expect($exports['PROVIDER'])->toBe('provider_c');
        });

        test('returns service-level exports at country level', function (): void {
            $division = new Division(
                name: 'FI',
                environments: [
                    'production' => [
                        'fields' => [],
                        'exports' => [],
                        'providers' => [
                            'provider_a' => [
                                'fields' => [],
                                'exports' => [],
                                'countries' => [
                                    'SE' => [
                                        'fields' => [],
                                        'exports' => [],
                                        'services' => [
                                            'service_a' => [
                                                'fields' => ['rate' => '9.99'],
                                                'exports' => ['SERVICE_RATE' => '${self.rate}'],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            );

            $exports = $division->exportsForContext([
                'division' => 'FI',
                'environment' => 'production',
                'provider' => 'provider_a',
                'country' => 'SE',
                'service' => 'service_a',
            ]);

            expect($exports)->toBe(['SERVICE_RATE' => '9.99']);
        });

        test('returns service-level exports at provider level', function (): void {
            $division = new Division(
                name: 'FI',
                environments: [
                    'production' => [
                        'fields' => [],
                        'exports' => [],
                        'providers' => [
                            'provider_c' => [
                                'fields' => [],
                                'exports' => [],
                                'countries' => [],
                                'services' => [
                                    'service_c' => [
                                        'fields' => ['speed' => 'fast'],
                                        'exports' => ['PROVIDER_C_SPEED' => '${self.speed}'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            );

            $exports = $division->exportsForContext([
                'division' => 'FI',
                'environment' => 'production',
                'provider' => 'provider_c',
                'service' => 'service_c',
            ]);

            expect($exports)->toBe(['PROVIDER_C_SPEED' => 'fast']);
        });

        test('skips non-matching services at country level', function (): void {
            $division = new Division(
                name: 'FI',
                environments: [
                    'production' => [
                        'fields' => [],
                        'exports' => [],
                        'providers' => [
                            'provider_a' => [
                                'fields' => [],
                                'exports' => [],
                                'countries' => [
                                    'SE' => [
                                        'fields' => [],
                                        'exports' => [],
                                        'services' => [
                                            'service_a' => [
                                                'fields' => [],
                                                'exports' => ['SERVICE' => 'service_a'],
                                            ],
                                            'service_b' => [
                                                'fields' => [],
                                                'exports' => ['SERVICE' => 'service_b'],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            );

            $exports = $division->exportsForContext([
                'division' => 'FI',
                'environment' => 'production',
                'provider' => 'provider_a',
                'country' => 'SE',
                'service' => 'service_b',
            ]);

            expect($exports['SERVICE'])->toBe('service_b');
        });

        test('skips non-matching services at provider level', function (): void {
            $division = new Division(
                name: 'FI',
                environments: [
                    'production' => [
                        'fields' => [],
                        'exports' => [],
                        'providers' => [
                            'provider_c' => [
                                'fields' => [],
                                'exports' => [],
                                'countries' => [],
                                'services' => [
                                    'service_c' => [
                                        'fields' => [],
                                        'exports' => ['SERVICE' => 'service_c'],
                                    ],
                                    'service_d' => [
                                        'fields' => [],
                                        'exports' => ['SERVICE' => 'service_d'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            );

            $exports = $division->exportsForContext([
                'division' => 'FI',
                'environment' => 'production',
                'provider' => 'provider_c',
                'service' => 'service_d',
            ]);

            expect($exports['SERVICE'])->toBe('service_d');
        });

        test('handles non-scalar field values gracefully', function (): void {
            $division = new Division(
                name: 'FI',
                fields: [
                    'array_field' => ['nested' => 'value'],
                ],
                exports: [
                    'ARRAY_EXPORT' => '${self.array_field}',
                ],
            );

            $exports = $division->exportsForContext(['division' => 'FI']);

            expect($exports['ARRAY_EXPORT'])->toBe('');
        });

        test('handles non-string export values gracefully', function (): void {
            $division = new Division(
                name: 'FI',
                fields: [
                    'number' => 42,
                ],
                exports: [
                    'NUMBER_EXPORT' => '${self.number}',
                ],
            );

            $exports = $division->exportsForContext(['division' => 'FI']);

            expect($exports['NUMBER_EXPORT'])->toBe('42');
        });
    });

    describe('environmentNames()', function (): void {
        test('returns all environment names', function (): void {
            $division = new Division(
                name: 'FI',
                environments: [
                    'production' => ['fields' => [], 'exports' => [], 'providers' => []],
                    'staging' => ['fields' => [], 'exports' => [], 'providers' => []],
                ],
            );

            expect($division->environmentNames())->toBe(['production', 'staging']);
        });

        test('returns empty array when no environments', function (): void {
            $division = new Division(name: 'FI');

            expect($division->environmentNames())->toBe([]);
        });
    });
});

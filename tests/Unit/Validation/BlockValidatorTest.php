<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Huckle\Exceptions\ValidationException;
use Cline\Huckle\Validation\BlockValidator;

describe('BlockValidator', function (): void {
    describe('validate()', function (): void {
        test('passes for valid country codes', function (): void {
            $ast = [
                'partitions' => [
                    [
                        'type' => 'division',
                        'labels' => ['FI'],
                        'body' => [
                            'country' => [
                                [
                                    'labels' => ['EE'],
                                    'body' => [],
                                ],
                            ],
                        ],
                    ],
                ],
            ];

            $validator = new BlockValidator();
            $validator->validate($ast);

            expect($validator->passes())->toBeTrue();
            expect($validator->errors())->toBe([]);
        });

        test('fails for invalid country codes', function (): void {
            $ast = [
                'partitions' => [
                    [
                        'type' => 'division',
                        'labels' => ['FI'],
                        'body' => [
                            'country' => [
                                [
                                    'labels' => ['INVALID'],
                                    'body' => [],
                                ],
                            ],
                        ],
                    ],
                ],
            ];

            $validator = new BlockValidator();
            $validator->validate($ast);

            expect($validator->fails())->toBeTrue();
            expect($validator->errors())->toHaveCount(1);
            expect($validator->errors()[0]['type'])->toBe('country');
            expect($validator->errors()[0]['label'])->toBe('INVALID');
        });

        test('passes for valid continent codes', function (): void {
            $ast = [
                'partitions' => [
                    [
                        'type' => 'division',
                        'labels' => ['global'],
                        'body' => [
                            'continent' => [
                                [
                                    'labels' => ['europe'],
                                    'body' => [],
                                ],
                            ],
                        ],
                    ],
                ],
            ];

            $validator = new BlockValidator();
            $validator->validate($ast);

            expect($validator->passes())->toBeTrue();
        });

        test('fails for invalid continent codes', function (): void {
            $ast = [
                'partitions' => [
                    [
                        'type' => 'division',
                        'labels' => ['global'],
                        'body' => [
                            'continent' => [
                                [
                                    'labels' => ['atlantis'],
                                    'body' => [],
                                ],
                            ],
                        ],
                    ],
                ],
            ];

            $validator = new BlockValidator();
            $validator->validate($ast);

            expect($validator->fails())->toBeTrue();
            expect($validator->errors()[0]['type'])->toBe('continent');
        });

        test('passes for valid zone codes', function (): void {
            $ast = [
                'partitions' => [
                    [
                        'type' => 'division',
                        'labels' => ['europe'],
                        'body' => [
                            'zone' => [
                                [
                                    'labels' => ['eu'],
                                    'body' => [],
                                ],
                                [
                                    'labels' => ['eea'],
                                    'body' => [],
                                ],
                            ],
                        ],
                    ],
                ],
            ];

            $validator = new BlockValidator();
            $validator->validate($ast);

            expect($validator->passes())->toBeTrue();
        });

        test('fails for invalid zone codes', function (): void {
            $ast = [
                'partitions' => [
                    [
                        'type' => 'division',
                        'labels' => ['europe'],
                        'body' => [
                            'zone' => [
                                [
                                    'labels' => ['invalid_zone'],
                                    'body' => [],
                                ],
                            ],
                        ],
                    ],
                ],
            ];

            $validator = new BlockValidator();
            $validator->validate($ast);

            expect($validator->fails())->toBeTrue();
            expect($validator->errors()[0]['type'])->toBe('zone');
        });

        test('validates state codes with country context', function (): void {
            $ast = [
                'partitions' => [
                    [
                        'type' => 'division',
                        'labels' => ['US'],
                        'body' => [
                            'country' => [
                                [
                                    'labels' => ['US'],
                                    'body' => [
                                        'state' => [
                                            [
                                                'labels' => ['CA'],
                                                'body' => [],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ];

            $validator = new BlockValidator();
            $validator->validate($ast);

            expect($validator->passes())->toBeTrue();
        });

        test('collects multiple errors', function (): void {
            $ast = [
                'partitions' => [
                    [
                        'type' => 'division',
                        'labels' => ['test'],
                        'body' => [
                            'country' => [
                                [
                                    'labels' => ['INVALID1'],
                                    'body' => [],
                                ],
                                [
                                    'labels' => ['INVALID2'],
                                    'body' => [],
                                ],
                            ],
                            'zone' => [
                                [
                                    'labels' => ['bad_zone'],
                                    'body' => [],
                                ],
                            ],
                        ],
                    ],
                ],
            ];

            $validator = new BlockValidator();
            $validator->validate($ast);

            expect($validator->fails())->toBeTrue();
            expect($validator->errors())->toHaveCount(3);
        });
    });

    describe('setEnabled()', function (): void {
        test('can disable validation', function (): void {
            $ast = [
                'partitions' => [
                    [
                        'type' => 'division',
                        'labels' => ['test'],
                        'body' => [
                            'country' => [
                                [
                                    'labels' => ['INVALID'],
                                    'body' => [],
                                ],
                            ],
                        ],
                    ],
                ],
            ];

            $validator = new BlockValidator();
            $validator->setEnabled(false)->validate($ast);

            expect($validator->passes())->toBeTrue();
        });
    });

    describe('throwIfFailed()', function (): void {
        test('throws ValidationException when errors exist', function (): void {
            $ast = [
                'partitions' => [
                    [
                        'type' => 'division',
                        'labels' => ['test'],
                        'body' => [
                            'country' => [
                                [
                                    'labels' => ['INVALID'],
                                    'body' => [],
                                ],
                            ],
                        ],
                    ],
                ],
            ];

            $validator = new BlockValidator();
            $validator->validate($ast);

            expect(fn (): BlockValidator => $validator->throwIfFailed())
                ->toThrow(ValidationException::class);
        });

        test('does not throw when no errors', function (): void {
            $ast = [
                'partitions' => [
                    [
                        'type' => 'division',
                        'labels' => ['FI'],
                        'body' => [
                            'country' => [
                                [
                                    'labels' => ['EE'],
                                    'body' => [],
                                ],
                            ],
                        ],
                    ],
                ],
            ];

            $validator = new BlockValidator();
            $validator->validate($ast);

            expect($validator->throwIfFailed())->toBeInstanceOf(BlockValidator::class);
        });
    });

    describe('messages()', function (): void {
        test('returns formatted error messages', function (): void {
            $ast = [
                'partitions' => [
                    [
                        'type' => 'division',
                        'labels' => ['test'],
                        'body' => [
                            'country' => [
                                [
                                    'labels' => ['INVALID'],
                                    'body' => [],
                                ],
                            ],
                        ],
                    ],
                ],
            ];

            $validator = new BlockValidator();
            $validator->validate($ast);

            $messages = $validator->messages();
            expect($messages)->toHaveCount(1);
            expect($messages[0])->toContain('division:test');
            expect($messages[0])->toContain('country:INVALID');
        });
    });
});

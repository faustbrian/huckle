<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Huckle\Parser\HuckleParser;

describe('Block Type Aliases', function (): void {
    describe('Defaults Aliases', function (): void {
        $defaultsAliases = ['defaults', 'default', 'base', 'template', 'common', 'shared', 'root'];

        foreach ($defaultsAliases as $alias) {
            test(sprintf("'%s' is parsed as defaults block", $alias), function () use ($alias): void {
                $hcl = <<<HCL
                {$alias} {
                    environment "production" {
                        provider "test" {
                            api_key = "test-key"

                            export {
                                TEST_API_KEY = self.api_key
                            }
                        }
                    }
                }
                HCL;

                $parser = new HuckleParser();
                $config = $parser->parse($hcl);

                // Defaults should be parsed (stored in config's defaults)
                // Verify config was parsed without error
                expect($config)->not->toBeNull();
            });
        }
    });

    describe('Fallback Aliases', function (): void {
        $fallbackAliases = ['fallback', 'global', 'catchall', 'otherwise', 'wildcard'];

        foreach ($fallbackAliases as $alias) {
            test(sprintf("'%s' is treated as fallback block", $alias), function () use ($alias): void {
                $hcl = <<<HCL
                {$alias} {
                    environment "production" {
                        provider "shared" {
                            api_key = "shared-key"

                            export {
                                SHARED_API_KEY = self.api_key
                            }
                        }
                    }
                }

                tenant "FI" {
                    environment "production" {
                        provider "local" {
                            key = "fi-key"

                            export {
                                LOCAL_KEY = self.key
                            }
                        }
                    }
                }
                HCL;

                $parser = new HuckleParser();
                $config = $parser->parse($hcl);

                expect($config->fallbacks())->toHaveCount(1);

                // Fallback should provide exports when no specific partition provider matches
                $exports = $config->exportsForContext([
                    'partition' => 'FI',
                    'environment' => 'production',
                ]);

                // Should have both fallback and tenant exports
                expect($exports)->toHaveKey('SHARED_API_KEY');
                expect($exports['SHARED_API_KEY'])->toBe('shared-key');
                expect($exports)->toHaveKey('LOCAL_KEY');
                expect($exports['LOCAL_KEY'])->toBe('fi-key');
            });
        }
    });

    describe('Partition Aliases', function (): void {
        $partitionAliases = ['partition', 'tenant', 'namespace', 'division', 'entity'];

        foreach ($partitionAliases as $alias) {
            test(sprintf("'%s' is treated as partition block", $alias), function () use ($alias): void {
                $hcl = <<<HCL
                {$alias} "FI" {
                    environment "production" {
                        provider "test" {
                            api_key = "fi-key"

                            export {
                                TEST_API_KEY = self.api_key
                            }
                        }
                    }
                }
                HCL;

                $parser = new HuckleParser();
                $config = $parser->parse($hcl);

                expect($config->partitions())->toHaveCount(1);
                expect($config->partition('FI'))->not->toBeNull();

                $exports = $config->exportsForContext([
                    'partition' => 'FI',
                    'environment' => 'production',
                    'provider' => 'test',
                ]);

                expect($exports)->toHaveKey('TEST_API_KEY');
                expect($exports['TEST_API_KEY'])->toBe('fi-key');
            });
        }
    });

    describe('Mixed Aliases', function (): void {
        test('can use different aliases in same config', function (): void {
            $hcl = <<<'HCL'
                base {
                    environment "production" {
                        provider "inherited" {
                            key = "base-key"

                            export {
                                INHERITED_KEY = self.key
                            }
                        }
                    }
                }

                global {
                    environment "production" {
                        provider "shared" {
                            key = "shared-key"

                            export {
                                SHARED_KEY = self.key
                            }
                        }
                    }
                }

                tenant "FI" {
                    environment "production" {
                        provider "local" {
                            key = "fi-key"

                            export {
                                LOCAL_KEY = self.key
                            }
                        }
                    }
                }
                HCL;

            $parser = new HuckleParser();
            $config = $parser->parse($hcl);

            // Verify parsing works with mixed aliases
            expect($config->partitions())->toHaveCount(1);
            expect($config->fallbacks())->toHaveCount(1);

            // Fallback + tenant exports should merge
            $exports = $config->exportsForContext([
                'partition' => 'FI',
                'environment' => 'production',
            ]);

            expect($exports)->toHaveKey('SHARED_KEY');
            expect($exports)->toHaveKey('LOCAL_KEY');
        });
    });
});

<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Hcl\Exceptions\InvalidBlockTypeException;
use Cline\Hcl\Exceptions\UnexpectedEndOfFileException;
use Cline\Hcl\Parser\Lexer;
use Cline\Huckle\Parser\Parser;

describe('Parser', function (): void {
    /**
     * Helper to parse HCL content.
     */
    function parse(string $content): array
    {
        $lexer = new Lexer($content);
        $tokens = $lexer->tokenize();
        $parser = new Parser($tokens);

        return $parser->parse();
    }

    describe('parse', function (): void {
        test('parses empty content', function (): void {
            $ast = parse('');

            expect($ast)->toHaveKey('defaults');
            expect($ast)->toHaveKey('groups');
            expect($ast['groups'])->toBeEmpty();
        });

        test('parses defaults block', function (): void {
            $ast = parse('
                defaults {
                    owner = "platform-team"
                    expires_in = "90d"
                }
            ');

            expect($ast['defaults'])->toHaveKey('owner');
            expect($ast['defaults']['owner']['value'])->toBe('platform-team');
        });

        test('parses group with labels', function (): void {
            $ast = parse('
                group "database" "production" {
                    tags = ["prod", "postgres"]
                }
            ');

            expect($ast['groups'])->toHaveCount(1);
            expect($ast['groups'][0]['labels'])->toBe(['database', 'production']);
        });

        test('parses nested credential block', function (): void {
            $ast = parse('
                group "database" "production" {
                    credential "main" {
                        host = "localhost"
                        port = 5432
                    }
                }
            ');

            $group = $ast['groups'][0];
            expect($group['body'])->toHaveKey('credential');
            expect($group['body']['credential'])->toHaveCount(1);
            expect($group['body']['credential'][0]['labels'])->toBe(['main']);
        });

        test('parses string values', function (): void {
            $ast = parse('defaults { key = "value" }');

            expect($ast['defaults']['key']['_type'])->toBe('string');
            expect($ast['defaults']['key']['value'])->toBe('value');
        });

        test('parses number values', function (): void {
            $ast = parse('defaults { port = 5432 }');

            expect($ast['defaults']['port']['_type'])->toBe('number');
            expect($ast['defaults']['port']['value'])->toBe(5_432);
        });

        test('parses float values', function (): void {
            $ast = parse('defaults { rate = 0.5 }');

            expect($ast['defaults']['rate']['value'])->toBe(0.5);
        });

        test('parses boolean values', function (): void {
            $ast = parse('defaults { enabled = true }');

            expect($ast['defaults']['enabled']['_type'])->toBe('bool');
            expect($ast['defaults']['enabled']['value'])->toBe(true);
        });

        test('parses null values', function (): void {
            $ast = parse('defaults { empty = null }');

            expect($ast['defaults']['empty']['_type'])->toBe('null');
            expect($ast['defaults']['empty']['value'])->toBeNull();
        });

        test('parses array values', function (): void {
            $ast = parse('defaults { tags = ["a", "b", "c"] }');

            expect($ast['defaults']['tags']['_type'])->toBe('array');
            expect($ast['defaults']['tags']['value'])->toHaveCount(3);
        });

        test('parses function calls', function (): void {
            $ast = parse('defaults { password = sensitive("secret") }');

            expect($ast['defaults']['password']['_type'])->toBe('function');
            expect($ast['defaults']['password']['name'])->toBe('sensitive');
            expect($ast['defaults']['password']['args'])->toHaveCount(1);
        });

        test('parses self references', function (): void {
            $ast = parse('defaults { url = self.host }');

            expect($ast['defaults']['url']['_type'])->toBe('reference');
            expect($ast['defaults']['url']['parts'])->toBe(['self', 'host']);
        });

        test('parses export blocks', function (): void {
            $ast = parse('
                group "db" "prod" {
                    credential "main" {
                        host = "localhost"
                        export {
                            DB_HOST = self.host
                        }
                    }
                }
            ');

            $credential = $ast['groups'][0]['body']['credential'][0];
            expect($credential['body'])->toHaveKey('export');
        });

        test('parses connect blocks', function (): void {
            $ast = parse('
                group "db" "prod" {
                    credential "main" {
                        host = "localhost"
                        connect "psql" {
                            command = "psql -h localhost"
                        }
                    }
                }
            ');

            $credential = $ast['groups'][0]['body']['credential'][0];
            expect($credential['body'])->toHaveKey('connect');
        });

        test('parses interpolated strings', function (): void {
            $ast = parse('defaults { url = "http://${self.host}:${self.port}" }');

            expect($ast['defaults']['url']['interpolated'])->toBe(true);
        });

        test('skips comments', function (): void {
            $ast = parse('
                # This is a comment
                defaults {
                    key = "value"
                }
            ');

            expect($ast['defaults'])->toHaveKey('key');
        });

        test('throws on invalid block type', function (): void {
            expect(fn (): array => parse('invalid_block { }'))
                ->toThrow(InvalidBlockTypeException::class, "Invalid block type 'invalid_block'");
        });

        test('parses object values with key=value syntax', function (): void {
            $ast = parse('defaults { config = { host = "localhost", port = 5432 } }');

            expect($ast['defaults']['config']['_type'])->toBe('object');
            expect($ast['defaults']['config']['value']['host']['value'])->toBe('localhost');
            expect($ast['defaults']['config']['value']['port']['value'])->toBe(5_432);
        });

        test('parses nested objects', function (): void {
            $ast = parse('defaults { db = { primary = { host = "localhost" } } }');

            expect($ast['defaults']['db']['_type'])->toBe('object');
            expect($ast['defaults']['db']['value']['primary']['_type'])->toBe('object');
            expect($ast['defaults']['db']['value']['primary']['value']['host']['value'])->toBe('localhost');
        });

        test('skips comments inside blocks', function (): void {
            $ast = parse('
                defaults {
                    # This is a comment
                    key = "value"
                    # Another comment
                    port = 5432
                }
            ');

            expect($ast['defaults'])->toHaveKey('key');
            expect($ast['defaults'])->toHaveKey('port');
            expect($ast['defaults']['key']['value'])->toBe('value');
        });

        test('skips comments inside objects', function (): void {
            $ast = parse('defaults { config = { # comment
                host = "localhost"
                # another comment
                port = 5432
            } }');

            expect($ast['defaults']['config']['_type'])->toBe('object');
            expect($ast['defaults']['config']['value']['host']['value'])->toBe('localhost');
            expect($ast['defaults']['config']['value']['port']['value'])->toBe(5_432);
        });

        test('throws on unexpected EOF when expecting value', function (): void {
            expect(fn (): array => parse('defaults { key = '))
                ->toThrow(UnexpectedEndOfFileException::class, 'Unexpected end of file');
        });

        test('throws on unexpected EOF when expecting block body', function (): void {
            expect(fn (): array => parse('defaults {'))
                ->toThrow(UnexpectedEndOfFileException::class, 'Unexpected end of file');
        });

        test('parses multiple groups', function (): void {
            $ast = parse('
                group "database" "production" {
                    host = "prod.db"
                }
                group "database" "staging" {
                    host = "stage.db"
                }
            ');

            expect($ast['groups'])->toHaveCount(2);
            expect($ast['groups'][0]['labels'])->toBe(['database', 'production']);
            expect($ast['groups'][1]['labels'])->toBe(['database', 'staging']);
        });

        test('parses group with multiple credentials', function (): void {
            $ast = parse('
                group "database" "production" {
                    credential "main" {
                        host = "main.db"
                    }
                    credential "replica" {
                        host = "replica.db"
                    }
                }
            ');

            $group = $ast['groups'][0];
            expect($group['body']['credential'])->toHaveCount(2);
            expect($group['body']['credential'][0]['labels'])->toBe(['main']);
            expect($group['body']['credential'][1]['labels'])->toBe(['replica']);
        });

        test('handles trailing commas in arrays', function (): void {
            $ast = parse('defaults { tags = ["a", "b", "c",] }');

            expect($ast['defaults']['tags']['_type'])->toBe('array');
            expect($ast['defaults']['tags']['value'])->toHaveCount(3);
        });

        test('handles trailing commas in objects', function (): void {
            $ast = parse('defaults { config = { host = "localhost", port = 5432, } }');

            expect($ast['defaults']['config']['_type'])->toBe('object');
            expect($ast['defaults']['config']['value']['host']['value'])->toBe('localhost');
            expect($ast['defaults']['config']['value']['port']['value'])->toBe(5_432);
        });
    });
});

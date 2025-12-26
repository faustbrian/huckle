<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Huckle\Parser;

use Cline\Hcl\Exceptions\InvalidBlockTypeException;
use Cline\Hcl\Exceptions\ParserException;
use Cline\Hcl\Exceptions\UnexpectedEndOfFileException;
use Cline\Hcl\Exceptions\UnexpectedTokenException;
use Cline\Hcl\Parser\Token;
use Cline\Hcl\Parser\TokenType;

use function count;
use function in_array;
use function str_contains;

/**
 * Parses HCL token streams into an abstract syntax tree representation.
 *
 * Converts a flat token stream from the lexer into a nested AST structure
 * that represents blocks, attributes, and nested relationships in the HCL
 * configuration. Supports standard HCL constructs including blocks with labels,
 * attribute assignments, and complex value types (objects, arrays, functions).
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class Parser
{
    /**
     * Valid block type identifiers recognized by the parser.
     */
    private const array BLOCK_TYPES = ['defaults', 'default', 'base', 'template', 'common', 'shared', 'root', 'group', 'credential', 'export', 'config', 'connect', 'environment', 'provider', 'country', 'service', 'carrier', 'organization', 'team', 'user', 'region', 'client', 'continent', 'state', 'zone', 'fallback', 'global', 'catchall', 'otherwise', 'wildcard', 'partition', 'tenant', 'namespace', 'division', 'entity', 'mappings'];

    /**
     * Block types that are treated as defaults (base configuration inherited by all).
     * All these types have identical semantics - the choice is purely for domain clarity.
     */
    private const array DEFAULTS_TYPES = ['defaults', 'default', 'base', 'template', 'common', 'shared', 'root'];

    /**
     * Block types that are treated as fallbacks (catch-all when no partition matches).
     * All these types have identical semantics - the choice is purely for domain clarity.
     */
    private const array FALLBACK_TYPES = ['fallback', 'global', 'catchall', 'otherwise', 'wildcard'];

    /**
     * Block types that are treated as top-level partitions.
     * All these types have identical semantics - the choice is purely for domain clarity.
     */
    private const array PARTITION_TYPES = ['partition', 'tenant', 'namespace', 'division', 'entity'];

    /**
     * Current position in the token stream.
     */
    private int $position = 0;

    /**
     * Create a new parser instance.
     *
     * @param array<Token> $tokens Token stream from the lexer to parse into an AST.
     *                             The parser maintains a read-only reference to avoid
     *                             accidental modification during parsing operations.
     */
    public function __construct(
        private readonly array $tokens,
    ) {}

    /**
     * Parse the token stream into an abstract syntax tree.
     *
     * Processes all tokens, building a structured AST with defaults, groups,
     * partitions, and fallbacks as top-level keys. Skips comments and newlines.
     *
     * @throws ParserException When encountering invalid syntax or unexpected tokens
     *
     * @return array<string, mixed> Parsed AST with top-level structure
     */
    public function parse(): array
    {
        $ast = [
            'defaults' => [],
            'groups' => [],
            'partitions' => [],
            'fallbacks' => [],
            'mappings' => [],
        ];

        $this->skipNewlines();

        while (!$this->isAtEnd()) {
            $this->skipNewlines();

            if ($this->isAtEnd()) {
                break;
            }

            // Skip comments
            if ($this->check(TokenType::Comment)) {
                $this->advance();

                continue;
            }

            $block = $this->parseBlock();

            if ($block === null) {
                continue;
            }

            if (in_array($block['type'], self::DEFAULTS_TYPES, true)) {
                $ast['defaults'] = $block['body'];
            } elseif ($block['type'] === 'group') {
                $ast['groups'][] = $block;
            } elseif (in_array($block['type'], self::PARTITION_TYPES, true)) {
                $ast['partitions'][] = $block;
            } elseif (in_array($block['type'], self::FALLBACK_TYPES, true)) {
                $ast['fallbacks'][] = $block;
            } elseif ($block['type'] === 'mappings') {
                $ast['mappings'] = $block['body'];
            }

            $this->skipNewlines();
        }

        return $ast;
    }

    /**
     * Parse a single block definition with type, labels, and body.
     *
     * Handles block syntax like: `group "database" "production" { ... }` where
     * "group" is the type and strings are labels.
     *
     * @throws ParserException When block type is invalid or syntax is malformed
     *
     * @return null|array<string, mixed> Parsed block structure or null for comments
     */
    private function parseBlock(): ?array
    {
        $this->skipNewlines();

        if ($this->check(TokenType::Comment)) {
            $this->advance();

            return null;
        }

        $typeToken = $this->consume(TokenType::Identifier, 'block type');
        $type = $typeToken->value;

        if (!in_array($type, self::BLOCK_TYPES, true)) {
            throw InvalidBlockTypeException::at($type, $typeToken->line, $typeToken->column);
        }

        // Parse block labels (e.g., group "database" "production")
        $labels = [];

        while ($this->check(TokenType::String) || $this->check(TokenType::Identifier)) {
            $labels[] = $this->advance()->value;
        }

        $this->skipNewlines();
        $this->consume(TokenType::LeftBrace, '{');
        $this->skipNewlines();

        $body = $this->parseBlockBody();

        $this->skipNewlines();
        $this->consume(TokenType::RightBrace, '}');

        return [
            'type' => $type,
            'labels' => $labels,
            'body' => $body,
        ];
    }

    /**
     * Parse the body contents of a block between braces.
     *
     * Processes attribute assignments and nested blocks. Handles both simple
     * assignments (`key = value`) and nested blocks with labels.
     *
     * @throws ParserException When encountering invalid syntax in block body
     *
     * @return array<string, mixed> Parsed body as an associative array
     */
    private function parseBlockBody(): array
    {
        $body = [];

        while (!$this->check(TokenType::RightBrace) && !$this->isAtEnd()) {
            $this->skipNewlines();

            if ($this->check(TokenType::RightBrace)) {
                break;
            }

            // Skip comments
            if ($this->check(TokenType::Comment)) {
                $this->advance();

                continue;
            }

            // Accept identifier or reserved keywords (like 'if') as keys
            $keyToken = $this->consumeKeyOrIdentifier();
            $key = $keyToken->value;

            $this->skipNewlines();

            // Check if this is a nested block
            if ($this->check(TokenType::String) || $this->check(TokenType::LeftBrace)) {
                // It's a nested block
                $labels = [];

                while ($this->check(TokenType::String) || $this->check(TokenType::Identifier)) {
                    if ($this->check(TokenType::LeftBrace)) {
                        break;
                    }

                    $labels[] = $this->advance()->value;
                }

                $this->skipNewlines();
                $this->consume(TokenType::LeftBrace, '{');
                $this->skipNewlines();

                $nestedBody = $this->parseBlockBody();

                $this->skipNewlines();
                $this->consume(TokenType::RightBrace, '}');

                if (!isset($body[$key])) {
                    $body[$key] = [];
                }

                /** @var array<int, array<string, mixed>> $bodyKey */
                $bodyKey = &$body[$key];
                $bodyKey[] = [
                    'labels' => $labels,
                    'body' => $nestedBody,
                ];
            } elseif ($this->check(TokenType::Equals)) {
                // It's an assignment
                $this->advance(); // consume =
                $this->skipNewlines();
                $body[$key] = $this->parseValue();
            }

            $this->skipNewlines();
        }

        return $body;
    }

    /**
     * Parse a value expression based on the next token type.
     *
     * Dispatches to specialized methods for primitives (string, number, bool, null),
     * collections (array, object), and references (function calls, variables).
     *
     * @throws ParserException When encountering an unexpected token type
     *
     * @return mixed Parsed value with type metadata
     */
    private function parseValue(): mixed
    {
        if ($this->isAtEnd()) {
            throw UnexpectedEndOfFileException::whileParsing('value');
        }

        $token = $this->peek();

        return match ($token->type) {
            TokenType::String, TokenType::Interpolation => $this->parseString(),
            TokenType::Number => $this->parseNumber(),
            TokenType::Bool => $this->parseBool(),
            TokenType::Null => $this->parseNull(),
            TokenType::LeftBracket => $this->parseArray(),
            TokenType::LeftBrace => $this->parseObject(),
            TokenType::Identifier => $this->parseFunctionOrReference(),
            default => throw UnexpectedTokenException::at($token, 'value'),
        };
    }

    /**
     * Parse a string value token.
     *
     * Preserves interpolation status for later evaluation.
     *
     * @return array{_type: string, value: string, interpolated: bool} Parsed string with metadata
     */
    private function parseString(): array
    {
        $token = $this->advance();

        return [
            '_type' => 'string',
            'value' => $token->value,
            'interpolated' => $token->type === TokenType::Interpolation,
        ];
    }

    /**
     * Parse a number value token, determining int or float type.
     *
     * Uses decimal point presence to determine numeric type.
     *
     * @return array{_type: string, value: float|int} Parsed number with metadata
     */
    private function parseNumber(): array
    {
        $token = $this->advance();
        $value = str_contains($token->value, '.') ? (float) $token->value : (int) $token->value;

        return [
            '_type' => 'number',
            'value' => $value,
        ];
    }

    /**
     * Parse a boolean value token.
     *
     * Converts string representation to PHP boolean.
     *
     * @return array{_type: string, value: bool} Parsed boolean with metadata
     */
    private function parseBool(): array
    {
        $token = $this->advance();

        return [
            '_type' => 'bool',
            'value' => $token->value === 'true',
        ];
    }

    /**
     * Parse a null value token.
     *
     * @return array{_type: string, value: null} Parsed null with metadata
     */
    private function parseNull(): array
    {
        $this->advance();

        return [
            '_type' => 'null',
            'value' => null,
        ];
    }

    /**
     * Parse an array literal enclosed in square brackets.
     *
     * Handles comma-separated values with optional trailing commas and nested arrays.
     *
     * @return array{_type: string, value: array<mixed>} Parsed array with metadata
     */
    private function parseArray(): array
    {
        $this->consume(TokenType::LeftBracket, '[');
        $this->skipNewlines();

        $elements = [];

        while (!$this->check(TokenType::RightBracket) && !$this->isAtEnd()) {
            $this->skipNewlines();

            if ($this->check(TokenType::RightBracket)) {
                break;
            }

            $elements[] = $this->parseValue();
            $this->skipNewlines();

            if (!$this->check(TokenType::Comma)) {
                continue;
            }

            $this->advance();
            $this->skipNewlines();
        }

        $this->consume(TokenType::RightBracket, ']');

        return [
            '_type' => 'array',
            'value' => $elements,
        ];
    }

    /**
     * Parse an object/map literal enclosed in curly braces.
     *
     * Handles key-value pairs with optional equals signs and commas. Supports
     * nested objects and skips comments.
     *
     * @return array{_type: string, value: array<string, mixed>} Parsed object with metadata
     */
    private function parseObject(): array
    {
        $this->consume(TokenType::LeftBrace, '{');
        $this->skipNewlines();

        $entries = [];

        while (!$this->check(TokenType::RightBrace) && !$this->isAtEnd()) {
            $this->skipNewlines();

            if ($this->check(TokenType::RightBrace)) {
                break;
            }

            // Skip comments
            if ($this->check(TokenType::Comment)) {
                $this->advance();

                continue;
            }

            $keyToken = $this->advance();
            $key = $keyToken->value;

            $this->skipNewlines();

            if ($this->check(TokenType::Equals)) {
                $this->advance();
            }

            $this->skipNewlines();
            $entries[$key] = $this->parseValue();
            $this->skipNewlines();

            if ($this->check(TokenType::Comma)) {
                $this->advance();
            }

            $this->skipNewlines();
        }

        $this->consume(TokenType::RightBrace, '}');

        return [
            '_type' => 'object',
            'value' => $entries,
        ];
    }

    /**
     * Parse a function call or variable reference expression.
     *
     * Distinguishes between function calls (with parentheses), dotted references
     * (like credential.db.host), and plain identifiers.
     *
     * @return array{_type: string, name?: string, args?: array<mixed>, parts?: array<string>, value?: string} Parsed function/reference with metadata
     */
    private function parseFunctionOrReference(): array
    {
        $nameToken = $this->advance();
        $name = $nameToken->value;

        // Check for function call
        if ($this->check(TokenType::LeftParen)) {
            $this->advance();
            $this->skipNewlines();

            $args = [];

            while (!$this->check(TokenType::RightParen) && !$this->isAtEnd()) {
                $this->skipNewlines();

                if ($this->check(TokenType::RightParen)) {
                    break;
                }

                $args[] = $this->parseValue();
                $this->skipNewlines();

                if (!$this->check(TokenType::Comma)) {
                    continue;
                }

                $this->advance();
                $this->skipNewlines();
            }

            $this->consume(TokenType::RightParen, ')');

            return [
                '_type' => 'function',
                'name' => $name,
                'args' => $args,
            ];
        }

        // Check for dotted reference (e.g., self.host, credential.db.host)
        $parts = [$name];

        while ($this->check(TokenType::Dot)) {
            $this->advance();
            $parts[] = $this->consume(TokenType::Identifier, 'identifier')->value;
        }

        if (count($parts) > 1) {
            return [
                '_type' => 'reference',
                'parts' => $parts,
            ];
        }

        // Plain identifier
        return [
            '_type' => 'identifier',
            'value' => $name,
        ];
    }

    /**
     * Skip consecutive newline tokens in the stream.
     *
     * Advances past all newline tokens for flexible whitespace formatting.
     */
    private function skipNewlines(): void
    {
        while ($this->check(TokenType::Newline)) {
            $this->advance();
        }
    }

    /**
     * Check if the current token matches the expected type.
     *
     * Performs lookahead without consuming the token. Returns false if at end.
     *
     * @param TokenType $type Token type to check
     *
     * @return bool True if current token matches the type
     */
    private function check(TokenType $type): bool
    {
        if ($this->isAtEnd()) {
            return false;
        }

        return $this->peek()->type === $type;
    }

    /**
     * Get the current token without advancing the position.
     *
     * Provides lookahead capability for parsing decisions.
     *
     * @return Token Current token at parser position
     */
    private function peek(): Token
    {
        return $this->tokens[$this->position];
    }

    /**
     * Consume the current token and advance to the next position.
     *
     * Increments position counter and returns the consumed token.
     *
     * @return Token Consumed token
     */
    private function advance(): Token
    {
        if (!$this->isAtEnd()) {
            ++$this->position;
        }

        return $this->tokens[$this->position - 1];
    }

    /**
     * Consume a token of the expected type or throw an exception.
     *
     * Verifies the current token matches the expected type before advancing.
     *
     * @param TokenType $type     Expected token type
     * @param string    $expected Human-readable description for error messages
     *
     * @throws ParserException When token doesn't match expected type or at EOF
     *
     * @return Token Consumed token
     */
    private function consume(TokenType $type, string $expected): Token
    {
        if ($this->check($type)) {
            return $this->advance();
        }

        if ($this->isAtEnd()) {
            throw UnexpectedEndOfFileException::whileParsing($expected);
        }

        throw UnexpectedTokenException::at($this->peek(), $expected);
    }

    /**
     * Consume a token that can be used as an attribute key.
     *
     * Accepts identifiers, reserved keywords (like 'if', 'for', 'in'), and
     * quoted strings. String keys allow dotted paths like "cashier.key".
     *
     * @throws ParserException When token cannot be used as a key or at EOF
     *
     * @return Token Consumed token suitable for use as a key
     */
    private function consumeKeyOrIdentifier(): Token
    {
        $token = $this->peek();

        // Accept identifiers, reserved keywords, and quoted strings as keys
        $validKeyTypes = [
            TokenType::Identifier,
            TokenType::String,
            TokenType::If,
            TokenType::For,
            TokenType::In,
        ];

        if (in_array($token->type, $validKeyTypes, true)) {
            return $this->advance();
        }

        if ($this->isAtEnd()) {
            throw UnexpectedEndOfFileException::whileParsing('key or block type');
        }

        throw UnexpectedTokenException::at($token, 'key or block type');
    }

    /**
     * Check if the parser has reached the end of the token stream.
     *
     * @return bool True if current token is EOF
     */
    private function isAtEnd(): bool
    {
        return $this->peek()->type === TokenType::Eof;
    }
}

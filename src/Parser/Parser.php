<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Huckle\Parser;

use Cline\Hcl\Exceptions\ParserException;
use Cline\Hcl\Parser\Token;
use Cline\Hcl\Parser\TokenType;

use function count;
use function in_array;
use function str_contains;

/**
 * Parses HCL tokens into an abstract syntax tree.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class Parser
{
    private const array BLOCK_TYPES = ['defaults', 'group', 'credential', 'export', 'connect', 'division', 'environment', 'provider', 'country', 'service', 'carrier', 'organization', 'team', 'user', 'region', 'tenant', 'client', 'continent', 'state', 'zone'];

    private int $position = 0;

    /**
     * Create a new parser instance.
     *
     * @param array<Token> $tokens The token stream to parse
     */
    public function __construct(
        /** @var array<Token> */
        private readonly array $tokens,
    ) {}

    /**
     * Parse the token stream into an AST.
     *
     * @throws ParserException      If a parsing error occurs
     * @return array<string, mixed> The parsed AST
     */
    public function parse(): array
    {
        $ast = [
            'defaults' => [],
            'groups' => [],
            'divisions' => [],
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

            if ($block['type'] === 'defaults') {
                $ast['defaults'] = $block['body'];
            } elseif ($block['type'] === 'group') {
                $ast['groups'][] = $block;
            } elseif ($block['type'] === 'division') {
                $ast['divisions'][] = $block;
            }

            $this->skipNewlines();
        }

        return $ast;
    }

    /**
     * Parse a block definition.
     *
     * @throws ParserException           If the block is invalid
     * @return null|array<string, mixed> The parsed block or null for comments
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
            throw ParserException::invalidBlockType($type, $typeToken->line, $typeToken->column);
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
     * Parse the body of a block.
     *
     * @throws ParserException      If the body is invalid
     * @return array<string, mixed> The parsed body
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
     * Parse a value expression.
     *
     * @throws ParserException If the value is invalid
     * @return mixed           The parsed value
     */
    private function parseValue(): mixed
    {
        $token = $this->peek();

        return match ($token->type) {
            TokenType::String, TokenType::Interpolation => $this->parseString(),
            TokenType::Number => $this->parseNumber(),
            TokenType::Bool => $this->parseBool(),
            TokenType::Null => $this->parseNull(),
            TokenType::LeftBracket => $this->parseArray(),
            TokenType::LeftBrace => $this->parseObject(),
            TokenType::Identifier => $this->parseFunctionOrReference(),
            default => throw ParserException::unexpectedToken($token, 'value'),
        };
    }

    /**
     * Parse a string value.
     *
     * @return array<string, mixed> The parsed string with metadata
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
     * Parse a number value.
     *
     * @return array<string, mixed> The parsed number with metadata
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
     * Parse a boolean value.
     *
     * @return array<string, mixed> The parsed boolean with metadata
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
     * Parse a null value.
     *
     * @return array<string, mixed> The parsed null with metadata
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
     * Parse an array value.
     *
     * @return array<string, mixed> The parsed array with metadata
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
     * Parse an object/map value.
     *
     * @return array<string, mixed> The parsed object with metadata
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
     * Parse a function call or variable reference.
     *
     * @return array<string, mixed> The parsed function/reference with metadata
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
     * Skip newline tokens.
     */
    private function skipNewlines(): void
    {
        while ($this->check(TokenType::Newline)) {
            $this->advance();
        }
    }

    /**
     * Check if the current token is of the given type.
     *
     * @param  TokenType $type The type to check
     * @return bool      True if the current token matches
     */
    private function check(TokenType $type): bool
    {
        if ($this->isAtEnd()) {
            return false;
        }

        return $this->peek()->type === $type;
    }

    /**
     * Get the current token without advancing.
     *
     * @return Token The current token
     */
    private function peek(): Token
    {
        return $this->tokens[$this->position];
    }

    /**
     * Advance to the next token and return the previous one.
     *
     * @return Token The previous token
     */
    private function advance(): Token
    {
        if (!$this->isAtEnd()) {
            ++$this->position;
        }

        return $this->tokens[$this->position - 1];
    }

    /**
     * Consume a token of the expected type.
     *
     * @param  TokenType       $type     The expected type
     * @param  string          $expected Description of what was expected
     * @throws ParserException If the token doesn't match
     * @return Token           The consumed token
     */
    private function consume(TokenType $type, string $expected): Token
    {
        if ($this->check($type)) {
            return $this->advance();
        }

        if ($this->isAtEnd()) {
            throw ParserException::unexpectedEof($expected);
        }

        throw ParserException::unexpectedToken($this->peek(), $expected);
    }

    /**
     * Consume a token that can be used as a key (identifier or reserved keyword).
     *
     * @throws ParserException If the token is not a valid key
     * @return Token           The consumed token
     */
    private function consumeKeyOrIdentifier(): Token
    {
        $token = $this->peek();

        // Accept identifiers and reserved keywords that can be used as keys
        $validKeyTypes = [
            TokenType::Identifier,
            TokenType::If,
            TokenType::For,
            TokenType::In,
        ];

        if (in_array($token->type, $validKeyTypes, true)) {
            return $this->advance();
        }

        if ($this->isAtEnd()) {
            throw ParserException::unexpectedEof('key or block type');
        }

        throw ParserException::unexpectedToken($token, 'key or block type');
    }

    /**
     * Check if we've reached the end of the token stream.
     *
     * @return bool True if at end
     */
    private function isAtEnd(): bool
    {
        return $this->peek()->type === TokenType::Eof;
    }
}

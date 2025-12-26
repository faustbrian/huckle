<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Huckle\Parser;

use Cline\Hcl\Exceptions\LexerException;
use Cline\Hcl\Exceptions\ParserException;
use Cline\Hcl\Parser\Lexer;
use Cline\Huckle\Exceptions\FileNotFoundException;
use Cline\Huckle\Exceptions\FileNotReadableException;
use Cline\Huckle\Exceptions\FileReadFailedException;
use Cline\Huckle\Exceptions\HuckleException;
use Cline\Huckle\Exceptions\ValidationException;
use Cline\Huckle\Validation\BlockValidator;

use function file_exists;
use function file_get_contents;
use function is_readable;

/**
 * Parses Huckle HCL configuration files into validated configuration objects.
 *
 * Provides the main entry point for parsing HCL content with optional geographic
 * validation for continent, zone, country, and state block labels.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class HuckleParser
{
    /**
     * Whether geographic validation is enabled for continent, zone, country, and state blocks.
     */
    private bool $validateGeo = true;

    /**
     * Enable or disable geographic validation for continent, zone, country, and state blocks.
     *
     * @param bool $enabled Whether to enable geographic validation
     *
     * @return self Fluent interface for method chaining
     */
    public function withGeoValidation(bool $enabled = true): self
    {
        $this->validateGeo = $enabled;

        return $this;
    }

    /**
     * Disable geographic validation for all geo blocks.
     *
     * @return self Fluent interface for method chaining
     */
    public function withoutGeoValidation(): self
    {
        return $this->withGeoValidation(false);
    }

    /**
     * Parse HCL content into a structured configuration object.
     *
     * Tokenizes, parses into AST, validates geographic labels (if enabled), and
     * returns a HuckleConfig wrapper.
     *
     * @param string $content HCL content string to parse
     *
     * @throws LexerException      When tokenization encounters invalid syntax
     * @throws ParserException     When parsing encounters invalid HCL structure
     * @throws ValidationException When geographic validation fails (if enabled)
     *
     * @return HuckleConfig Parsed and validated configuration
     */
    public function parse(string $content): HuckleConfig
    {
        $lexer = new Lexer($content);
        $tokens = $lexer->tokenize();

        $parser = new Parser($tokens);
        $ast = $parser->parse();

        // Run geographic validation if enabled
        if ($this->validateGeo) {
            $validator = new BlockValidator();
            $validator->validate($ast)->throwIfFailed();
        }

        return new HuckleConfig($ast);
    }

    /**
     * Parse an HCL file from the filesystem into a configuration object.
     *
     * Reads and validates file accessibility before parsing.
     *
     * @param string $path Path to the HCL file
     *
     * @throws HuckleException     When the file cannot be accessed or read
     * @throws LexerException      When tokenization encounters invalid syntax
     * @throws ParserException     When parsing encounters invalid HCL structure
     * @throws ValidationException When geographic validation fails (if enabled)
     *
     * @return HuckleConfig Parsed and validated configuration
     */
    public function parseFile(string $path): HuckleConfig
    {
        if (!file_exists($path)) {
            throw FileNotFoundException::forPath($path);
        }

        if (!is_readable($path)) {
            throw FileNotReadableException::atPath($path);
        }

        $content = file_get_contents($path);

        if ($content === false) {
            throw FileReadFailedException::atPath($path);
        }

        return $this->parse($content);
    }

    /**
     * Validate HCL content without building the full configuration object.
     *
     * Performs syntax and structural validation by attempting to parse the content.
     * Returns a result array instead of throwing exceptions.
     *
     * @param string $content HCL content string to validate
     *
     * @return array{valid: bool, errors: array<string>} Validation result with success flag and error messages
     */
    public function validate(string $content): array
    {
        try {
            $this->parse($content);

            return ['valid' => true, 'errors' => []];
        } catch (LexerException|ParserException $e) {
            return [
                'valid' => false,
                'errors' => [$e->getMessage()],
            ];
        } catch (ValidationException $e) {
            return [
                'valid' => false,
                'errors' => $e->messages(),
            ];
        }
    }

    /**
     * Validate an HCL file without building the full configuration object.
     *
     * Performs file accessibility checks and content validation. Returns a result
     * array instead of throwing exceptions.
     *
     * @param string $path Path to the HCL file
     *
     * @return array{valid: bool, errors: array<string>} Validation result with success flag and error messages
     */
    public function validateFile(string $path): array
    {
        try {
            $this->parseFile($path);

            return ['valid' => true, 'errors' => []];
        } catch (ValidationException $e) {
            return [
                'valid' => false,
                'errors' => $e->messages(),
            ];
        } catch (LexerException|ParserException|HuckleException $e) {
            return [
                'valid' => false,
                'errors' => [$e->getMessage()],
            ];
        }
    }
}

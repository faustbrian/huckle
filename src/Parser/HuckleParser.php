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
use Cline\Huckle\Exceptions\ValidationException;
use Cline\Huckle\Validation\BlockValidator;
use RuntimeException;

use function file_exists;
use function file_get_contents;
use function is_readable;
use function throw_if;
use function throw_unless;

/**
 * Main entry point for parsing Huckle HCL files.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class HuckleParser
{
    /**
     * Whether geographic validation is enabled.
     */
    private bool $validateGeo = true;

    /**
     * Enable or disable geographic validation (continent, zone, country, state).
     *
     * @param bool $enabled Whether to enable validation
     */
    public function withGeoValidation(bool $enabled = true): self
    {
        $this->validateGeo = $enabled;

        return $this;
    }

    /**
     * Disable geographic validation.
     */
    public function withoutGeoValidation(): self
    {
        return $this->withGeoValidation(false);
    }

    /**
     * Parse HCL content into a HuckleConfig.
     *
     * @param  string              $content The HCL content to parse
     * @throws LexerException      If tokenization fails
     * @throws ParserException     If parsing fails
     * @throws ValidationException If geographic validation fails
     * @return HuckleConfig        The parsed configuration
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
     * Parse an HCL file into a HuckleConfig.
     *
     * @param  string              $path The path to the HCL file
     * @throws LexerException      If tokenization fails
     * @throws ParserException     If parsing fails
     * @throws RuntimeException    If the file cannot be read
     * @throws ValidationException If geographic validation fails
     * @return HuckleConfig        The parsed configuration
     */
    public function parseFile(string $path): HuckleConfig
    {
        throw_unless(file_exists($path), RuntimeException::class, 'File not found: '.$path);

        throw_unless(is_readable($path), RuntimeException::class, 'File not readable: '.$path);

        $content = file_get_contents($path);

        throw_if($content === false, RuntimeException::class, 'Failed to read file: '.$path);

        return $this->parse($content);
    }

    /**
     * Validate HCL content without building the full config.
     *
     * @param  string               $content The HCL content to validate
     * @return array<string, mixed> Validation result with 'valid' bool and optional 'errors' array
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
     * Validate an HCL file without building the full config.
     *
     * @param  string               $path The path to the HCL file
     * @return array<string, mixed> Validation result
     */
    public function validateFile(string $path): array
    {
        try {
            $this->parseFile($path);

            return ['valid' => true, 'errors' => []];
        } catch (LexerException|ParserException|RuntimeException $e) {
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
}

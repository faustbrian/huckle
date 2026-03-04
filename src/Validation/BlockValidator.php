<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Huckle\Validation;

use Cline\Huckle\Exceptions\ValidationException;

use function array_map;
use function array_pop;
use function implode;
use function in_array;
use function is_array;
use function sprintf;

/**
 * Validates geographic block labels against standard codes and conventions.
 *
 * Recursively validates block labels for continent, zone, country, and state blocks
 * in the parsed AST. Accumulates errors with contextual path information for precise
 * error reporting. Maintains country context during traversal to validate state codes
 * within their proper country scope.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class BlockValidator
{
    /**
     * Block types that require geographic validation against standard codes.
     */
    private const array GEO_VALIDATED_BLOCKS = ['continent', 'zone', 'country', 'state'];

    /**
     * Whether validation is currently enabled.
     */
    private bool $enabled = true;

    /**
     * Accumulated validation errors with context.
     *
     * Each error contains the block type, label, error message, and path context
     * for precise error reporting and troubleshooting.
     *
     * @var array<array{type: string, label: string, message: string, path: string}>
     */
    private array $errors = [];

    /**
     * Current hierarchical path through the AST for error context.
     *
     * Tracks the nested block path (e.g., "division:usa > country:US > state:CA")
     * to provide meaningful error location information.
     *
     * @var array<string>
     */
    private array $currentPath = [];

    /**
     * Current country code context for state validation.
     *
     * Maintained during AST traversal to validate state codes within their
     * proper country context, as state codes are only unique within a country.
     */
    private ?string $currentCountry = null;

    /**
     * Enable or disable validation for geographic blocks.
     *
     * @param  bool $enabled Whether to enable validation checks
     * @return self Fluent interface for method chaining
     */
    public function setEnabled(bool $enabled): self
    {
        $this->enabled = $enabled;

        return $this;
    }

    /**
     * Validate an AST and accumulate errors without throwing.
     *
     * Recursively validates all geographic blocks in the AST, collecting errors
     * for batch reporting. Resets error state before validation. Chain with
     * throwIfFailed() to convert errors into an exception.
     *
     * @param  array<string, mixed> $ast The parsed AST to validate
     * @return self                 Fluent interface for method chaining
     */
    public function validate(array $ast): self
    {
        $this->errors = [];
        $this->currentPath = [];
        $this->currentCountry = null;

        if (!$this->enabled) {
            return $this;
        }

        // Validate partitions (formerly divisions)
        /** @var array<array<string, mixed>> $partitions */
        $partitions = $ast['partitions'] ?? [];

        foreach ($partitions as $partition) {
            $this->validateBlock($partition);
        }

        // Validate groups
        /** @var array<array<string, mixed>> $groups */
        $groups = $ast['groups'] ?? [];

        foreach ($groups as $group) {
            $this->validateBlock($group);
        }

        return $this;
    }

    /**
     * Check if validation passed with no errors.
     *
     * @return bool True if validation found no errors
     */
    public function passes(): bool
    {
        return $this->errors === [];
    }

    /**
     * Check if validation failed with errors.
     *
     * @return bool True if validation found one or more errors
     */
    public function fails(): bool
    {
        return $this->errors !== [];
    }

    /**
     * Get all validation errors with full context.
     *
     * Returns structured error data including block type, invalid label,
     * error message, and hierarchical path for precise error reporting.
     *
     * @return array<array{type: string, label: string, message: string, path: string}> Array of validation errors
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * Get validation errors formatted as human-readable messages.
     *
     * Converts structured error data into strings with path context
     * suitable for display to users or logging.
     *
     * @return array<string> Array of formatted error messages
     */
    public function messages(): array
    {
        return array_map(
            fn (array $error): string => sprintf('[%s] %s', $error['path'], $error['message']),
            $this->errors,
        );
    }

    /**
     * Throw exception if validation encountered errors.
     *
     * Converts accumulated validation errors into a ValidationException
     * for exception-based error handling workflows.
     *
     * @throws ValidationException When validation errors exist
     * @return self                Fluent interface for method chaining
     */
    public function throwIfFailed(): self
    {
        if ($this->fails()) {
            throw ValidationException::fromErrors($this->errors);
        }

        return $this;
    }

    /**
     * Recursively validate a block and all nested blocks.
     *
     * Validates the current block's label if it's a geographic type, then
     * traverses nested blocks to validate the entire hierarchy. Maintains
     * path and country context during traversal for accurate error reporting.
     *
     * @param array<string, mixed> $block The block structure to validate
     */
    private function validateBlock(array $block): void
    {
        /** @var string $type */
        $type = $block['type'];

        /** @var array<int, string> $labels */
        $labels = $block['labels'] ?? [];

        /** @var array<string, mixed> $body */
        $body = $block['body'] ?? [];

        // Build path
        $label = $labels[0] ?? 'unnamed';
        $this->currentPath[] = sprintf('%s:%s', $type, $label);

        // Track country context for state validation
        $previousCountry = $this->currentCountry;

        if ($type === 'country' && isset($labels[0])) {
            $this->currentCountry = $labels[0];
        }

        // Validate this block's label if it's a geo block
        if (in_array($type, self::GEO_VALIDATED_BLOCKS, true) && isset($labels[0])) {
            $this->validateGeoLabel($type, $labels[0]);
        }

        // Recursively validate nested blocks
        foreach ($body as $key => $value) {
            if (!is_array($value)) {
                continue;
            }

            // Check if it's a nested block array
            foreach ($value as $nested) {
                if (!is_array($nested)) {
                    continue;
                }

                if (!isset($nested['labels'], $nested['body'])) {
                    continue;
                }

                /** @var array<int, string> $nestedLabels */
                $nestedLabels = $nested['labels'];

                /** @var array<string, mixed> $nestedBody */
                $nestedBody = $nested['body'];

                $this->validateBlock([
                    'type' => $key,
                    'labels' => $nestedLabels,
                    'body' => $nestedBody,
                ]);
            }
        }

        // Restore country context
        $this->currentCountry = $previousCountry;

        // Pop path
        array_pop($this->currentPath);
    }

    /**
     * Validate a geographic block label against standard codes.
     *
     * Dispatches to the appropriate GeoValidator method based on block type.
     * Accumulates errors with full context when validation fails. Uses current
     * country context for state validation.
     *
     * @param string $type  The block type (continent, zone, country, or state)
     * @param string $label The label value to validate
     */
    private function validateGeoLabel(string $type, string $label): void
    {
        $error = match ($type) {
            'continent' => GeoValidator::validateContinent($label),
            'zone' => GeoValidator::validateZone($label),
            'country' => GeoValidator::validateCountry($label),
            'state' => GeoValidator::validateState($label, $this->currentCountry),
            default => null,
        };

        if ($error === null) {
            return;
        }

        $this->errors[] = [
            'type' => $type,
            'label' => $label,
            'message' => $error,
            'path' => implode(' > ', $this->currentPath),
        ];
    }
}

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
 * Validates block labels for geographic and organizational blocks.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class BlockValidator
{
    /**
     * Block types that require geographic validation.
     */
    private const array GEO_VALIDATED_BLOCKS = ['continent', 'zone', 'country', 'state'];

    /**
     * Whether validation is enabled.
     */
    private bool $enabled = true;

    /**
     * Collected validation errors.
     *
     * @var array<array{type: string, label: string, message: string, path: string}>
     */
    private array $errors = [];

    /**
     * Current block path for error context.
     *
     * @var array<string>
     */
    private array $currentPath = [];

    /**
     * Current country context for state validation.
     */
    private ?string $currentCountry = null;

    /**
     * Enable or disable validation.
     *
     * @param bool $enabled Whether to enable validation
     */
    public function setEnabled(bool $enabled): self
    {
        $this->enabled = $enabled;

        return $this;
    }

    /**
     * Validate an AST and collect errors.
     *
     * @param array<string, mixed> $ast The parsed AST
     */
    public function validate(array $ast): self
    {
        $this->errors = [];
        $this->currentPath = [];
        $this->currentCountry = null;

        if (!$this->enabled) {
            return $this;
        }

        // Validate divisions
        /** @var array<array<string, mixed>> $divisions */
        $divisions = $ast['divisions'] ?? [];

        foreach ($divisions as $division) {
            $this->validateBlock($division);
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
     * Check if validation passed.
     *
     * @return bool True if no errors
     */
    public function passes(): bool
    {
        return $this->errors === [];
    }

    /**
     * Check if validation failed.
     *
     * @return bool True if errors exist
     */
    public function fails(): bool
    {
        return $this->errors !== [];
    }

    /**
     * Get validation errors.
     *
     * @return array<array{type: string, label: string, message: string, path: string}>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * Get formatted error messages.
     *
     * @return array<string>
     */
    public function messages(): array
    {
        return array_map(
            fn (array $error): string => sprintf('[%s] %s', $error['path'], $error['message']),
            $this->errors,
        );
    }

    /**
     * Throw exception if validation failed.
     *
     * @throws ValidationException If validation errors exist
     */
    public function throwIfFailed(): self
    {
        if ($this->fails()) {
            throw ValidationException::fromErrors($this->errors);
        }

        return $this;
    }

    /**
     * Validate a block recursively.
     *
     * @param array<string, mixed> $block The block to validate
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
     * Validate a geographic block label.
     *
     * @param string $type  The block type
     * @param string $label The label to validate
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

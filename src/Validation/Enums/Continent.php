<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Huckle\Validation\Enums;

use function array_column;
use function mb_strtolower;

/**
 * Valid continent identifiers for geographic validation.
 *
 * Provides standardized continent codes used for validating geographic
 * block labels in Huckle configurations. Values use snake_case format
 * for consistency with HCL configuration syntax.
 *
 * @author Brian Faust <brian@cline.sh>
 */
enum Continent: string
{
    case Africa = 'africa';
    case Antarctica = 'antarctica';
    case Asia = 'asia';
    case Europe = 'europe';
    case NorthAmerica = 'north_america';
    case Oceania = 'oceania';
    case SouthAmerica = 'south_america';

    /**
     * Get all valid continent codes as an array.
     *
     * Extracts the string values from all enum cases for validation
     * and error message generation.
     *
     * @return array<string> Array of continent code strings
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Check if a value is a recognized continent code.
     *
     * Performs case-insensitive validation by converting input to lowercase
     * before checking against valid enum cases.
     *
     * @param  string $value The continent code to validate
     * @return bool   True if the value matches a valid continent code
     */
    public static function isValid(string $value): bool
    {
        return self::tryFrom(mb_strtolower($value)) !== null;
    }
}

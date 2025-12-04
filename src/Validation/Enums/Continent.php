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
 * Valid continent codes.
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
     * Get all valid continent codes.
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Check if a value is a valid continent code.
     *
     * @param  string $value The value to check
     * @return bool   True if valid
     */
    public static function isValid(string $value): bool
    {
        return self::tryFrom(mb_strtolower($value)) !== null;
    }
}

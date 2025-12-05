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
 * Valid trading and economic zone identifiers for geographic validation.
 *
 * Provides standardized codes for major trade agreements, economic unions,
 * and regional cooperation organizations. Useful for validating business
 * logic related to trade regulations, compliance zones, and regional markets.
 *
 * @author Brian Faust <brian@cline.sh>
 */
enum Zone: string
{
    // European zones
    case EU = 'eu';                     // European Union (27 members)
    case EEA = 'eea';                   // European Economic Area (EU + Norway, Iceland, Liechtenstein)
    case EFTA = 'efta';                 // European Free Trade Association (Iceland, Liechtenstein, Norway, Switzerland)
    case Schengen = 'schengen';         // Schengen Area (26 countries, free movement)
    case Eurozone = 'eurozone';         // Euro currency zone (20 countries)

    // Americas zones
    case USMCA = 'usmca';               // United States-Mexico-Canada Agreement (formerly NAFTA)
    case Mercosur = 'mercosur';         // Southern Common Market (South America)
    case CARICOM = 'caricom';           // Caribbean Community
    case PacificAlliance = 'pacific_alliance'; // Chile, Colombia, Mexico, Peru

    // Asia-Pacific zones
    case ASEAN = 'asean';               // Association of Southeast Asian Nations
    case RCEP = 'rcep';                 // Regional Comprehensive Economic Partnership
    case CPTPP = 'cptpp';               // Comprehensive and Progressive Agreement for Trans-Pacific Partnership
    case SAARC = 'saarc';               // South Asian Association for Regional Cooperation
    case GCC = 'gcc';                   // Gulf Cooperation Council

    // Africa zones
    case AfCFTA = 'afcfta';             // African Continental Free Trade Area
    case ECOWAS = 'ecowas';             // Economic Community of West African States
    case SADC = 'sadc';                 // Southern African Development Community
    case EAC = 'eac';                   // East African Community
    case COMESA = 'comesa';             // Common Market for Eastern and Southern Africa

    // Eurasia zones
    case CIS = 'cis';                   // Commonwealth of Independent States
    case EAEU = 'eaeu';                 // Eurasian Economic Union

    // Oceania zones
    case ANZCERTA = 'anzcerta';         // Australia-New Zealand Closer Economic Relations Trade Agreement
    case PIF = 'pif';                   // Pacific Islands Forum

    /**
     * Get all valid zone codes as an array.
     *
     * Extracts the string values from all enum cases for validation
     * and error message generation.
     *
     * @return array<int, string> Array of zone code strings
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Check if a value is a recognized zone code.
     *
     * Performs case-insensitive validation by converting input to lowercase
     * before checking against valid enum cases.
     *
     * @param  string $value The zone code to validate
     * @return bool   True if the value matches a valid zone code
     */
    public static function isValid(string $value): bool
    {
        return self::tryFrom(mb_strtolower($value)) !== null;
    }

    /**
     * Get zone codes organized by geographic region.
     *
     * Provides a structured mapping of zones to their primary geographic
     * regions for regional analysis and filtering. Useful for organizing
     * zone-based business logic by continental groupings.
     *
     * @return array<string, array<int, string>> Associative array with region names as keys
     */
    public static function byRegion(): array
    {
        return [
            'europe' => ['eu', 'eea', 'efta', 'schengen', 'eurozone'],
            'americas' => ['usmca', 'mercosur', 'caricom', 'pacific_alliance'],
            'asia_pacific' => ['asean', 'rcep', 'cptpp', 'saarc', 'gcc'],
            'africa' => ['afcfta', 'ecowas', 'sadc', 'eac', 'comesa'],
            'eurasia' => ['cis', 'eaeu'],
            'oceania' => ['anzcerta', 'pif'],
        ];
    }
}

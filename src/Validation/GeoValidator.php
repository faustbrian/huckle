<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Huckle\Validation;

use Cline\Huckle\Validation\Enums\Continent;
use Cline\Huckle\Validation\Enums\Zone;
use Symfony\Component\Intl\Countries;

use function array_keys;
use function implode;
use function mb_strlen;
use function mb_strtoupper;
use function mb_substr;
use function preg_match;
use function sprintf;
use function str_contains;

/**
 * Validates geographic identifiers against international standards.
 *
 * Provides validation for continent codes, economic zones, ISO 3166 country codes,
 * and ISO 3166-2 state/subdivision codes. Returns descriptive error messages
 * when validation fails or null for valid inputs. Leverages Symfony Intl component
 * for country code validation.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class GeoValidator
{
    /**
     * Validate a continent identifier.
     *
     * Checks the label against the Continent enum values. Returns a descriptive
     * error message with valid options if validation fails.
     *
     * @param  string      $label The continent code to validate
     * @return null|string Error message if invalid, null if valid
     */
    public static function validateContinent(string $label): ?string
    {
        if (!Continent::isValid($label)) {
            $valid = implode(', ', Continent::values());

            return sprintf("Invalid continent '%s'. Valid values: %s", $label, $valid);
        }

        return null;
    }

    /**
     * Validate an economic or trading zone identifier.
     *
     * Checks the label against the Zone enum values. Returns a descriptive
     * error message with valid options if validation fails.
     *
     * @param  string      $label The zone code to validate
     * @return null|string Error message if invalid, null if valid
     */
    public static function validateZone(string $label): ?string
    {
        if (!Zone::isValid($label)) {
            $valid = implode(', ', Zone::values());

            return sprintf("Invalid zone '%s'. Valid values: %s", $label, $valid);
        }

        return null;
    }

    /**
     * Validate a country code against ISO 3166-1 standard.
     *
     * Accepts both alpha-2 (e.g., "FI", "US") and alpha-3 (e.g., "FIN", "USA")
     * formats. Uses Symfony Intl component for validation. Case-insensitive.
     *
     * @param  string      $label The country code to validate (2 or 3 letters)
     * @return null|string Error message if invalid, null if valid
     */
    public static function validateCountry(string $label): ?string
    {
        $upper = mb_strtoupper($label);
        $length = mb_strlen($upper);

        // Check alpha-2 (e.g., FI, SE, EE)
        if ($length === 2) {
            if (Countries::exists($upper)) {
                return null;
            }

            return sprintf("Invalid country code '%s'. Expected ISO 3166-1 alpha-2 code (e.g., FI, SE, US)", $label);
        }

        // Check alpha-3 (e.g., FIN, SWE, EST)
        if ($length === 3) {
            if (Countries::alpha3CodeExists($upper)) {
                return null;
            }

            return sprintf("Invalid country code '%s'. Expected ISO 3166-1 alpha-3 code (e.g., FIN, SWE, USA)", $label);
        }

        return sprintf("Invalid country code '%s'. Expected 2-letter (alpha-2) or 3-letter (alpha-3) ISO code", $label);
    }

    /**
     * Validate a state or subdivision code against ISO 3166-2 standard.
     *
     * Accepts two formats:
     * 1. Full ISO 3166-2 format: "XX-YYY" (e.g., "US-CA", "DE-BY")
     * 2. Short subdivision code: "YYY" (requires country context parameter)
     *
     * When using short format, the country code context must be provided
     * (typically from a parent country block in the AST).
     *
     * @param  string      $label       The state/subdivision code to validate
     * @param  null|string $countryCode Parent country code context for short format validation
     * @return null|string Error message if invalid, null if valid
     */
    public static function validateState(string $label, ?string $countryCode = null): ?string
    {
        $upper = mb_strtoupper($label);

        // If label contains hyphen, it might be full ISO 3166-2 code
        if (str_contains($upper, '-')) {
            // Full ISO 3166-2 format: US-CA, DE-BY, etc.
            if (!preg_match('/^[A-Z]{2}-[A-Z0-9]{1,3}$/', $upper)) {
                return sprintf("Invalid state code format '%s'. Expected ISO 3166-2 format (e.g., US-CA, DE-BY)", $label);
            }

            // Extract country part and validate it exists
            $countryPart = mb_substr($upper, 0, 2);

            if (!Countries::exists($countryPart)) {
                return sprintf("Invalid country prefix in state code '%s'. '%s' is not a valid country", $label, $countryPart);
            }

            // We trust the subdivision part if country is valid
            // (Symfony Intl doesn't expose subdivisions list)
            return null;
        }

        // Short form (e.g., "CA" for California) - requires country context
        if ($countryCode === null) {
            return sprintf("State code '%s' requires country context. Either nest inside a country block or use full ISO 3166-2 format (e.g., US-%s)", $label, $label);
        }

        // Validate the parent country
        $countryUpper = mb_strtoupper($countryCode);

        // Convert alpha-3 to alpha-2 if needed
        if (mb_strlen($countryUpper) === 3) {
            if (!Countries::alpha3CodeExists($countryUpper)) {
                return sprintf("Invalid parent country code '%s'", $countryCode);
            }

            $countryUpper = Countries::getAlpha2Code($countryUpper);
        }

        if (!Countries::exists($countryUpper)) {
            return sprintf("Invalid parent country code '%s'", $countryCode);
        }

        // Basic format check for subdivision part
        if (!preg_match('/^[A-Z0-9]{1,3}$/', $upper)) {
            return sprintf("Invalid state code format '%s'. Expected 1-3 alphanumeric characters", $label);
        }

        return null;
    }

    /**
     * Normalize a country code to ISO 3166-1 alpha-2 format.
     *
     * Converts alpha-3 codes to alpha-2 or validates existing alpha-2 codes.
     * Returns null for invalid country codes. Case-insensitive.
     *
     * @param  string      $code The country code to normalize (2 or 3 letters)
     * @return null|string The normalized alpha-2 code, or null if invalid
     */
    public static function normalizeCountryCode(string $code): ?string
    {
        $upper = mb_strtoupper($code);

        if (mb_strlen($upper) === 2 && Countries::exists($upper)) {
            return $upper;
        }

        if (mb_strlen($upper) === 3 && Countries::alpha3CodeExists($upper)) {
            return Countries::getAlpha2Code($upper);
        }

        return null;
    }

    /**
     * Get all valid ISO 3166-1 alpha-2 country codes.
     *
     * Returns the complete list of recognized country codes from the
     * Symfony Intl component. Useful for generating dropdown lists or
     * validation documentation.
     *
     * @return array<int, string> Array of alpha-2 country codes
     */
    public static function getCountryCodes(): array
    {
        return array_keys(Countries::getNames());
    }

    /**
     * Get the country name from an ISO 3166-1 country code.
     *
     * Accepts both alpha-2 and alpha-3 formats, normalizing to alpha-2
     * before lookup. Returns null for invalid country codes.
     *
     * @param  string      $code The country code to look up (2 or 3 letters)
     * @return null|string The localized country name, or null if invalid
     */
    public static function getCountryName(string $code): ?string
    {
        $alpha2 = self::normalizeCountryCode($code);

        if ($alpha2 === null) {
            return null;
        }

        return Countries::getName($alpha2);
    }
}

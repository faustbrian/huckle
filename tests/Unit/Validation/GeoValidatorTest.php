<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Huckle\Validation\GeoValidator;

describe('GeoValidator', function (): void {
    describe('validateContinent()', function (): void {
        test('accepts valid continent codes', function (): void {
            expect(GeoValidator::validateContinent('europe'))->toBeNull();
            expect(GeoValidator::validateContinent('asia'))->toBeNull();
            expect(GeoValidator::validateContinent('africa'))->toBeNull();
            expect(GeoValidator::validateContinent('north_america'))->toBeNull();
            expect(GeoValidator::validateContinent('south_america'))->toBeNull();
            expect(GeoValidator::validateContinent('oceania'))->toBeNull();
            expect(GeoValidator::validateContinent('antarctica'))->toBeNull();
        });

        test('accepts case-insensitive continent codes', function (): void {
            expect(GeoValidator::validateContinent('Europe'))->toBeNull();
            expect(GeoValidator::validateContinent('ASIA'))->toBeNull();
        });

        test('rejects invalid continent codes', function (): void {
            $error = GeoValidator::validateContinent('atlantis');
            expect($error)->toContain("Invalid continent 'atlantis'");
            expect($error)->toContain('Valid values:');
        });
    });

    describe('validateZone()', function (): void {
        test('accepts valid European zone codes', function (): void {
            expect(GeoValidator::validateZone('eu'))->toBeNull();
            expect(GeoValidator::validateZone('eea'))->toBeNull();
            expect(GeoValidator::validateZone('efta'))->toBeNull();
            expect(GeoValidator::validateZone('schengen'))->toBeNull();
            expect(GeoValidator::validateZone('eurozone'))->toBeNull();
        });

        test('accepts valid Americas zone codes', function (): void {
            expect(GeoValidator::validateZone('usmca'))->toBeNull();
            expect(GeoValidator::validateZone('mercosur'))->toBeNull();
            expect(GeoValidator::validateZone('caricom'))->toBeNull();
        });

        test('accepts valid Asia-Pacific zone codes', function (): void {
            expect(GeoValidator::validateZone('asean'))->toBeNull();
            expect(GeoValidator::validateZone('rcep'))->toBeNull();
            expect(GeoValidator::validateZone('cptpp'))->toBeNull();
            expect(GeoValidator::validateZone('gcc'))->toBeNull();
        });

        test('accepts valid African zone codes', function (): void {
            expect(GeoValidator::validateZone('afcfta'))->toBeNull();
            expect(GeoValidator::validateZone('ecowas'))->toBeNull();
            expect(GeoValidator::validateZone('sadc'))->toBeNull();
        });

        test('accepts case-insensitive zone codes', function (): void {
            expect(GeoValidator::validateZone('EU'))->toBeNull();
            expect(GeoValidator::validateZone('Eea'))->toBeNull();
        });

        test('rejects invalid zone codes', function (): void {
            $error = GeoValidator::validateZone('invalid_zone');
            expect($error)->toContain("Invalid zone 'invalid_zone'");
            expect($error)->toContain('Valid values:');
        });
    });

    describe('validateCountry()', function (): void {
        test('accepts valid ISO 3166-1 alpha-2 codes', function (): void {
            expect(GeoValidator::validateCountry('FI'))->toBeNull();
            expect(GeoValidator::validateCountry('SE'))->toBeNull();
            expect(GeoValidator::validateCountry('EE'))->toBeNull();
            expect(GeoValidator::validateCountry('US'))->toBeNull();
            expect(GeoValidator::validateCountry('DE'))->toBeNull();
            expect(GeoValidator::validateCountry('LV'))->toBeNull();
            expect(GeoValidator::validateCountry('LT'))->toBeNull();
        });

        test('accepts valid ISO 3166-1 alpha-3 codes', function (): void {
            expect(GeoValidator::validateCountry('FIN'))->toBeNull();
            expect(GeoValidator::validateCountry('SWE'))->toBeNull();
            expect(GeoValidator::validateCountry('EST'))->toBeNull();
            expect(GeoValidator::validateCountry('USA'))->toBeNull();
            expect(GeoValidator::validateCountry('DEU'))->toBeNull();
        });

        test('accepts case-insensitive country codes', function (): void {
            expect(GeoValidator::validateCountry('fi'))->toBeNull();
            expect(GeoValidator::validateCountry('Fin'))->toBeNull();
        });

        test('rejects invalid alpha-2 codes', function (): void {
            $error = GeoValidator::validateCountry('XX');
            expect($error)->toContain("Invalid country code 'XX'");
            expect($error)->toContain('alpha-2');
        });

        test('rejects invalid alpha-3 codes', function (): void {
            $error = GeoValidator::validateCountry('XXX');
            expect($error)->toContain("Invalid country code 'XXX'");
            expect($error)->toContain('alpha-3');
        });

        test('rejects codes with wrong length', function (): void {
            $error = GeoValidator::validateCountry('XXXX');
            expect($error)->toContain("Invalid country code 'XXXX'");
            expect($error)->toContain('2-letter');
            expect($error)->toContain('3-letter');
        });
    });

    describe('validateState()', function (): void {
        test('accepts full ISO 3166-2 codes', function (): void {
            expect(GeoValidator::validateState('US-CA'))->toBeNull();
            expect(GeoValidator::validateState('US-NY'))->toBeNull();
            expect(GeoValidator::validateState('DE-BY'))->toBeNull();
            expect(GeoValidator::validateState('DE-BE'))->toBeNull();
        });

        test('accepts case-insensitive full codes', function (): void {
            expect(GeoValidator::validateState('us-ca'))->toBeNull();
            expect(GeoValidator::validateState('de-by'))->toBeNull();
        });

        test('rejects full codes with invalid country prefix', function (): void {
            $error = GeoValidator::validateState('XX-CA');
            expect($error)->toContain('Invalid country prefix');
            expect($error)->toContain("'XX' is not a valid country");
        });

        test('accepts short codes with country context', function (): void {
            expect(GeoValidator::validateState('CA', 'US'))->toBeNull();
            expect(GeoValidator::validateState('BY', 'DE'))->toBeNull();
        });

        test('accepts short codes with alpha-3 country context', function (): void {
            expect(GeoValidator::validateState('CA', 'USA'))->toBeNull();
            expect(GeoValidator::validateState('BY', 'DEU'))->toBeNull();
        });

        test('requires country context for short codes', function (): void {
            $error = GeoValidator::validateState('CA');
            expect($error)->toContain('requires country context');
            expect($error)->toContain('US-CA');
        });

        test('rejects invalid country context', function (): void {
            $error = GeoValidator::validateState('CA', 'XX');
            expect($error)->toContain("Invalid parent country code 'XX'");
        });
    });

    describe('normalizeCountryCode()', function (): void {
        test('normalizes alpha-2 to uppercase', function (): void {
            expect(GeoValidator::normalizeCountryCode('fi'))->toBe('FI');
            expect(GeoValidator::normalizeCountryCode('FI'))->toBe('FI');
        });

        test('converts alpha-3 to alpha-2', function (): void {
            expect(GeoValidator::normalizeCountryCode('FIN'))->toBe('FI');
            expect(GeoValidator::normalizeCountryCode('USA'))->toBe('US');
            expect(GeoValidator::normalizeCountryCode('DEU'))->toBe('DE');
        });

        test('returns null for invalid codes', function (): void {
            expect(GeoValidator::normalizeCountryCode('XX'))->toBeNull();
            expect(GeoValidator::normalizeCountryCode('XXX'))->toBeNull();
        });
    });

    describe('getCountryName()', function (): void {
        test('returns country name from alpha-2 code', function (): void {
            expect(GeoValidator::getCountryName('FI'))->toBe('Finland');
            expect(GeoValidator::getCountryName('US'))->toBe('United States');
            expect(GeoValidator::getCountryName('DE'))->toBe('Germany');
        });

        test('returns country name from alpha-3 code', function (): void {
            expect(GeoValidator::getCountryName('FIN'))->toBe('Finland');
            expect(GeoValidator::getCountryName('USA'))->toBe('United States');
        });

        test('returns null for invalid codes', function (): void {
            expect(GeoValidator::getCountryName('XX'))->toBeNull();
        });

        test('returns null for invalid alpha-3 codes', function (): void {
            expect(GeoValidator::getCountryName('XXX'))->toBeNull();
        });
    });
});

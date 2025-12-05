<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Huckle\Validation\Enums\Zone;

describe('Zone Enum', function (): void {
    describe('values()', function (): void {
        test('returns all zone codes', function (): void {
            $values = Zone::values();

            expect($values)->toBeArray();
            expect($values)->toContain('eu');
            expect($values)->toContain('eea');
            expect($values)->toContain('efta');
            expect($values)->toContain('schengen');
            expect($values)->toContain('eurozone');
            expect($values)->toContain('usmca');
            expect($values)->toContain('asean');
            expect($values)->toContain('afcfta');
        });
    });

    describe('isValid()', function (): void {
        test('returns true for valid zone codes', function (): void {
            expect(Zone::isValid('eu'))->toBeTrue();
            expect(Zone::isValid('EU'))->toBeTrue();
            expect(Zone::isValid('Eu'))->toBeTrue();
        });

        test('returns false for invalid zone codes', function (): void {
            expect(Zone::isValid('invalid'))->toBeFalse();
            expect(Zone::isValid('xyz'))->toBeFalse();
        });
    });

    describe('byRegion()', function (): void {
        test('returns zones grouped by region', function (): void {
            $byRegion = Zone::byRegion();

            expect($byRegion)->toBeArray();
            expect($byRegion)->toHaveKey('europe');
            expect($byRegion)->toHaveKey('americas');
            expect($byRegion)->toHaveKey('asia_pacific');
            expect($byRegion)->toHaveKey('africa');
            expect($byRegion)->toHaveKey('eurasia');
            expect($byRegion)->toHaveKey('oceania');

            expect($byRegion['europe'])->toContain('eu');
            expect($byRegion['americas'])->toContain('usmca');
            expect($byRegion['asia_pacific'])->toContain('asean');
            expect($byRegion['africa'])->toContain('afcfta');
            expect($byRegion['eurasia'])->toContain('eaeu');
            expect($byRegion['oceania'])->toContain('pif');
        });
    });
});

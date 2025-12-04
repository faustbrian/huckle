<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Illuminate\Support\Facades\Config;

test('dump expiring command output', function (): void {
    Config::set('huckle.path', testFixture('expiring.hcl'));
    Config::set('huckle.expiry_warning', 30);
    Config::set('huckle.rotation_warning', 90);

    $result = $this->artisan('huckle:expiring');

    dump($result);

    expect(true)->toBeTrue();
});

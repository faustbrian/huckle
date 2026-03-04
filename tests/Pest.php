<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Tests\TestCase;

pest()->extend(TestCase::class)->in(__DIR__);

/**
 * Get the path to a test fixture file.
 *
 * @param  string $name The fixture name
 * @return string The full path
 */
function testFixture(string $name): string
{
    return __DIR__.'/Fixtures/'.$name;
}

/**
 * Get the contents of a test fixture file.
 *
 * @param  string $name The fixture name
 * @return string The file contents
 */
function testFixtureContent(string $name): string
{
    return file_get_contents(testFixture($name));
}

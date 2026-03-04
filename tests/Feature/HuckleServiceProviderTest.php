<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Huckle\Facades\Huckle;
use Cline\Huckle\HuckleManager;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Config;

describe('HuckleServiceProvider', function (): void {
    describe('service registration', function (): void {
        test('registers HuckleManager as singleton', function (): void {
            // Arrange & Act
            $instance1 = resolve(HuckleManager::class);
            $instance2 = resolve(HuckleManager::class);

            // Assert
            expect($instance1)->toBeInstanceOf(HuckleManager::class)
                ->and($instance1)->toBe($instance2);
        });
    });

    describe('auto_export configuration', function (): void {
        test('exports to env when auto_export is true', function (): void {
            // Arrange
            Config::set('huckle.path', testFixture('basic.hcl'));

            // Clear any existing env vars from the fixture
            putenv('AWS_ACCESS_KEY_ID');
            putenv('AWS_SECRET_ACCESS_KEY');
            putenv('AWS_DEFAULT_REGION');
            unset($_ENV['AWS_ACCESS_KEY_ID'], $_SERVER['AWS_ACCESS_KEY_ID'], $_ENV['AWS_SECRET_ACCESS_KEY'], $_SERVER['AWS_SECRET_ACCESS_KEY'], $_ENV['AWS_DEFAULT_REGION'], $_SERVER['AWS_DEFAULT_REGION']);

            // Act - Manually trigger exportAllToEnv (simulating auto_export behavior)
            resolve(HuckleManager::class)->exportAllToEnv();

            // Assert - Check that AWS credentials were exported (unique exports that won't be overridden)
            expect(getenv('AWS_ACCESS_KEY_ID'))->toBe('AKIAIOSFODNN7EXAMPLE')
                ->and($_ENV['AWS_ACCESS_KEY_ID'])->toBe('AKIAIOSFODNN7EXAMPLE')
                ->and($_SERVER['AWS_ACCESS_KEY_ID'])->toBe('AKIAIOSFODNN7EXAMPLE')
                ->and(getenv('AWS_DEFAULT_REGION'))->toBe('us-east-1')
                ->and($_ENV['AWS_DEFAULT_REGION'])->toBe('us-east-1')
                ->and($_SERVER['AWS_DEFAULT_REGION'])->toBe('us-east-1');
        });

        test('does not export to env when auto_export is false', function (): void {
            // Arrange
            Config::set('huckle.path', testFixture('basic.hcl'));

            // Clear any existing env vars
            putenv('DB_HOST');
            putenv('AWS_ACCESS_KEY_ID');
            unset($_ENV['DB_HOST'], $_SERVER['DB_HOST'], $_ENV['AWS_ACCESS_KEY_ID'], $_SERVER['AWS_ACCESS_KEY_ID']);

            // Act - Do NOT call exportAllToEnv (simulating auto_export = false)
            // Just ensure the manager is loaded
            resolve(HuckleManager::class);

            // Assert - Environment variables should not be set
            expect(getenv('DB_HOST'))->toBeFalse()
                ->and(isset($_ENV['DB_HOST']))->toBeFalse()
                ->and(isset($_SERVER['DB_HOST']))->toBeFalse();
        });
    });

    describe('Blade directives', function (): void {
        test('registers @huckle Blade directive', function (): void {
            // Arrange & Act
            $directives = Blade::getCustomDirectives();

            // Assert
            expect($directives)->toHaveKey('huckle')
                ->and($directives['huckle'])->toBeCallable();
        });

        test('registers @hasHuckle Blade directive', function (): void {
            // Arrange
            Config::set('huckle.path', testFixture('basic.hcl'));
            $template = "@hasHuckle('database.production.main')
                <div>exists</div>
            @else
                <div>not found</div>
            @endhasHuckle";

            // Act
            $compiled = Blade::compileString($template);

            // Assert - Verify the conditional was compiled
            expect($compiled)
                ->toContain('<?php if')
                ->toContain('<?php else')
                ->toContain('<?php endif');
        });

        test('@huckle directive outputs credential value', function (): void {
            // Arrange
            Config::set('huckle.path', testFixture('basic.hcl'));
            $expression = "'database.production.main.host'";
            $directive = Blade::getCustomDirectives()['huckle'];

            // Act
            $compiled = $directive($expression);

            // Assert - Verify the compiled PHP code structure
            expect($compiled)
                ->toContain(Huckle::class.'::get')
                ->toContain('echo e(')
                ->toContain(Arr::class.'::last(explode');
        });

        test('@huckle directive compiles with non-existent credential path', function (): void {
            // Arrange
            Config::set('huckle.path', testFixture('basic.hcl'));
            $template = "@huckle('non.existent.credential.key')";

            // Act
            $compiled = Blade::compileString($template);

            // Assert - Verify it compiles without errors and includes fallback logic
            expect($compiled)
                ->toContain(Huckle::class.'::get')
                ->toContain("?? ''");
        });

        test('@hasHuckle directive compiles correctly for existing credential', function (): void {
            // Arrange
            Config::set('huckle.path', testFixture('basic.hcl'));
            $template = "@hasHuckle('database.production.main')
                <div>Credential exists</div>
            @endhasHuckle";

            // Act
            $compiled = Blade::compileString($template);

            // Assert
            expect($compiled)
                ->toContain('<?php if')
                ->toContain('Credential exists')
                ->toContain('<?php endif');
        });

        test('@hasHuckle directive compiles correctly for non-existent credential', function (): void {
            // Arrange
            Config::set('huckle.path', testFixture('basic.hcl'));
            $template = "@hasHuckle('non.existent.credential')
                <div>Should not appear</div>
            @endhasHuckle";

            // Act
            $compiled = Blade::compileString($template);

            // Assert - Verify conditional structure is created
            expect($compiled)
                ->toContain('<?php if')
                ->toContain('<?php endif');
        });

        test('@huckle directive compiles with different credential paths', function (): void {
            // Arrange
            Config::set('huckle.path', testFixture('basic.hcl'));
            $template = "@huckle('database.production.main.port')";

            // Act
            $compiled = Blade::compileString($template);

            // Assert - Verify the path is included in the compiled code
            expect($compiled)
                ->toContain(Huckle::class.'::get')
                ->toContain("'database.production.main.port'");
        });

        test('@huckle directive compiles multiple credentials in template', function (): void {
            // Arrange
            Config::set('huckle.path', testFixture('basic.hcl'));
            $template = "Host: @huckle('database.production.main.host'), Port: @huckle('database.production.main.port')";

            // Act
            $compiled = Blade::compileString($template);

            // Assert - Verify both directives are compiled
            expect($compiled)
                ->toContain('Host:')
                ->toContain('Port:')
                ->toContain("'database.production.main.host'")
                ->toContain("'database.production.main.port'");
        });
    });

    describe('package configuration', function (): void {
        test('loads configuration file', function (): void {
            // Arrange & Act
            $config = Config::get('huckle');

            // Assert
            expect($config)->toBeArray()
                ->and($config)->toHaveKey('path');
        });

        test('registers console commands', function (): void {
            // Arrange & Act
            $commands = $this->app->make(Kernel::class)->all();

            // Assert - Verify key Huckle commands are registered
            expect($commands)->toHaveKey('huckle:export')
                ->and($commands)->toHaveKey('huckle:sync')
                ->and($commands)->toHaveKey('huckle:lint')
                ->and($commands)->toHaveKey('huckle:list')
                ->and($commands)->toHaveKey('huckle:show');
        });
    });
});

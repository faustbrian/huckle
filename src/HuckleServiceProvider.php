<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Huckle;

use Cline\Huckle\Console\Commands\ConfigDecryptCommand;
use Cline\Huckle\Console\Commands\ConfigEncryptCommand;
use Cline\Huckle\Console\Commands\ConnectCommand;
use Cline\Huckle\Console\Commands\DiffCommand;
use Cline\Huckle\Console\Commands\ExpiringCommand;
use Cline\Huckle\Console\Commands\ExportCommand;
use Cline\Huckle\Console\Commands\Hcl2JsonCommand;
use Cline\Huckle\Console\Commands\Json2HclCommand;
use Cline\Huckle\Console\Commands\LintCommand;
use Cline\Huckle\Console\Commands\ListCommand;
use Cline\Huckle\Console\Commands\ShowCommand;
use Cline\Huckle\Console\Commands\SyncCommand;
use Cline\Huckle\Facades\Huckle;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Config;
use Override;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

use function sprintf;

/**
 * Service provider for the Huckle configuration management package.
 *
 * Registers the HuckleManager singleton, publishes configuration, registers
 * Artisan commands, sets up Blade directives for config access, and
 * optionally auto-exports configuration to environment variables on boot.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class HuckleServiceProvider extends PackageServiceProvider
{
    /**
     * Configure the package settings.
     *
     * Defines package name, configuration file, and registers all Artisan
     * commands for configuration management operations.
     *
     * @param Package $package Package configuration builder instance
     */
    public function configurePackage(Package $package): void
    {
        $package
            ->name('huckle')
            ->hasConfigFile()
            ->hasCommands([
                ConfigEncryptCommand::class,
                ConfigDecryptCommand::class,
                ExportCommand::class,
                SyncCommand::class,
                LintCommand::class,
                ConnectCommand::class,
                ListCommand::class,
                ShowCommand::class,
                DiffCommand::class,
                ExpiringCommand::class,
                Hcl2JsonCommand::class,
                Json2HclCommand::class,
            ]);
    }

    /**
     * Bootstrap the package's services.
     *
     * Registers Blade directives and optionally auto-exports all configuration
     * to environment variables when huckle.auto_export is enabled.
     */
    #[Override()]
    public function bootingPackage(): void
    {
        $this->registerBladeDirectives();

        // Auto-export configuration to environment variables if enabled in config
        if (!Config::get('huckle.auto_export', false)) {
            return;
        }

        $this->app->make(HuckleManager::class)->exportAllToEnv();
    }

    /**
     * Register Blade directives for config access in views.
     *
     * Directives:
     * - @huckle('path.to.node.field'): Output field value with auto-escaping
     * - @hasHuckle('path.to.node') / @endhasHuckle: Conditional block for node existence
     */
    private function registerBladeDirectives(): void
    {
        // @huckle('database.production.main.host') - safely output a config field value
        Blade::directive('huckle', fn (string $expression): string => sprintf('<?php echo e('.Huckle::class.'::get(%s)?->get('.Arr::class."::last(explode('.', %s))) ?? ''); ?>", $expression, $expression));

        // @hasHuckle('database.production.main') ... @endhasHuckle - conditional node check
        Blade::if('hasHuckle', fn (string $path): bool => $this->app->make(HuckleManager::class)->has($path));
    }
}

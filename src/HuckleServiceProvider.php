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
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Config;
use Override;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

use function sprintf;

/**
 * Service provider for the Huckle credential management package.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class HuckleServiceProvider extends PackageServiceProvider
{
    /**
     * Configure the package settings.
     *
     * @param Package $package The package configuration instance
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
     * Register the package's services in the container.
     */
    #[Override()]
    public function registeringPackage(): void
    {
        $this->app->singleton(HuckleManager::class, fn (Application $app): HuckleManager => new HuckleManager($app));
    }

    /**
     * Bootstrap the package's services.
     */
    #[Override()]
    public function bootingPackage(): void
    {
        $this->registerBladeDirectives();

        // Auto-export to env if configured
        if (!Config::get('huckle.auto_export', false)) {
            return;
        }

        $this->app->make(HuckleManager::class)->exportAllToEnv();
    }

    /**
     * Register Blade directives for credential access in views.
     */
    private function registerBladeDirectives(): void
    {
        // @huckle('database.production.main.host') - output a credential value
        Blade::directive('huckle', fn (string $expression): string => sprintf('<?php echo e('.Huckle::class.'::get(%s)?->get('.Arr::class."::last(explode('.', %s))) ?? ''); ?>", $expression, $expression));

        // @hasHuckle('database.production.main') ... @endhasHuckle
        Blade::if('hasHuckle', fn (string $path): bool => $this->app->make(HuckleManager::class)->has($path));
    }
}

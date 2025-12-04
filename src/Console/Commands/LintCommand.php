<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Huckle\Console\Commands;

use Cline\Huckle\HuckleManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;

use function clearstatcache;
use function count;
use function fileperms;
use function mb_substr;
use function sprintf;

/**
 * Lint and validate Huckle configuration.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class LintCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'huckle:lint
        {--check-expiry : Warn about expiring credentials}
        {--check-rotation : Warn about credentials needing rotation}
        {--check-permissions : Warn about file permissions}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Validate Huckle configuration syntax and rules';

    /**
     * Execute the console command.
     *
     * @param  HuckleManager $huckle The Huckle manager instance
     * @return int           The exit code
     */
    public function handle(HuckleManager $huckle): int
    {
        $path = $huckle->getConfigPath();
        $errors = [];
        $warnings = [];

        $this->info('Linting: '.$path);
        $this->newLine();

        // Validate syntax
        $validation = $huckle->validate($path);

        if (!$validation['valid']) {
            /** @var array<string> $validationErrors */
            $validationErrors = $validation['errors'];

            foreach ($validationErrors as $error) {
                $errors[] = $error;
            }
        }

        if ($errors !== []) {
            $this->error('Syntax errors found:');

            foreach ($errors as $error) {
                $this->line('  - '.$error);
            }

            return self::FAILURE;
        }

        $this->info('✓ Syntax valid');

        // Load config for additional checks
        $config = $huckle->load($path);
        $credentials = $config->credentials();

        $this->info(sprintf('✓ Loaded %d credentials in %d groups', $credentials->count(), $config->groups()->count()));

        // Check expiry
        if ($this->option('check-expiry')) {
            /** @var int $expiryDays */
            $expiryDays = Config::get('huckle.expiry_warning', 30);
            $expiring = $config->expiring($expiryDays);
            $expired = $config->expired();

            if ($expired->isNotEmpty()) {
                $errors[] = sprintf('Found %d expired credential(s)', $expired->count());

                foreach ($expired as $cred) {
                    $this->error(sprintf('  ✗ EXPIRED: %s (expired: %s)', $cred->path(), $cred->expires));
                }
            }

            if ($expiring->isNotEmpty()) {
                $warnings[] = sprintf('Found %d credential(s) expiring within %d days', $expiring->count(), $expiryDays);

                foreach ($expiring as $cred) {
                    $this->warn(sprintf('  ! Expiring: %s (expires: %s)', $cred->path(), $cred->expires));
                }
            }

            if ($expired->isEmpty() && $expiring->isEmpty()) {
                $this->info('✓ No credentials expiring soon');
            }
        }

        // Check rotation
        if ($this->option('check-rotation')) {
            /** @var int $rotationDays */
            $rotationDays = Config::get('huckle.rotation_warning', 90);
            $needsRotation = $config->needsRotation($rotationDays);

            if ($needsRotation->isNotEmpty()) {
                $warnings[] = sprintf('Found %d credential(s) needing rotation', $needsRotation->count());

                foreach ($needsRotation as $cred) {
                    $rotated = $cred->rotated ?? 'never';
                    $this->warn(sprintf('  ! Needs rotation: %s (last: %s)', $cred->path(), $rotated));
                }
            } else {
                $this->info('✓ All credentials recently rotated');
            }
        }

        // Check file permissions
        if ($this->option('check-permissions')) {
            clearstatcache();
            $perms = fileperms($path);

            if ($perms !== false) {
                $octal = mb_substr(sprintf('%o', $perms), -4);

                // Check if world-readable (others have read permission)
                if (($perms & 0x00_04) !== 0) {
                    $warnings[] = sprintf('Configuration file is world-readable (%s). Consider chmod 600.', $octal);
                    $this->warn(sprintf('  ! File permissions: %s (world-readable)', $octal));
                } else {
                    $this->info('✓ File permissions: '.$octal);
                }
            }
        }

        // Summary
        $this->newLine();

        if ($errors !== []) {
            $this->error('Lint failed with '.count($errors).' error(s)');

            return self::FAILURE;
        }

        if ($warnings !== []) {
            $this->warn('Lint passed with '.count($warnings).' warning(s)');

            return self::SUCCESS;
        }

        $this->info('Lint passed with no issues');

        return self::SUCCESS;
    }
}

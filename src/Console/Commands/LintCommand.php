<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Huckle\Console\Commands;

use Cline\Huckle\HuckleManager;
use Cline\Huckle\Parser\Node;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;

use function clearstatcache;
use function count;
use function fileperms;
use function mb_substr;
use function sprintf;

/**
 * Artisan command to lint and validate Huckle configuration.
 *
 * Provides CLI interface for validating HCL configuration syntax and checking
 * nodes for security issues. Validates syntax, checks for expiring or
 * expired nodes, identifies nodes needing rotation, and verifies
 * file permissions. Helps maintain configuration quality and security compliance.
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
        {--check-expiry : Warn about expiring nodes}
        {--check-rotation : Warn about nodes needing rotation}
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
     * Validates the HCL configuration file and performs optional checks for
     * node expiration, rotation, and file permissions. Returns FAILURE
     * if syntax errors or expired nodes are found, SUCCESS otherwise.
     *
     * @param HuckleManager $huckle The Huckle manager instance
     *
     * @return int FAILURE if errors found, SUCCESS if validation passes
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
        // Filter to only count leaf nodes (not partition/environment containers)
        $nodes = $config->nodes()->filter(
            fn (Node $n): bool => !\in_array($n->type, ['partition', 'environment'], true),
        );
        $partitions = $config->partitions();

        $this->info(sprintf('✓ Loaded %d nodes in %d partitions', $nodes->count(), $partitions->count()));

        // Check expiry
        if ($this->option('check-expiry')) {
            /** @var int $expiryDays */
            $expiryDays = Config::get('huckle.expiry_warning', 30);
            $expiring = $config->expiring($expiryDays);
            $expired = $config->expired();

            if ($expired->isNotEmpty()) {
                $errors[] = sprintf('Found %d expired node(s)', $expired->count());

                foreach ($expired as $node) {
                    $this->error(sprintf('  ✗ EXPIRED: %s (expired: %s)', $node->pathString(), $node->expires));
                }
            }

            if ($expiring->isNotEmpty()) {
                $warnings[] = sprintf('Found %d node(s) expiring within %d days', $expiring->count(), $expiryDays);

                foreach ($expiring as $node) {
                    $this->warn(sprintf('  ! Expiring: %s (expires: %s)', $node->pathString(), $node->expires));
                }
            }

            if ($expired->isEmpty() && $expiring->isEmpty()) {
                $this->info('✓ No nodes expiring soon');
            }
        }

        // Check rotation
        if ($this->option('check-rotation')) {
            /** @var int $rotationDays */
            $rotationDays = Config::get('huckle.rotation_warning', 90);
            $needsRotation = $config->needsRotation($rotationDays);

            if ($needsRotation->isNotEmpty()) {
                $warnings[] = sprintf('Found %d node(s) needing rotation', $needsRotation->count());

                foreach ($needsRotation as $node) {
                    $rotated = $node->rotated ?? 'never';
                    $this->warn(sprintf('  ! Needs rotation: %s (last: %s)', $node->pathString(), $rotated));
                }
            } else {
                $this->info('✓ All nodes recently rotated');
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

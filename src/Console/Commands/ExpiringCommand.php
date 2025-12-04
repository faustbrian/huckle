<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Huckle\Console\Commands;

use Cline\Huckle\HuckleManager;
use Cline\Huckle\Parser\Credential;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;

use const JSON_PRETTY_PRINT;

use function collect;
use function json_encode;
use function sprintf;

/**
 * List credentials that are expiring soon or need rotation.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class ExpiringCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'huckle:expiring
        {--days= : Days to consider "expiring soon"}
        {--include-rotation : Also show credentials needing rotation}
        {--json : Output as JSON}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List credentials that are expiring soon';

    /**
     * Execute the console command.
     *
     * @param  HuckleManager $huckle The Huckle manager instance
     * @return int           The exit code
     */
    public function handle(HuckleManager $huckle): int
    {
        /** @var int $defaultDays */
        $defaultDays = Config::get('huckle.expiry_warning', 30);
        $daysOption = $this->option('days');
        $days = $daysOption !== null ? (int) $daysOption : $defaultDays;
        $includeRotation = $this->option('include-rotation');
        $json = $this->option('json');

        // Get expired credentials
        $expired = $huckle->expired();

        // Get expiring soon
        $expiring = $huckle->expiring($days);

        // Get needing rotation
        /** @var int $rotationWarning */
        $rotationWarning = Config::get('huckle.rotation_warning', 90);
        $needsRotation = $includeRotation
            ? $huckle->needsRotation($rotationWarning)
            : collect();

        // Output as JSON
        if ($json) {
            $data = [
                'expired' => $expired->map(fn (Credential $c): array => [
                    'path' => $c->path(),
                    'expires' => $c->expires,
                ])->values()->all(),
                'expiring' => $expiring->map(fn (Credential $c): array => [
                    'path' => $c->path(),
                    'expires' => $c->expires,
                ])->values()->all(),
            ];

            if ($includeRotation) {
                $data['needs_rotation'] = $needsRotation->map(fn (Credential $c): array => [
                    'path' => $c->path(),
                    'rotated' => $c->rotated,
                ])->values()->all();
            }

            $encoded = json_encode($data, JSON_PRETTY_PRINT);
            $this->line($encoded !== false ? $encoded : '{}');

            return self::SUCCESS;
        }

        // Output formatted
        $hasIssues = false;

        // Expired
        if ($expired->isNotEmpty()) {
            $hasIssues = true;
            $this->error('EXPIRED credentials:');

            foreach ($expired as $credential) {
                $this->line(sprintf('  ✗ %s (expired: %s)', $credential->path(), $credential->expires));
            }

            $this->newLine();
        }

        // Expiring soon
        if ($expiring->isNotEmpty()) {
            $hasIssues = true;
            $this->warn(sprintf('Expiring within %d days:', $days));

            foreach ($expiring as $credential) {
                $this->line(sprintf('  ! %s (expires: %s)', $credential->path(), $credential->expires));
            }

            $this->newLine();
        }

        // Needs rotation
        if ($includeRotation && $needsRotation->isNotEmpty()) {
            $hasIssues = true;
            $this->warn(sprintf('Needs rotation (>%d days):', $rotationWarning));

            foreach ($needsRotation as $credential) {
                $rotated = $credential->rotated ?? 'never';
                $this->line(sprintf('  ! %s (last: %s)', $credential->path(), $rotated));
            }

            $this->newLine();
        }

        // Summary
        if (!$hasIssues) {
            $this->info('✓ No credentials expiring soon');
        }

        return $hasIssues && $expired->isNotEmpty() ? self::FAILURE : self::SUCCESS;
    }
}

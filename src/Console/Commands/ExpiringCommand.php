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

use const JSON_PRETTY_PRINT;

use function collect;
use function json_encode;
use function sprintf;

/**
 * Artisan command to list nodes that are expiring soon or need rotation.
 *
 * Provides CLI interface for monitoring node lifecycle by identifying
 * expired nodes, nodes expiring within a specified timeframe,
 * and nodes that haven't been rotated recently. Helps maintain security
 * by proactively alerting to nodes requiring attention.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class ExpiringCommand extends Command
{
    /**
     * Command signature defining arguments and options.
     *
     * Accepts options to specify the expiration warning threshold in days,
     * include rotation checks, and output results in JSON format.
     *
     * @var string
     */
    protected $signature = 'huckle:expiring
        {--days= : Days to consider "expiring soon"}
        {--include-rotation : Also show nodes needing rotation}
        {--json : Output as JSON}';

    /**
     * Brief description displayed in command listings.
     *
     * @var string
     */
    protected $description = 'List nodes that are expiring soon';

    /**
     * Execute the console command.
     *
     * Retrieves and displays nodes based on their expiration and rotation
     * status. Returns FAILURE if any nodes are expired, SUCCESS otherwise.
     * Output can be formatted as human-readable text or JSON for automation.
     *
     * @param HuckleManager $huckle The Huckle manager instance
     *
     * @return int FAILURE if expired nodes exist, SUCCESS otherwise
     */
    public function handle(HuckleManager $huckle): int
    {
        /** @var int $defaultDays */
        $defaultDays = Config::get('huckle.expiry_warning', 30);
        $daysOption = $this->option('days');
        $days = $daysOption !== null ? (int) $daysOption : $defaultDays;
        $includeRotation = $this->option('include-rotation');
        $json = $this->option('json');

        // Get expired nodes
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
                'expired' => $expired->map(fn (Node $n): array => [
                    'path' => $n->pathString(),
                    'expires' => $n->expires,
                ])->values()->all(),
                'expiring' => $expiring->map(fn (Node $n): array => [
                    'path' => $n->pathString(),
                    'expires' => $n->expires,
                ])->values()->all(),
            ];

            if ($includeRotation) {
                $data['needs_rotation'] = $needsRotation->map(fn (Node $n): array => [
                    'path' => $n->pathString(),
                    'rotated' => $n->rotated,
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
            $this->error('EXPIRED nodes:');

            foreach ($expired as $node) {
                $this->line(sprintf('  ✗ %s (expired: %s)', $node->pathString(), $node->expires));
            }

            $this->newLine();
        }

        // Expiring soon
        if ($expiring->isNotEmpty()) {
            $hasIssues = true;
            $this->warn(sprintf('Expiring within %d days:', $days));

            foreach ($expiring as $node) {
                $this->line(sprintf('  ! %s (expires: %s)', $node->pathString(), $node->expires));
            }

            $this->newLine();
        }

        // Needs rotation
        if ($includeRotation && $needsRotation->isNotEmpty()) {
            $hasIssues = true;
            $this->warn(sprintf('Needs rotation (>%d days):', $rotationWarning));

            foreach ($needsRotation as $node) {
                $rotated = $node->rotated ?? 'never';
                $this->line(sprintf('  ! %s (last: %s)', $node->pathString(), $rotated));
            }

            $this->newLine();
        }

        // Summary
        if (!$hasIssues) {
            $this->info('✓ No nodes expiring soon');
        }

        return $hasIssues && $expired->isNotEmpty() ? self::FAILURE : self::SUCCESS;
    }
}

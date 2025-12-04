<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Huckle\Console\Commands;

use Cline\Hcl\Hcl;
use Illuminate\Console\Command;
use Throwable;

use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function sprintf;

/**
 * Convert HCL files to JSON format.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class Hcl2JsonCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'huckle:hcl2json
        {input : Path to the HCL file}
        {output? : Path for the output JSON file (defaults to stdout)}
        {--compact : Output compact JSON without pretty printing}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Convert an HCL file to JSON format';

    /**
     * Execute the console command.
     *
     * @return int The exit code
     */
    public function handle(): int
    {
        /** @var string $inputPath */
        $inputPath = $this->argument('input');

        /** @var null|string $outputPath */
        $outputPath = $this->argument('output');
        $pretty = !$this->option('compact');

        if (!file_exists($inputPath)) {
            $this->error('Input file not found: '.$inputPath);

            return self::FAILURE;
        }

        try {
            $hcl = file_get_contents($inputPath);

            if ($hcl === false) {
                $this->error('Failed to read input file: '.$inputPath);

                return self::FAILURE;
            }

            $json = Hcl::toJson($hcl, $pretty);

            if ($outputPath !== null) {
                file_put_contents($outputPath, $json);
                $this->info(sprintf('Converted %s to %s', $inputPath, $outputPath));
            } else {
                $this->line($json);
            }

            return self::SUCCESS;
        } catch (Throwable $throwable) {
            $this->error('Failed to convert HCL: '.$throwable->getMessage());

            return self::FAILURE;
        }
    }
}

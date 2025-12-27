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
 * Artisan command to convert HCL files to JSON format.
 *
 * Provides CLI interface for converting HashiCorp Configuration Language (HCL)
 * files to JSON format. Supports both pretty-printed and compact JSON output,
 * with the option to write to a file or output to stdout for piping to other
 * commands. Useful for integrating HCL configurations with JSON-based tooling.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class Hcl2JsonCommand extends Command
{
    /**
     * Command signature defining arguments and options.
     *
     * Accepts an input HCL file path and optional output JSON file path, with
     * an option to disable pretty-printing for compact JSON output. Outputs
     * to stdout when no output path is specified.
     *
     * @var string
     */
    protected $signature = 'huckle:convert:to-json
        {input : Path to the HCL file}
        {output? : Path for the output JSON file (defaults to stdout)}
        {--compact : Output compact JSON without pretty printing}';

    /**
     * Brief description displayed in command listings.
     *
     * @var string
     */
    protected $description = 'Convert an HCL file to JSON format';

    /**
     * Execute the console command.
     *
     * Reads an HCL file, converts it to JSON using the HCL parser, and either
     * writes the result to a file or outputs to stdout. Returns FAILURE if the
     * file doesn't exist, cannot be read, or conversion fails.
     *
     * @return int FAILURE on error, SUCCESS on successful conversion
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

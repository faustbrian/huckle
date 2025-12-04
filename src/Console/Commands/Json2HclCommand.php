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
 * Convert JSON files to HCL format.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class Json2HclCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'huckle:json2hcl
        {input : Path to the JSON file}
        {output? : Path for the output HCL file (defaults to stdout)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Convert a JSON file to HCL format';

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

        if (!file_exists($inputPath)) {
            $this->error('Input file not found: '.$inputPath);

            return self::FAILURE;
        }

        try {
            $json = file_get_contents($inputPath);

            if ($json === false) {
                $this->error('Failed to read input file: '.$inputPath);

                return self::FAILURE;
            }

            $hcl = Hcl::fromJson($json);

            if ($outputPath !== null) {
                file_put_contents($outputPath, $hcl);
                $this->info(sprintf('Converted %s to %s', $inputPath, $outputPath));
            } else {
                $this->line($hcl);
            }

            return self::SUCCESS;
        } catch (Throwable $throwable) {
            $this->error('Failed to convert JSON: '.$throwable->getMessage());

            return self::FAILURE;
        }
    }
}

<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Huckle\HuckleManager;
use Illuminate\Support\Facades\File;

use function Pest\Laravel\artisan;

describe('ConfigEncryptCommand', function (): void {
    beforeEach(function (): void {
        $this->tempDir = sys_get_temp_dir().'/huckle-test-'.uniqid('', true);
        mkdir($this->tempDir, 0o755, true);
    });

    afterEach(function (): void {
        File::deleteDirectory($this->tempDir);
    });

    test('encrypts a configuration file', function (): void {
        $file = $this->tempDir.'/config.hcl';
        file_put_contents($file, 'secret = "value"');

        artisan('huckle:encrypt', ['file' => $file])
            ->assertSuccessful()
            ->expectsOutputToContain('encrypted successfully');

        expect(file_exists($file.'.encrypted'))->toBeTrue();
    });

    test('generates encryption key when not provided', function (): void {
        $file = $this->tempDir.'/config.hcl';
        file_put_contents($file, 'data = "test"');

        artisan('huckle:encrypt', ['file' => $file])
            ->assertSuccessful()
            ->expectsOutputToContain('Store this key securely');
    });

    test('uses provided encryption key', function (): void {
        $file = $this->tempDir.'/config.hcl';
        file_put_contents($file, 'data = "test"');
        $key = base64_encode(random_bytes(32));

        artisan('huckle:encrypt', ['file' => $file, '--key' => $key])
            ->assertSuccessful();

        expect(file_exists($file.'.encrypted'))->toBeTrue();
    });

    test('prune option deletes original file', function (): void {
        $file = $this->tempDir.'/config.hcl';
        file_put_contents($file, 'data = "test"');

        artisan('huckle:encrypt', ['file' => $file, '--prune' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('deleted');

        expect(file_exists($file))->toBeFalse()
            ->and(file_exists($file.'.encrypted'))->toBeTrue();
    });

    test('force option overwrites existing encrypted file', function (): void {
        $file = $this->tempDir.'/config.hcl';
        file_put_contents($file, 'data = "original"');

        artisan('huckle:encrypt', ['file' => $file])
            ->assertSuccessful();

        file_put_contents($file, 'data = "updated"');

        artisan('huckle:encrypt', ['file' => $file, '--force' => true])
            ->assertSuccessful();
    });

    test('fails for non-existent file', function (): void {
        artisan('huckle:encrypt', ['file' => '/nonexistent/file.hcl'])
            ->assertFailed();
    });

    test('encrypts environment-specific file with suffix style', function (): void {
        $baseFile = $this->tempDir.'/config.hcl';
        $envFile = $this->tempDir.'/config.production.hcl';
        file_put_contents($envFile, 'env = "production"');

        artisan('huckle:encrypt', ['file' => $baseFile, '--environment' => 'production'])
            ->assertSuccessful();

        expect(file_exists($envFile.'.encrypted'))->toBeTrue();
    });

    test('encrypts environment-specific file with directory style', function (): void {
        $envDir = $this->tempDir.'/production';
        mkdir($envDir);
        $baseFile = $this->tempDir.'/config.hcl';
        $envFile = $envDir.'/config.hcl';
        file_put_contents($envFile, 'env = "production"');

        artisan('huckle:encrypt', [
            'file' => $baseFile,
            '--environment' => 'production',
            '--env-style' => 'directory',
        ])
            ->assertSuccessful();

        expect(file_exists($envFile.'.encrypted'))->toBeTrue();
    });

    test('uses custom cipher', function (): void {
        $file = $this->tempDir.'/config.hcl';
        file_put_contents($file, 'data = "test"');

        artisan('huckle:encrypt', ['file' => $file, '--cipher' => 'AES-128-CBC'])
            ->assertSuccessful()
            ->expectsOutputToContain('AES-128-CBC');
    });

    test('encrypts all files in a directory', function (): void {
        file_put_contents($this->tempDir.'/config1.hcl', 'key1 = "value1"');
        file_put_contents($this->tempDir.'/config2.hcl', 'key2 = "value2"');
        file_put_contents($this->tempDir.'/config3.json', '{"key3": "value3"}');

        artisan('huckle:encrypt', ['file' => $this->tempDir])
            ->assertSuccessful()
            ->expectsOutputToContain('3 file(s) encrypted');

        expect(file_exists($this->tempDir.'/config1.hcl.encrypted'))->toBeTrue()
            ->and(file_exists($this->tempDir.'/config2.hcl.encrypted'))->toBeTrue()
            ->and(file_exists($this->tempDir.'/config3.json.encrypted'))->toBeTrue();
    });

    test('encrypts directory with glob pattern filter', function (): void {
        file_put_contents($this->tempDir.'/config1.hcl', 'key1 = "value1"');
        file_put_contents($this->tempDir.'/config2.hcl', 'key2 = "value2"');
        file_put_contents($this->tempDir.'/config3.json', '{"key3": "value3"}');

        artisan('huckle:encrypt', ['file' => $this->tempDir, '--glob' => '*.hcl'])
            ->assertSuccessful()
            ->expectsOutputToContain('2 file(s) encrypted');

        expect(file_exists($this->tempDir.'/config1.hcl.encrypted'))->toBeTrue()
            ->and(file_exists($this->tempDir.'/config2.hcl.encrypted'))->toBeTrue()
            ->and(file_exists($this->tempDir.'/config3.json.encrypted'))->toBeFalse();
    });

    test('encrypts directory recursively', function (): void {
        mkdir($this->tempDir.'/subdir', 0o755, true);
        file_put_contents($this->tempDir.'/config1.hcl', 'key1 = "value1"');
        file_put_contents($this->tempDir.'/subdir/config2.hcl', 'key2 = "value2"');

        artisan('huckle:encrypt', ['file' => $this->tempDir, '--recursive' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('2 file(s) encrypted');

        expect(file_exists($this->tempDir.'/config1.hcl.encrypted'))->toBeTrue()
            ->and(file_exists($this->tempDir.'/subdir/config2.hcl.encrypted'))->toBeTrue();
    });

    test('encrypts directory with prune option deletes originals', function (): void {
        file_put_contents($this->tempDir.'/config1.hcl', 'key1 = "value1"');
        file_put_contents($this->tempDir.'/config2.hcl', 'key2 = "value2"');

        artisan('huckle:encrypt', ['file' => $this->tempDir, '--prune' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('deleted');

        expect(file_exists($this->tempDir.'/config1.hcl'))->toBeFalse()
            ->and(file_exists($this->tempDir.'/config2.hcl'))->toBeFalse()
            ->and(file_exists($this->tempDir.'/config1.hcl.encrypted'))->toBeTrue()
            ->and(file_exists($this->tempDir.'/config2.hcl.encrypted'))->toBeTrue();
    });

    test('encrypts empty directory with warning', function (): void {
        artisan('huckle:encrypt', ['file' => $this->tempDir])
            ->assertSuccessful()
            ->expectsOutputToContain('No files found');
    });

    test('skips already encrypted files when encrypting directory', function (): void {
        file_put_contents($this->tempDir.'/config.hcl', 'key = "value"');
        file_put_contents($this->tempDir.'/already.hcl.encrypted', 'encrypted-data');

        artisan('huckle:encrypt', ['file' => $this->tempDir])
            ->assertSuccessful()
            ->expectsOutputToContain('1 file(s) encrypted');

        expect(file_exists($this->tempDir.'/config.hcl.encrypted'))->toBeTrue();
    });

    test('encrypts with --app-key flag using APP_KEY', function (): void {
        // Set APP_KEY in config
        $key = base64_encode(random_bytes(32));
        config(['app.key' => 'base64:'.$key]);

        $file = $this->tempDir.'/config.hcl';
        $content = 'secret = "value"';
        file_put_contents($file, $content);

        artisan('huckle:encrypt', [
            'file' => $file,
            '--app-key' => true,
        ])
            ->assertSuccessful()
            ->expectsOutputToContain('encrypted successfully');

        expect(file_exists($file.'.encrypted'))->toBeTrue();

        // Verify we can decrypt with the same APP_KEY
        $manager = resolve(HuckleManager::class);
        $decrypted = $manager->decrypt($file.'.encrypted', $key, force: true);

        expect(file_get_contents($decrypted))->toBe($content);
    });

    test('encrypts with config use_app_key enabled', function (): void {
        // Set APP_KEY and config option
        $key = base64_encode(random_bytes(32));
        config(['app.key' => 'base64:'.$key]);
        config(['huckle.encryption.use_app_key' => true]);

        $file = $this->tempDir.'/config.hcl';
        $content = 'secret = "value"';
        file_put_contents($file, $content);

        artisan('huckle:encrypt', ['file' => $file])
            ->assertSuccessful()
            ->expectsOutputToContain('encrypted successfully');

        expect(file_exists($file.'.encrypted'))->toBeTrue();

        // Verify we can decrypt with the same APP_KEY
        $manager = resolve(HuckleManager::class);
        $decrypted = $manager->decrypt($file.'.encrypted', $key, force: true);

        expect(file_get_contents($decrypted))->toBe($content);
    });

    test('--key option takes precedence over --app-key flag', function (): void {
        // Set a different APP_KEY in config
        $appKey = base64_encode(random_bytes(32));
        config(['app.key' => 'base64:'.$appKey]);

        // Use a separate key for encryption
        $encryptionKey = base64_encode(random_bytes(32));

        $file = $this->tempDir.'/config.hcl';
        $content = 'secret = "value"';
        file_put_contents($file, $content);

        // Passing both --key and --app-key, --key should win
        artisan('huckle:encrypt', [
            'file' => $file,
            '--key' => $encryptionKey,
            '--app-key' => true,
        ])
            ->assertSuccessful();

        // Verify we can decrypt with encryptionKey, not appKey
        $manager = resolve(HuckleManager::class);
        $decrypted = $manager->decrypt($file.'.encrypted', $encryptionKey, force: true);

        expect(file_get_contents($decrypted))->toBe($content);
    });

    test('fails with --app-key when APP_KEY is not set', function (): void {
        config(['app.key' => null]);

        artisan('huckle:encrypt', [
            'file' => $this->tempDir.'/config.hcl',
            '--app-key' => true,
        ])
            ->assertFailed()
            ->expectsOutputToContain('APP_KEY is not set');
    });
});

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

describe('ConfigDecryptCommand', function (): void {
    beforeEach(function (): void {
        $this->tempDir = sys_get_temp_dir().'/huckle-test-'.uniqid('', true);
        mkdir($this->tempDir, 0o755, true);
    });

    afterEach(function (): void {
        File::deleteDirectory($this->tempDir);
    });

    test('decrypts an encrypted configuration file', function (): void {
        $file = $this->tempDir.'/config.hcl';
        $content = 'secret = "value"';
        file_put_contents($file, $content);

        // Encrypt first
        $manager = resolve(HuckleManager::class);
        $result = $manager->encrypt($file);
        $key = $result['key'];

        // Remove original
        unlink($file);

        // Decrypt
        artisan('huckle:config:decrypt', ['file' => $result['path'], '--key' => $key])
            ->assertSuccessful()
            ->expectsOutputToContain('decrypted successfully');

        expect(file_exists($file))->toBeTrue()
            ->and(file_get_contents($file))->toBe($content);
    });

    test('decrypts with force option overwrites existing', function (): void {
        $file = $this->tempDir.'/config.hcl';
        $content = 'secret = "value"';
        file_put_contents($file, $content);

        // Encrypt first
        $manager = resolve(HuckleManager::class);
        $result = $manager->encrypt($file);
        $key = $result['key'];

        // Modify original
        file_put_contents($file, 'different = "content"');

        // Decrypt with force
        artisan('huckle:config:decrypt', ['file' => $result['path'], '--key' => $key, '--force' => true])
            ->assertSuccessful();

        expect(file_get_contents($file))->toBe($content);
    });

    test('keeps encrypted file when --keep option is used', function (): void {
        $file = $this->tempDir.'/config.hcl';
        file_put_contents($file, 'data = "test"');

        $manager = resolve(HuckleManager::class);
        $result = $manager->encrypt($file, prune: true);
        $key = $result['key'];

        artisan('huckle:config:decrypt', ['file' => $result['path'], '--key' => $key, '--keep' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('Encrypted file has been kept');

        expect(file_exists($result['path']))->toBeTrue()
            ->and(file_exists($file))->toBeTrue();
    });

    test('deletes encrypted file by default after decryption', function (): void {
        $file = $this->tempDir.'/config.hcl';
        file_put_contents($file, 'data = "test"');

        $manager = resolve(HuckleManager::class);
        $result = $manager->encrypt($file, prune: true);
        $key = $result['key'];
        $encryptedPath = $result['path'];

        artisan('huckle:config:decrypt', ['file' => $encryptedPath, '--key' => $key])
            ->assertSuccessful();

        expect(file_exists($encryptedPath))->toBeFalse()
            ->and(file_exists($file))->toBeTrue();
    });

    test('fails for non-existent encrypted file', function (): void {
        artisan('huckle:config:decrypt', ['file' => '/nonexistent/file.hcl.encrypted', '--key' => 'somekey'])
            ->assertFailed();
    });

    test('fails when no key provided and use_app_key is false', function (): void {
        config(['huckle.encryption.use_app_key' => false]);

        artisan('huckle:config:decrypt', ['file' => $this->tempDir.'/config.hcl.encrypted'])
            ->assertFailed()
            ->expectsOutputToContain('No decryption key provided');
    });

    test('decrypts with wrong key fails', function (): void {
        $file = $this->tempDir.'/config.hcl';
        file_put_contents($file, 'secret = "value"');

        $manager = resolve(HuckleManager::class);
        $result = $manager->encrypt($file, prune: true);

        // Use a different key
        $wrongKey = base64_encode(random_bytes(32));

        artisan('huckle:config:decrypt', ['file' => $result['path'], '--key' => $wrongKey, '--force' => true])
            ->assertFailed()
            ->expectsOutputToContain('Decryption failed');
    });

    test('decrypts to custom path', function (): void {
        $file = $this->tempDir.'/config.hcl';
        $content = 'secret = "value"';
        file_put_contents($file, $content);

        $outputDir = $this->tempDir.'/output';
        mkdir($outputDir);

        $manager = resolve(HuckleManager::class);
        $result = $manager->encrypt($file, prune: true);
        $key = $result['key'];

        artisan('huckle:config:decrypt', [
            'file' => $result['path'],
            '--key' => $key,
            '--path' => $outputDir,
        ])
            ->assertSuccessful();

        expect(file_exists($outputDir.'/config.hcl'))->toBeTrue()
            ->and(file_get_contents($outputDir.'/config.hcl'))->toBe($content);
    });

    test('decrypts with custom filename', function (): void {
        $file = $this->tempDir.'/config.hcl';
        $content = 'secret = "value"';
        file_put_contents($file, $content);

        $manager = resolve(HuckleManager::class);
        $result = $manager->encrypt($file, prune: true);
        $key = $result['key'];

        artisan('huckle:config:decrypt', [
            'file' => $result['path'],
            '--key' => $key,
            '--filename' => 'decrypted-config.hcl',
        ])
            ->assertSuccessful();

        expect(file_exists($this->tempDir.'/decrypted-config.hcl'))->toBeTrue()
            ->and(file_get_contents($this->tempDir.'/decrypted-config.hcl'))->toBe($content);
    });

    test('decrypts all encrypted files in directory', function (): void {
        file_put_contents($this->tempDir.'/config1.hcl', 'key1 = "value1"');
        file_put_contents($this->tempDir.'/config2.hcl', 'key2 = "value2"');

        $manager = resolve(HuckleManager::class);
        $result = $manager->encryptDirectory($this->tempDir, prune: true);
        $key = $result['key'];

        artisan('huckle:config:decrypt', ['file' => $this->tempDir, '--key' => $key])
            ->assertSuccessful()
            ->expectsOutputToContain('2 file(s) decrypted');

        expect(file_exists($this->tempDir.'/config1.hcl'))->toBeTrue()
            ->and(file_exists($this->tempDir.'/config2.hcl'))->toBeTrue();
    });

    test('decrypts directory recursively', function (): void {
        mkdir($this->tempDir.'/subdir', 0o755, true);
        file_put_contents($this->tempDir.'/config1.hcl', 'key1 = "value1"');
        file_put_contents($this->tempDir.'/subdir/config2.hcl', 'key2 = "value2"');

        $manager = resolve(HuckleManager::class);
        $result = $manager->encryptDirectory($this->tempDir, prune: true, recursive: true);
        $key = $result['key'];

        artisan('huckle:config:decrypt', ['file' => $this->tempDir, '--key' => $key, '--recursive' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('2 file(s) decrypted');

        expect(file_exists($this->tempDir.'/config1.hcl'))->toBeTrue()
            ->and(file_exists($this->tempDir.'/subdir/config2.hcl'))->toBeTrue();
    });

    test('shows warning when no encrypted files found in directory', function (): void {
        artisan('huckle:config:decrypt', ['file' => $this->tempDir, '--key' => 'somekey'])
            ->assertSuccessful()
            ->expectsOutputToContain('No encrypted files found');
    });

    test('decrypts with --app-key flag using APP_KEY', function (): void {
        $key = base64_encode(random_bytes(32));
        config(['app.key' => 'base64:'.$key]);

        $file = $this->tempDir.'/config.hcl';
        $content = 'secret = "value"';
        file_put_contents($file, $content);

        $manager = resolve(HuckleManager::class);
        $result = $manager->encrypt($file, key: $key, prune: true);

        artisan('huckle:config:decrypt', [
            'file' => $result['path'],
            '--app-key' => true,
        ])
            ->assertSuccessful()
            ->expectsOutputToContain('decrypted successfully');

        expect(file_get_contents($file))->toBe($content);
    });

    test('decrypts with config use_app_key enabled', function (): void {
        $key = base64_encode(random_bytes(32));
        config(['app.key' => 'base64:'.$key]);
        config(['huckle.encryption.use_app_key' => true]);

        $file = $this->tempDir.'/config.hcl';
        $content = 'secret = "value"';
        file_put_contents($file, $content);

        $manager = resolve(HuckleManager::class);
        $result = $manager->encrypt($file, key: $key, prune: true);

        artisan('huckle:config:decrypt', ['file' => $result['path']])
            ->assertSuccessful();

        expect(file_get_contents($file))->toBe($content);
    });

    test('fails with --app-key when APP_KEY is not set', function (): void {
        config(['app.key' => null]);

        artisan('huckle:config:decrypt', [
            'file' => $this->tempDir.'/config.hcl.encrypted',
            '--app-key' => true,
        ])
            ->assertFailed()
            ->expectsOutputToContain('APP_KEY is not set');
    });

    test('decrypts environment-specific file with suffix style', function (): void {
        $envFile = $this->tempDir.'/config.production.hcl';
        $content = 'env = "production"';
        file_put_contents($envFile, $content);

        $manager = resolve(HuckleManager::class);
        $result = $manager->encrypt($envFile, prune: true);
        $key = $result['key'];

        $baseFile = $this->tempDir.'/config.hcl';

        artisan('huckle:config:decrypt', [
            'file' => $baseFile,
            '--key' => $key,
            '--environment' => 'production',
        ])
            ->assertSuccessful();

        expect(file_exists($envFile))->toBeTrue()
            ->and(file_get_contents($envFile))->toBe($content);
    });

    test('decrypts environment-specific file with directory style', function (): void {
        $envDir = $this->tempDir.'/production';
        mkdir($envDir);
        $envFile = $envDir.'/config.hcl';
        $content = 'env = "production"';
        file_put_contents($envFile, $content);

        $manager = resolve(HuckleManager::class);
        $result = $manager->encrypt($envFile, prune: true);
        $key = $result['key'];

        $baseFile = $this->tempDir.'/config.hcl';

        artisan('huckle:config:decrypt', [
            'file' => $baseFile,
            '--key' => $key,
            '--environment' => 'production',
            '--env-style' => 'directory',
        ])
            ->assertSuccessful();

        expect(file_exists($envFile))->toBeTrue()
            ->and(file_get_contents($envFile))->toBe($content);
    });

    test('uses custom cipher for decryption', function (): void {
        $file = $this->tempDir.'/config.hcl';
        $content = 'secret = "value"';
        file_put_contents($file, $content);

        // Encrypt with AES-128-CBC
        $manager = resolve(HuckleManager::class);
        $result = $manager->encrypt($file, cipher: 'AES-128-CBC', prune: true);
        $key = $result['key'];

        artisan('huckle:config:decrypt', [
            'file' => $result['path'],
            '--key' => $key,
            '--cipher' => 'AES-128-CBC',
        ])
            ->assertSuccessful();

        expect(file_get_contents($file))->toBe($content);
    });
});

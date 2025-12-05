<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Default Configuration Path
    |--------------------------------------------------------------------------
    |
    | The path to your main Huckle HCL configuration file. This file contains
    | your partition definitions, environments, services, and export mappings.
    |
    */

    'path' => base_path('config/huckle.hcl'),

    /*
    |--------------------------------------------------------------------------
    | Environment-Specific Configuration Files
    |--------------------------------------------------------------------------
    |
    | Define separate configuration files for different environments. Huckle
    | will automatically load the appropriate file based on the current
    | Laravel environment (APP_ENV).
    |
    */

    'environments' => [
        'production' => base_path('.huckle.production'),
        'staging'    => base_path('.huckle.staging'),
        'testing'    => base_path('.huckle.testing'),
        'sandbox'    => base_path('.huckle.sandbox'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Auto-Discovery
    |--------------------------------------------------------------------------
    |
    | When enabled, Huckle will automatically discover environment-specific
    | configuration files (e.g., .huckle.production, .huckle.staging) based
    | on APP_ENV, even if not explicitly defined in the environments array.
    |
    */

    'auto_discovery' => false,

    /*
    |--------------------------------------------------------------------------
    | Enabled
    |--------------------------------------------------------------------------
    |
    | This option allows you to completely disable Huckle. When disabled,
    | all Huckle operations will be no-ops. You can also use Huckle::disable()
    | and Huckle::enable() at runtime to toggle this dynamically.
    |
    */

    'enabled' => env('HUCKLE_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Auto-Export to Environment
    |--------------------------------------------------------------------------
    |
    | When enabled, Huckle will automatically export all configuration values
    | to the environment on application boot. This makes them available
    | via Laravel's env() helper.
    |
    | WARNING: This may expose sensitive values in environment dumps.
    | Only enable in trusted environments.
    |
    */

    'auto_export' => env('HUCKLE_AUTO_EXPORT', false),

    /*
    |--------------------------------------------------------------------------
    | Mask Sensitive Values
    |--------------------------------------------------------------------------
    |
    | When enabled, sensitive values (wrapped in sensitive()) will be masked
    | in logs, dumps, and command output. This prevents accidental exposure
    | of secrets in debug output.
    |
    */

    'mask_sensitive' => true,

    /*
    |--------------------------------------------------------------------------
    | Rotation Warning (Days)
    |--------------------------------------------------------------------------
    |
    | Nodes with credentials that haven't been rotated within this many days
    | will be flagged in the huckle:expiring command and lint checks.
    |
    */

    'rotation_warning' => 90,

    /*
    |--------------------------------------------------------------------------
    | Expiry Warning (Days)
    |--------------------------------------------------------------------------
    |
    | Nodes that will expire within this many days will be flagged
    | in the huckle:expiring command and lint checks.
    |
    */

    'expiry_warning' => 30,

    /*
    |--------------------------------------------------------------------------
    | Config Mappings
    |--------------------------------------------------------------------------
    |
    | Define mappings from environment variable names to Laravel config paths.
    | When exporting to config, Huckle will use these mappings to write values
    | directly to Laravel's config repository, making them work with config:cache.
    |
    | These mappings are merged with (and overridden by) mappings defined in
    | the HCL file's `mappings` block and per-node `config` blocks.
    |
    | Example:
    |     'STRIPE_KEY' => 'cashier.key',
    |     'STRIPE_SECRET' => 'cashier.secret',
    |
    */

    'mappings' => [
        // 'STRIPE_KEY' => 'cashier.key',
        // 'STRIPE_SECRET' => 'cashier.secret',
    ],

    /*
    |--------------------------------------------------------------------------
    | Encryption Settings
    |--------------------------------------------------------------------------
    |
    | Below are all of the encryption-related settings for Huckle. These
    | options control how configuration files are encrypted and decrypted
    | when using the encrypt() and decrypt() methods, including cipher
    | selection, key management, and environment-specific file handling.
    |
    */

    'encryption' => [

        /*
        |--------------------------------------------------------------------------
        | Use Application Key
        |--------------------------------------------------------------------------
        |
        | This option determines whether the encrypt and decrypt commands should
        | use the application's APP_KEY environment variable as the default
        | encryption key when no explicit --key option is provided. This can
        | also be enabled on a per-command basis using the --app-key flag.
        |
        | WARNING: When using APP_KEY for encryption, the encrypted files can
        | only be decrypted in environments that share the same APP_KEY value.
        | This coupling may have security and deployment implications that you
        | should carefully consider before enabling this functionality.
        |
        */

        'use_app_key' => false,

        /*
        |--------------------------------------------------------------------------
        | Default Cipher
        |--------------------------------------------------------------------------
        |
        | This option specifies the default encryption cipher that Huckle will
        | use when encrypting configuration files. Laravel supports both AES-256
        | and AES-128 encryption ciphers, which offer different key lengths
        | and performance characteristics for your security requirements.
        |
        | Supported ciphers: "AES-256-CBC", "AES-128-CBC"
        |
        */

        'cipher' => 'AES-256-CBC',

        /*
        |--------------------------------------------------------------------------
        | Environment Style
        |--------------------------------------------------------------------------
        |
        | This option controls how environment-specific encrypted configuration
        | files are organized within your project structure. You may choose to
        | append the environment name as a suffix to the filename, or organize
        | environment files into separate subdirectories for better isolation.
        |
        | Supported styles: "suffix", "directory"
        |
        */

        'env_style' => 'suffix',

        /*
        |--------------------------------------------------------------------------
        | Environment Directory
        |--------------------------------------------------------------------------
        |
        | When using the "directory" environment style, this option specifies
        | the base directory where environment-specific subdirectories will
        | be located. The path may be relative to the configuration file's
        | directory or an absolute path for more flexible organization.
        |
        | For example, with env_directory set to "environments" and an env
        | value of "production", a file at config/app.hcl would resolve
        | to config/environments/production/app.hcl during encryption.
        |
        | When this value is null, environment directories will be created
        | as siblings to the configuration file, such as config/production
        | for files originally located in the config directory.
        |
        */

        'env_directory' => null,

    ],

];

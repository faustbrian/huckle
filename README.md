[![GitHub Workflow Status][ico-tests]][link-tests]
[![Latest Version on Packagist][ico-version]][link-packagist]
[![Software License][ico-license]](LICENSE.md)
[![Total Downloads][ico-downloads]][link-downloads]

------

Huckle is a Laravel package for managing credentials using HCL (HashiCorp Configuration Language) syntax. It provides a purpose-built credential management system with support for tagging, environments, sensitive value masking, and environment variable exports.

## Requirements

> **Requires [PHP 8.4+](https://php.net/releases/) and Laravel 12+**

## Installation

```bash
composer require cline/huckle
```

Publish the configuration:

```bash
php artisan vendor:publish --tag=huckle-config
```

## Documentation

- **[Getting Started](cookbooks/getting-started.md)** - Create your first credentials file
- **[Environment Management](cookbooks/environment-management.md)** - Work with multiple environments
- **[Exports & Syncing](cookbooks/exports-syncing.md)** - Export to .env files and environment variables
- **[Connection Commands](cookbooks/connection-commands.md)** - Execute database CLI connections
- **[HCL Conversion](cookbooks/hcl-conversion.md)** - Convert between HCL and JSON formats

## Quick Start

Create a `credentials.hcl` file:

```hcl
group "database" "production" {
  tags = ["prod", "postgres"]

  credential "main" {
    host     = "db.prod.internal"
    port     = 5432
    username = "app_user"
    password = sensitive("secret123")

    export {
      DB_HOST     = self.host
      DB_PORT     = self.port
      DB_PASSWORD = self.password
    }

    connect "psql" {
      command = "psql -h ${self.host} -p ${self.port} -U ${self.username}"
    }
  }
}
```

Access credentials in your code:

```php
use Cline\Huckle\Facades\Huckle;

// Get a credential
$credential = Huckle::get('database.production.main');
$host = $credential->host;  // 'db.prod.internal'

// Get sensitive values
$password = $credential->password;           // SensitiveValue object
echo $password->reveal();                    // 'secret123'
echo $password->masked();                    // '********'

// Filter credentials
$prodCredentials = Huckle::inEnvironment('production');
$taggedCredentials = Huckle::tagged('postgres', 'critical');

// Export to environment variables
Huckle::exportToEnv('database.production.main');
```

## Artisan Commands

| Command | Description |
|---------|-------------|
| `huckle:list` | List all credentials |
| `huckle:show {path}` | Show credential details |
| `huckle:export` | Export as environment variables |
| `huckle:sync` | Sync exports to .env file |
| `huckle:lint` | Validate configuration |
| `huckle:connect {path} {connection}` | Execute connection command |
| `huckle:diff {env1} {env2}` | Compare environments |
| `huckle:expiring` | List expiring credentials |
| `huckle:hcl2json {input} [output]` | Convert HCL to JSON |
| `huckle:json2hcl {input} [output]` | Convert JSON to HCL |

## HCL Features

Huckle includes a generic HCL parser that supports:

- **Any block type** - Not limited to credentials
- **Nested blocks with labels** - `service "http" "web_proxy" { }`
- **Function calls** - `sensitive("value")`, `file("path")`
- **Interpolation** - `"${self.host}:${self.port}"`
- **HCL ↔ JSON conversion** - Full bidirectional conversion

```php
use Cline\Huckle\Hcl;

// Parse any HCL content
$data = Hcl::parse($hclContent);

// Convert HCL to JSON
$json = Hcl::toJson($hclContent);

// Convert JSON to HCL
$hcl = Hcl::fromJson($jsonContent);
```

## Change log

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) and [CODE_OF_CONDUCT](CODE_OF_CONDUCT.md) for details.

## Security

If you discover any security related issues, please use the [GitHub security reporting form][link-security] rather than the issue queue.

## Credits

- [Brian Faust][link-maintainer]
- [All Contributors][link-contributors]

## License

The MIT License. Please see [License File](LICENSE.md) for more information.

[ico-tests]: https://github.com/faustbrian/huckle/actions/workflows/quality-assurance.yaml/badge.svg
[ico-version]: https://img.shields.io/packagist/v/cline/huckle.svg
[ico-license]: https://img.shields.io/badge/License-MIT-green.svg
[ico-downloads]: https://img.shields.io/packagist/dt/cline/huckle.svg

[link-tests]: https://github.com/faustbrian/huckle/actions
[link-packagist]: https://packagist.org/packages/cline/huckle
[link-downloads]: https://packagist.org/packages/cline/huckle
[link-security]: https://github.com/faustbrian/huckle/security
[link-maintainer]: https://github.com/faustbrian
[link-contributors]: ../../contributors

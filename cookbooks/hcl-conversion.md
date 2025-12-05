# HCL Conversion

Convert between HCL and JSON formats for interoperability with other tools.

**Use case:** Integrating with CI/CD pipelines, generating configuration from other sources, or migrating existing JSON configs to HCL.

## HCL to JSON

### Via Artisan

```bash
# Convert to stdout
php artisan huckle:hcl2json credentials.hcl

# Output:
# {
#     "group": {
#         "database": {
#             "production": {
#                 "credential": {
#                     "main": {
#                         "host": "db.prod.internal",
#                         "port": 5432
#                     }
#                 }
#             }
#         }
#     }
# }

# Convert to file
php artisan huckle:hcl2json credentials.hcl credentials.json

# Compact output (no pretty printing)
php artisan huckle:hcl2json credentials.hcl --compact
```

### In Code

```php
use Cline\Huckle\Hcl;

$hcl = <<<'HCL'
    name = "my-app"
    version = 1.0
    enabled = true
    tags = ["web", "api"]
    HCL;

// Convert to JSON string
$json = Hcl::toJson($hcl);
// {"name": "my-app", "version": 1.0, ...}

// Compact JSON
$json = Hcl::toJson($hcl, pretty: false);

// Parse to PHP array
$data = Hcl::parse($hcl);
// ['name' => 'my-app', 'version' => 1.0, ...]

// Parse from file
$data = Hcl::parseFile('/path/to/config.hcl');
```

## JSON to HCL

### Via Artisan

```bash
# Convert to stdout
php artisan huckle:json2hcl config.json

# Output:
# name = "my-app"
# version = 1.0
# enabled = true

# Convert to file
php artisan huckle:json2hcl config.json config.hcl
```

### In Code

```php
use Cline\Huckle\Hcl;

$json = '{"name": "my-app", "port": 8080, "enabled": true}';

$hcl = Hcl::fromJson($json);
// name = "my-app"
// port = 8080
// enabled = true

// Convert from PHP array
$data = [
    'service' => [
        'http' => [
            'web_proxy' => [
                'listen_addr' => '127.0.0.1:8080',
            ],
        ],
    ],
];

$hcl = Hcl::arrayToHcl($data);
// service "http" "web_proxy" {
//   listen_addr = "127.0.0.1:8080"
// }
```

## Nested Block Conversion

The parser intelligently converts between nested HCL blocks and JSON structures:

### HCL Input

```hcl
io_mode = "async"

service "http" "web_proxy" {
  listen_addr = "127.0.0.1:8080"

  process "main" {
    command = ["/usr/local/bin/awesome-app", "server"]
  }

  process "mgmt" {
    command = ["/usr/local/bin/awesome-app", "mgmt"]
  }
}
```

### JSON Output

```json
{
    "io_mode": "async",
    "service": {
        "http": {
            "web_proxy": {
                "listen_addr": "127.0.0.1:8080",
                "process": {
                    "main": {
                        "command": ["/usr/local/bin/awesome-app", "server"]
                    },
                    "mgmt": {
                        "command": ["/usr/local/bin/awesome-app", "mgmt"]
                    }
                }
            }
        }
    }
}
```

## Function Calls

Function calls in HCL are preserved as special structures:

```hcl
data = file("config.json")
password = sensitive("secret")
```

```json
{
    "data": {
        "__function__": "file",
        "__args__": ["config.json"]
    },
    "password": {
        "__function__": "sensitive",
        "__args__": ["secret"]
    }
}
```

## Roundtrip Conversion

Data is preserved when converting HCL -> JSON -> HCL:

```php
use Cline\Huckle\Hcl;

$original = <<<'HCL'
    name = "roundtrip-test"
    version = 42
    enabled = true
    tags = ["a", "b", "c"]
    HCL;

// HCL -> JSON -> HCL
$json = Hcl::toJson($original);
$backToHcl = Hcl::fromJson($json);
$reparsed = Hcl::parse($backToHcl);

// Data is preserved
assert($reparsed['name'] === 'roundtrip-test');
assert($reparsed['version'] === 42);
assert($reparsed['enabled'] === true);
assert($reparsed['tags'] === ['a', 'b', 'c']);
```

## CI/CD Integration Example

```bash
#!/bin/bash
# deploy.sh - Generate environment-specific config from HCL

ENVIRONMENT=$1

# Convert HCL to JSON for processing
php artisan huckle:hcl2json credentials.hcl /tmp/creds.json

# Extract environment-specific values with jq
DB_HOST=$(jq -r ".group.database.${ENVIRONMENT}.credential.main.host" /tmp/creds.json)
DB_PORT=$(jq -r ".group.database.${ENVIRONMENT}.credential.main.port" /tmp/creds.json)

# Use values in deployment
echo "Deploying to ${DB_HOST}:${DB_PORT}"

# Cleanup
rm /tmp/creds.json
```

## Migration from JSON Config

```php
// Convert existing JSON config to HCL
$existingConfig = file_get_contents('legacy-config.json');
$hcl = Hcl::fromJson($existingConfig);
file_put_contents('config.hcl', $hcl);
```

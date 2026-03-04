Convert between HCL and JSON formats for interoperability and migration.

**Use case:** Integrating with tools that expect JSON, generating HCL from code, or migrating configurations.

## HCL to JSON

### CLI

```bash
# Output to stdout
php artisan huckle:hcl2json nodes.hcl

# Output to file
php artisan huckle:hcl2json nodes.hcl nodes.json
```

### Code

```php
use Cline\Hcl\Hcl;

$hcl = <<<'HCL'
name = "my-app"
version = "1.0.0"
enabled = true
ports = [80, 443]
HCL;

// Pretty-printed JSON (default)
$json = Hcl::toJson($hcl);

// Compact JSON
$json = Hcl::toJson($hcl, pretty: false);
```

## JSON to HCL

### CLI

```bash
# Output to stdout
php artisan huckle:json2hcl nodes.json

# Output to file
php artisan huckle:json2hcl nodes.json nodes.hcl
```

### Code

```php
use Cline\Hcl\Hcl;

$json = '{"name": "my-app", "port": 8080}';

$hcl = Hcl::fromJson($json);
// name = "my-app"
// port = 8080
```

## Array to HCL

```php
use Cline\Hcl\Hcl;

$data = [
    'name' => 'my-app',
    'settings' => [
        'timeout' => 30,
        'retries' => 3,
    ],
];

$hcl = Hcl::arrayToHcl($data);
```

Output:

```hcl
name = "my-app"
settings = {
  timeout = 30
  retries = 3
}
```

## Block Conversion

Nested structures are intelligently converted to HCL blocks:

```php
$data = [
    'resource' => [
        'aws_instance' => [
            'web' => [
                'ami' => 'ami-12345',
                'instance_type' => 't2.micro',
            ],
        ],
    ],
];

$hcl = Hcl::arrayToHcl($data);
```

Output:

```hcl
resource "aws_instance" "web" {
  ami = "ami-12345"
  instance_type = "t2.micro"
}
```

## Use Cases

### Export for Backup

```bash
# Export current config as JSON backup
php artisan huckle:hcl2json nodes.hcl backup-$(date +%Y%m%d).json
```

### Generate from Code

```php
// Build config programmatically
$config = [
    'partition' => [
        'database' => [
            'environment' => [
                'production' => [
                    'provider' => [
                        'main' => [
                            'host' => $productionHost,
                            'port' => 5432,
                        ],
                    ],
                ],
            ],
        ],
    ],
];

$hcl = Hcl::arrayToHcl($config);
file_put_contents('generated.hcl', $hcl);
```

### API Integration

```php
// Parse HCL config
$config = Hcl::parseFile('app.hcl');

// Send as JSON to API
$response = Http::post('https://api.example.com/config', $config);
```

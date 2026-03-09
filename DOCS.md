## Table of Contents

1. [Getting Started](#doc-cookbooks-getting-started)
2. [Environment Management](#doc-cookbooks-environment-management)
3. [Exports & Syncing](#doc-cookbooks-exports-syncing)
4. [Connection Commands](#doc-cookbooks-connection-commands)
5. [HCL Conversion](#doc-cookbooks-hcl-conversion)
6. [Artisan Commands](#doc-cookbooks-artisan-commands)
7. [Adding Specsuite Tests](#doc-cookbooks-adding-specsuite-tests)
8. [Config Cache Compatibility](#doc-cookbooks-config-cache-compatibility)
9. [Encryption](#doc-cookbooks-encryption)
10. [Nested Hierarchy Credentials](#doc-cookbooks-nested-hierarchy-credentials)
11. [Overview](#doc-docs-readme)
12. [Artisan Commands](#doc-docs-artisan-commands)
13. [Config Cache Compatibility](#doc-docs-config-cache-compatibility)
14. [Connection Commands](#doc-docs-connection-commands)
15. [Encryption](#doc-docs-encryption)
16. [Environment Management](#doc-docs-environment-management)
17. [Exports Syncing](#doc-docs-exports-syncing)
18. [Hcl Conversion](#doc-docs-hcl-conversion)
19. [Nested Hierarchy Credentials](#doc-docs-nested-hierarchy-credentials)
<a id="doc-cookbooks-getting-started"></a>

# Getting Started with Huckle

Create and manage configuration using HCL syntax with environment organization, tagging, and sensitive value protection.

**Use case:** Managing database, API, and service credentials across development, staging, and production environments.

## Creating Your First Configuration File

Create a `.huckle` file in your project root or a secure location:

```hcl
# .huckle

# Default values applied to all nodes
defaults {
  owner      = "platform-team"
  expires_in = "90d"
}

# Database nodes for production
partition "database" {
  environment "production" {
    tags = ["prod", "postgres", "critical"]

    provider "main" {
      host     = "db.prod.internal"
      port     = 5432
      username = "app_user"
      password = sensitive("your-secret-password")
      database = "myapp_production"
      ssl_mode = "require"

      # Metadata
      owner   = "dba-team"
      expires = "2025-12-31"
      notes   = "Primary production database"

      # Environment variable exports
      export {
        DB_HOST     = self.host
        DB_PORT     = self.port
        DB_USERNAME = self.username
        DB_PASSWORD = self.password
        DB_DATABASE = self.database
      }

      # Connection command
      connect "psql" {
        command = "psql -h ${self.host} -p ${self.port} -U ${self.username} -d ${self.database}"
      }
    }
  }
}
```

## Configuration

Update your `config/huckle.php`:

```php
return [
    // Path to your HCL configuration file
    'path' => env('HUCKLE_PATH', base_path('.huckle')),

    // Default days to check for expiring credentials
    'expiring_days' => 30,

    // Auto-export all configuration to env on boot
    'auto_export' => false,
];
```

## Accessing Configuration in Code

```php
use Cline\Huckle\Facades\Huckle;

// Get a specific node by path
$db = Huckle::get('database.production.main');

// Access fields directly
$host = $db->host;           // 'db.prod.internal'
$port = $db->port;           // 5432

// Access sensitive values
$password = $db->password;   // SensitiveValue object
$password->reveal();         // 'your-secret-password'
$password->masked();         // '********'

// Check if node exists
if (Huckle::has('database.production.main')) {
    // ...
}
```

## Listing Nodes

Use the artisan command to see all nodes:

```bash
php artisan huckle:list

# Output:
# +--------------------------------+------------+--------+
# | Path                           | Environment| Tags   |
# +--------------------------------+------------+--------+
# | database.production.main       | production | prod   |
# +--------------------------------+------------+--------+
```

## Showing Node Details

```bash
php artisan huckle:show database.production.main

# Shows all fields, exports, and connection commands
# Sensitive values are masked by default
# Use --reveal to show actual values
```

## Validating Configuration

Check your HCL configuration file for errors:

```bash
php artisan huckle:lint

# Validates:
# - HCL syntax
# - Required fields
# - File permissions
```

## Using Blade Directives

Access nodes in your views:

```blade
@hasHuckle('database.production.main')
    <p>Database: @huckle('database.production.main.host')</p>
@endhasHuckle
```

## Complete Example

```php
use Cline\Huckle\Facades\Huckle;

class DatabaseService
{
    public function getConnectionConfig(): array
    {
        $node = Huckle::get('database.production.main');

        return [
            'driver'   => 'pgsql',
            'host'     => $node->host,
            'port'     => $node->port,
            'database' => $node->database,
            'username' => $node->username,
            'password' => $node->password->reveal(),
            'sslmode'  => $node->ssl_mode,
        ];
    }
}
```

<a id="doc-cookbooks-environment-management"></a>

# Environment Management

Organize nodes by environment (production, staging, development) and filter them for specific use cases.

**Use case:** Managing the same service nodes across multiple deployment environments.

## Organizing by Environment

Structure your nodes with partition and environment blocks:

```hcl
# Production database
partition "database" {
  environment "production" {
    tags = ["prod", "postgres"]

    provider "main" {
      host     = "db.prod.internal"
      port     = 5432
      username = "app_user"
      password = sensitive("prod-secret")
    }
  }

  environment "staging" {
    tags = ["staging", "postgres"]

    provider "main" {
      host     = "db.staging.internal"
      port     = 5432
      username = "app_user"
      password = sensitive("staging-secret")
    }
  }

  environment "development" {
    tags = ["dev", "postgres"]

    provider "main" {
      host     = "localhost"
      port     = 5432
      username = "dev_user"
      password = sensitive("dev-secret")
    }
  }
}
```

## Filtering by Environment

```php
use Cline\Huckle\Facades\Huckle;

// Get all production nodes
$prodNodes = Huckle::inEnvironment('production');

// Get all staging nodes
$stagingNodes = Huckle::inEnvironment('staging');

// Get nodes from multiple environments (OR logic)
$nonProdNodes = Huckle::inEnvironment(['local', 'sandbox', 'staging']);

// Iterate over environment nodes
foreach (Huckle::inEnvironment('production') as $node) {
    echo "{$node->pathString()}: {$node->host}\n";
}
```

## Filtering by Partition

```php
// Get all database nodes (any environment)
$databases = Huckle::inPartition('database');

// Get database nodes for production only
$prodDatabases = Huckle::inPartition('database', 'production');
```

## Filtering by Tags

```php
// Get all nodes tagged 'critical'
$critical = Huckle::tagged('critical');

// Get nodes with multiple tags (AND logic)
$prodPostgres = Huckle::tagged('prod', 'postgres');
```

## Comparing Environments

Use the diff command to compare nodes between environments:

```bash
php artisan huckle:diff production staging

# Output:
# Comparing production vs staging
#
# Only in production:
#   - database.production.readonly
#
# Only in staging:
#   - database.staging.test
#
# Differences:
#   database.*.main:
#     host: db.prod.internal -> db.staging.internal
```

## Dynamic Environment Selection

```php
use Cline\Huckle\Facades\Huckle;
use Cline\Huckle\Parser\Node;

class NodeService
{
    public function getForEnvironment(string $partition, string $name): ?Node
    {
        $env = app()->environment(); // 'production', 'staging', etc.

        return Huckle::get("{$partition}.{$env}.{$name}");
    }

    public function getDatabaseNode(): ?Node
    {
        return $this->getForEnvironment('database', 'main');
    }
}

// Usage
$node = app(NodeService::class)->getDatabaseNode();
```

## Environment-Specific Exports

Export nodes for the current environment:

```bash
# Export only production nodes
php artisan huckle:export --environment=production

# Export only staging nodes
php artisan huckle:export --environment=staging --format=shell
```

## Complete Example: Multi-Environment Setup

```hcl
# nodes.hcl

defaults {
  owner = "platform-team"
}

# AWS nodes per environment
partition "aws" {
  environment "production" {
    tags = ["prod", "aws"]

    provider "deploy" {
      access_key = sensitive("AKIAPRODUCTION...")
      secret_key = sensitive("...")
      region     = "us-east-1"

      export {
        AWS_ACCESS_KEY_ID     = self.access_key
        AWS_SECRET_ACCESS_KEY = self.secret_key
        AWS_DEFAULT_REGION    = self.region
      }
    }
  }

  environment "staging" {
    tags = ["staging", "aws"]

    provider "deploy" {
      access_key = sensitive("AKIASTAGING...")
      secret_key = sensitive("...")
      region     = "us-west-2"

      export {
        AWS_ACCESS_KEY_ID     = self.access_key
        AWS_SECRET_ACCESS_KEY = self.secret_key
        AWS_DEFAULT_REGION    = self.region
      }
    }
  }
}
```

```php
// Automatically export nodes based on environment
$env = app()->environment();
$awsNode = Huckle::get("aws.{$env}.deploy");

if ($awsNode) {
    Huckle::exportToEnv("aws.{$env}.deploy");
}
```

## Partition, Defaults, and Fallback Blocks

For multi-tenant or geographic configurations, use partition blocks, `defaults` blocks, and `fallback` blocks. All block types support multiple keyword aliases - choose whichever term best fits your domain:

**Defaults block aliases** (base configuration inherited by all):
- `defaults` - generic term
- `default` - singular form
- `base` - emphasizes inheritance
- `template` - emphasizes copy-and-customize
- `common` - emphasizes shared config
- `shared` - emphasizes shared config
- `root` - emphasizes top-level base

**Fallback block aliases** (catch-all when no partition matches):
- `fallback` - generic term
- `global` - emphasizes applies everywhere
- `catchall` - explicit catch-all semantics
- `otherwise` - functional programming style
- `wildcard` - pattern matching style

**Partition block aliases** (tenant/division-specific config):
- `partition` - generic term
- `tenant` - for multi-tenant applications
- `namespace` - for namespace-based isolation
- `division` - for business divisions
- `entity` - for legal entities or organizations

Fallback blocks provide default exports that apply when no matching partition is found, or as a base layer that partitions can override.

```hcl
# Fallback provides shared defaults for all partitions
fallback {
  environment "production" {
    provider "service_a" {
      key    = "pk_live_default"
      secret = "sk_live_default"

      export {
        SERVICE_A_KEY    = self.key
        SERVICE_A_SECRET = self.secret
      }
    }

    provider "service_b" {
      api_key = "default-api-key"

      export {
        SERVICE_B_API_KEY = self.api_key
      }
    }
  }

  environment "staging" "local" "sandbox" {
    provider "service_a" {
      key    = "pk_test_default"
      secret = "sk_test_default"

      export {
        SERVICE_A_KEY    = self.key
        SERVICE_A_SECRET = self.secret
      }
    }
  }
}

# Using 'tenant' keyword for multi-tenant app
tenant "FI" {
  environment "production" {
    provider "provider_fi" {
      customer_number = "12345-FI"
      api_key         = "fi-provider-key"

      export {
        PROVIDER_FI_CUSTOMER_NUMBER = self.customer_number
        PROVIDER_FI_API_KEY         = self.api_key
      }
    }

    # Override fallback's shared_service with FI-specific value
    provider "shared_service" {
      api_key = "fi-specific-key"

      export {
        SHARED_SERVICE_API_KEY = self.api_key
      }
    }
  }
}

# Using 'namespace' keyword for namespace-based config
namespace "SE" {
  environment "production" {
    provider "provider_se" {
      customer_number = "67890-SE"
      api_key         = "se-provider-key"

      export {
        PROVIDER_SE_CUSTOMER_NUMBER = self.customer_number
        PROVIDER_SE_API_KEY         = self.api_key
      }
    }
  }
}
```

### Loading Partition Exports

```php
use Cline\Huckle\HuckleManager;

$manager = resolve(HuckleManager::class);

// Load exports for SE partition (gets fallback + SE-specific)
$exports = $manager->loadEnv('path/to/config.hcl', [
    'partition' => 'SE',  // or 'division' for backwards compat
    'environment' => 'production',
]);

// Result includes:
// - SERVICE_A_KEY (from fallback)
// - SERVICE_A_SECRET (from fallback)
// - SERVICE_B_API_KEY (from fallback)
// - PROVIDER_SE_CUSTOMER_NUMBER (from SE partition)
// - PROVIDER_SE_API_KEY (from SE partition)
```

### Fallback Override Semantics

1. **Fallback loads first** - provides base layer of exports
2. **Partition loads second** - adds partition-specific exports
3. **Partition overrides fallback** - if both define the same export key, partition wins

```php
// FI overrides SHARED_SERVICE_API_KEY from fallback
$exports = $manager->loadEnv('path/to/config.hcl', [
    'partition' => 'FI',
    'environment' => 'production',
]);

// SHARED_SERVICE_API_KEY = "fi-specific-key" (from FI, not fallback)
// SERVICE_A_KEY = "pk_live_default" (from fallback, FI didn't override)
```

### Non-Matching Partition Context

When the requested partition doesn't exist, only fallback exports are returned:

```php
// NO partition doesn't exist - only fallback exports returned
$exports = $manager->loadEnv('path/to/config.hcl', [
    'partition' => 'NO',
    'environment' => 'production',
]);

// Only fallback exports: SERVICE_A_KEY, SERVICE_A_SECRET, SERVICE_B_API_KEY
```

<a id="doc-cookbooks-exports-syncing"></a>

# Exports & Syncing

Export node values as environment variables and sync them to your .env file.

**Use case:** Keeping .env files in sync with centralized node management.

## Defining Exports

Define which fields should be exported as environment variables:

```hcl
partition "database" {
  environment "production" {
    provider "main" {
      host     = "db.prod.internal"
      port     = 5432
      username = "app_user"
      password = sensitive("secret123")
      database = "myapp"

      export {
        # Simple field mapping
        DB_HOST     = self.host
        DB_PORT     = self.port
        DB_USERNAME = self.username
        DB_PASSWORD = self.password
        DB_DATABASE = self.database

        # Interpolated values
        DATABASE_URL = "postgres://${self.username}:${self.password}@${self.host}:${self.port}/${self.database}"
      }
    }
  }
}
```

## Exporting to Environment

### In Code

```php
use Cline\Huckle\Facades\Huckle;

// Export a single node
Huckle::exportToEnv('database.production.main');

// Now these are available:
$host = getenv('DB_HOST');        // 'db.prod.internal'
$url = getenv('DATABASE_URL');    // 'postgres://app_user:secret123@...'

// Export all nodes
Huckle::exportAllToEnv();
```

### Via Artisan

```bash
# Export as .env format (default)
php artisan huckle:export

# Output:
# DB_HOST=db.prod.internal
# DB_PORT=5432
# DB_USERNAME=app_user
# DB_PASSWORD=secret123
# DATABASE_URL="postgres://..."

# Export as JSON
php artisan huckle:export --format=json

# Export as shell commands
php artisan huckle:export --format=shell

# Output:
# export DB_HOST='db.prod.internal'
# export DB_PORT='5432'
```

### Filtered Exports

```bash
# Export specific node
php artisan huckle:export --path=database.production.main

# Export by environment
php artisan huckle:export --environment=production

# Export by group
php artisan huckle:export --partition=database

# Export by tag
php artisan huckle:export --tag=critical
```

## Syncing to .env File

### Sync Command

```bash
# Preview changes (dry run)
php artisan huckle:sync --dry-run

# Output:
# Would update .env with 5 variables:
#   DB_HOST: [not set] -> db.prod.internal
#   DB_PORT: 3306 -> 5432
#   DB_USERNAME: old_user -> app_user

# Apply changes
php artisan huckle:sync

# Sync to specific env file
php artisan huckle:sync --env-file=.env.production
```

### Sync Options

```bash
# Sync specific node
php artisan huckle:sync --path=database.production.main

# Sync by environment
php artisan huckle:sync --environment=production

# Sync by group
php artisan huckle:sync --partition=database
```

## Auto-Export on Boot

Enable automatic export in your config:

```php
// config/huckle.php
return [
    'path' => base_path('nodes.hcl'),
    'auto_export' => true,  // Export all on application boot
];
```

## Getting Exports Programmatically

```php
use Cline\Huckle\Facades\Huckle;

// Get exports for a specific node
$exports = Huckle::exports('database.production.main');
// ['DB_HOST' => 'db.prod.internal', 'DB_PORT' => '5432', ...]

// Get all exports from all nodes
$allExports = Huckle::allExports();
```

## Complete Example: CI/CD Integration

```php
// In a deployment script or service provider

use Cline\Huckle\Facades\Huckle;

class DeploymentNodeService
{
    public function exportForEnvironment(string $environment): void
    {
        // Get all nodes for this environment
        $nodes = Huckle::inEnvironment($environment);

        foreach ($nodes as $node) {
            $path = "{$node->path[0]}.{$node->path[1]}.{$node->name}";
            Huckle::exportToEnv($path);
        }
    }

    public function generateEnvFile(string $environment, string $outputPath): void
    {
        $exports = [];

        foreach (Huckle::inEnvironment($environment) as $node) {
            $path = "{$node->path[0]}.{$node->path[1]}.{$node->name}";
            $exports = array_merge($exports, Huckle::exports($path));
        }

        $content = '';
        foreach ($exports as $key => $value) {
            $content .= "{$key}={$this->escapeValue($value)}\n";
        }

        file_put_contents($outputPath, $content);
    }

    private function escapeValue(string $value): string
    {
        if (preg_match('/[\s#"\']/', $value)) {
            return '"' . addslashes($value) . '"';
        }
        return $value;
    }
}
```

```bash
# Generate env file for production deployment
php artisan tinker --execute="app(DeploymentNodeService::class)->generateEnvFile('production', '/tmp/.env.production')"
```

<a id="doc-cookbooks-connection-commands"></a>

# Connection Commands

Define and execute CLI connection commands for nodes like database clients, SSH, and APIs.

**Use case:** Quick access to database shells, SSH connections, and other CLI tools using stored nodes.

## Defining Connections

Add `connect` blocks inside nodes:

```hcl
partition "database" {
  environment "production" {
    provider "main" {
      host     = "db.prod.internal"
      port     = 5432
      username = "app_user"
      password = sensitive("secret123")
      database = "myapp"

      # PostgreSQL connection
      connect "psql" {
        command = "psql -h ${self.host} -p ${self.port} -U ${self.username} -d ${self.database}"
      }

      # pgAdmin connection string
      connect "pgadmin" {
        command = "pgadmin4 --server=${self.host}:${self.port}"
      }
    }
  }
}

partition "server" {
  environment "production" {
    tags = ["prod", "ssh"]

    provider "web" {
      host     = "web.prod.internal"
      user     = "deploy"
      key_path = "~/.ssh/prod_key"
      port     = 22

      connect "ssh" {
        command = "ssh -i ${self.key_path} -p ${self.port} ${self.user}@${self.host}"
      }

      connect "scp" {
        command = "scp -i ${self.key_path} -P ${self.port}"
      }
    }
  }
}
```

## Listing Available Connections

```bash
# List connections for a node
php artisan huckle:connect database.production.main --list

# Output:
# Available connections for database.production.main:
#   psql: psql -h db.prod.internal -p 5432 -U app_user -d myapp
#   pgadmin: pgadmin4 --server=db.prod.internal:5432
```

## Executing Connections

```bash
# Execute a specific connection
php artisan huckle:connect database.production.main psql

# Opens an interactive psql session
```

## Copying Commands to Clipboard

```bash
# Copy command instead of executing
php artisan huckle:connect database.production.main psql --copy

# Output:
# Command copied to clipboard: psql -h db.prod.internal -p 5432 -U app_user -d myapp
```

## Accessing Connections in Code

```php
use Cline\Huckle\Facades\Huckle;

$node = Huckle::get('database.production.main');

// Get all connection names
$names = $node->connectionNames();
// ['psql', 'pgadmin']

// Get a specific connection command
$psqlCommand = $node->connection('psql');
// 'psql -h db.prod.internal -p 5432 -U app_user -d myapp'
```

## Common Connection Examples

### MySQL/MariaDB

```hcl
connect "mysql" {
  command = "mysql -h ${self.host} -P ${self.port} -u ${self.username} -p${self.password} ${self.database}"
}

connect "mycli" {
  command = "mycli -h ${self.host} -P ${self.port} -u ${self.username} -p ${self.password} ${self.database}"
}
```

### Redis

```hcl
connect "redis-cli" {
  command = "redis-cli -h ${self.host} -p ${self.port} -a ${self.password}"
}
```

### MongoDB

```hcl
connect "mongosh" {
  command = "mongosh mongodb://${self.username}:${self.password}@${self.host}:${self.port}/${self.database}"
}
```

### SSH with Jump Host

```hcl
connect "ssh-jump" {
  command = "ssh -J ${self.jump_host} ${self.user}@${self.host}"
}
```

## Complete Example: Database Team Setup

```hcl
# nodes.hcl

partition "database" {
  environment "production" {
    tags = ["prod", "postgres", "critical"]

    provider "primary" {
      host     = "db-primary.prod.internal"
      port     = 5432
      username = "admin"
      password = sensitive("admin-secret")
      database = "myapp"

      connect "psql" {
        command = "psql -h ${self.host} -p ${self.port} -U ${self.username} -d ${self.database}"
      }

      connect "pg_dump" {
        command = "pg_dump -h ${self.host} -p ${self.port} -U ${self.username} -d ${self.database}"
      }
    }

    provider "readonly" {
      host     = "db-replica.prod.internal"
      port     = 5432
      username = "readonly"
      password = sensitive("readonly-secret")
      database = "myapp"

      connect "psql" {
        command = "psql -h ${self.host} -p ${self.port} -U ${self.username} -d ${self.database}"
      }
    }
  }
}
```

```bash
# Connect to primary for admin work
php artisan huckle:connect database.production.primary psql

# Connect to replica for read queries
php artisan huckle:connect database.production.readonly psql

# Dump production database
php artisan huckle:connect database.production.primary pg_dump --copy
# Then paste and redirect: > backup.sql
```

<a id="doc-cookbooks-hcl-conversion"></a>

# HCL Conversion

Convert between HCL and JSON formats for interoperability with other tools.

**Use case:** Integrating with CI/CD pipelines, generating configuration from other sources, or migrating existing JSON configs to HCL.

## HCL to JSON

### Via Artisan

```bash
# Convert to stdout
php artisan huckle:convert:to-json credentials.hcl

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
php artisan huckle:convert:to-json credentials.hcl credentials.json

# Compact output (no pretty printing)
php artisan huckle:convert:to-json credentials.hcl --compact
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
php artisan huckle:convert:to-hcl config.json

# Output:
# name = "my-app"
# version = 1.0
# enabled = true

# Convert to file
php artisan huckle:convert:to-hcl config.json config.hcl
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
php artisan huckle:convert:to-json credentials.hcl /tmp/creds.json

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

<a id="doc-cookbooks-artisan-commands"></a>

# Artisan Commands

All CLI commands available in Huckle for managing nodes.

## Available Commands

| Command | Description |
|---------|-------------|
| `huckle:list` | List all nodes |
| `huckle:show {path}` | Show node details |
| `huckle:export` | Export as environment variables |
| `huckle:sync` | Sync exports to .env file |
| `huckle:lint` | Validate configuration |
| `huckle:connect {path} {connection}` | Execute connection command |
| `huckle:diff {env1} {env2}` | Compare environments |
| `huckle:expiring` | List expiring nodes |
| `huckle:convert:to-json {input} [output]` | Convert HCL to JSON |
| `huckle:convert:to-hcl {input} [output]` | Convert JSON to HCL |

## Command Details

### huckle:list

List all nodes with their paths, environments, and tags:

```bash
php artisan huckle:list

# Filter by single environment
php artisan huckle:list --environment=production

# Filter by multiple environments (OR logic)
php artisan huckle:list --environment=local --environment=sandbox --environment=staging

# Filter by tag
php artisan huckle:list --tag=postgres
```

### huckle:show

Display detailed information about a specific node:

```bash
php artisan huckle:show database.production.main

# Reveal sensitive values
php artisan huckle:show database.production.main --reveal
```

### huckle:export

Export nodes as environment variables:

```bash
# Export specific node
php artisan huckle:export database.production.main

# Export all nodes
php artisan huckle:export --all
```

### huckle:sync

Sync exported values to your .env file:

```bash
php artisan huckle:sync

# Sync to specific file
php artisan huckle:sync --file=.env.production
```

### huckle:lint

Validate your nodes configuration:

```bash
php artisan huckle:lint

# With expiry and rotation checks
php artisan huckle:lint --check-expiry --check-rotation --check-permissions

# Show environment variables table
php artisan huckle:lint --table

# Filter table by partition/environment/provider/country
php artisan huckle:lint --table --partition=FI --environment=production
php artisan huckle:lint --table --provider=stripe --country=SE
```

### huckle:connect

Execute a connection command defined in a node:

```bash
php artisan huckle:connect database.production.main psql
```

### huckle:diff

Compare nodes between environments:

```bash
php artisan huckle:diff production staging
```

### huckle:expiring

List nodes that are expiring soon:

```bash
# Default: 30 days
php artisan huckle:expiring

# Custom threshold
php artisan huckle:expiring --days=7
```

### huckle:convert:to-json

Convert HCL file to JSON:

```bash
# Output to stdout
php artisan huckle:convert:to-json nodes.hcl

# Output to file
php artisan huckle:convert:to-json nodes.hcl nodes.json
```

### huckle:convert:to-hcl

Convert JSON file to HCL:

```bash
# Output to stdout
php artisan huckle:convert:to-hcl nodes.json

# Output to file
php artisan huckle:convert:to-hcl nodes.json nodes.hcl
```

<a id="doc-cookbooks-adding-specsuite-tests"></a>

# Adding Specsuite Tests

Add tests for new HashiCorp HCL specification files when the official spec is updated.

**Use case:** When HashiCorp releases new HCL specification tests, you want to ensure Huckle remains compliant by adding corresponding tests.

## Overview

The HCL specsuite uses two files per test case:
- `.hcl` - The HCL source to parse/validate
- `.t` - The expected outcome (result values or diagnostics)

## Step 1: Copy New Specsuite Files

Copy both `.hcl` and `.t` files from the official HashiCorp specsuite:

```bash
# From the hashicorp/hcl repository
# https://github.com/hashicorp/hcl/tree/main/hclsyntax/testdata

# Copy to appropriate location
cp new_feature.hcl tests/Fixtures/specsuite/expressions/
cp new_feature.t tests/Fixtures/specsuite/expressions/
```

## Step 2: Understand the .t File Format

The `.t` files are HCL themselves and can contain:

### Success Cases (No Errors Expected)

```hcl
# Result value expectation
result = {
  key = "value"
  number = 42
}

# Optional type specification
result_type = object({
  key = string
  number = number
})
```

### Error Cases (Diagnostics Expected)

```hcl
diagnostics {
  error {
    # Comment describing the error
    from {
      line   = 1
      column = 14
      byte   = 13
    }
    to {
      line   = 1
      column = 15
      byte   = 14
    }
  }
}
```

## Step 3: Parse the .t File

Use `TSpec::fromFile()` to read expectations:

```php
use Cline\Huckle\Testing\TSpec;

$tspec = TSpec::fromFile($tFilePath);

// Check what the spec expects
$tspec->expectsSuccess();     // true if no diagnostics
$tspec->expectsErrors();      // true if has error diagnostics
$tspec->expectedErrorCount(); // number of expected errors
$tspec->expectedErrors();     // array of ExpectedDiagnostic
$tspec->result;               // expected result value
$tspec->resultType;           // expected result type
```

## Step 4: Add Validator Tests

For syntax validation tests, add to `tests/Unit/HclValidatorTest.php`:

### Valid Syntax Test

```php
test('accepts new_feature per new_feature.t', function (): void {
    $hcl = file_get_contents(testFixture('specsuite/expressions/new_feature.hcl'));
    $tspec = TSpec::fromFile(testFixture('specsuite/expressions/new_feature.t'));

    $result = $this->validator->validate($hcl);

    expect($tspec->expectsSuccess())->toBeTrue();
    expect($result->isValid())->toBeTrue();
});
```

### Invalid Syntax Test (With Diagnostics)

```php
test('rejects invalid_construct per invalid_construct.t', function (): void {
    $hcl = file_get_contents(testFixture('specsuite/structure/invalid_construct.hcl'));
    $tspec = TSpec::fromFile(testFixture('specsuite/structure/invalid_construct.t'));

    $result = $this->validator->validate($hcl);

    expect($tspec->expectsErrors())->toBeTrue();
    expect($result->hasErrors())->toBeTrue();
    expect($result->errorCount())->toBeGreaterThanOrEqual($tspec->expectedErrorCount());

    // Optionally verify error locations match
    $expectedError = $tspec->expectedErrors()[0];
    $actualError = $result->errors()[0];

    expect($actualError->range->fromLine)->toBe($expectedError->range->fromLine);
    expect($actualError->range->fromColumn)->toBe($expectedError->range->fromColumn);
});
```

## Step 5: Add Parser Tests

For parsing/value tests, add to `tests/Unit/HclComplianceTest.php`:

```php
test('parses new_feature correctly', function (): void {
    $hcl = file_get_contents(testFixture('specsuite/expressions/new_feature.hcl'));
    $tspec = TSpec::fromFile(testFixture('specsuite/expressions/new_feature.t'));

    $result = Hcl::parse($hcl);

    // Compare against expected result from .t file
    expect($result)->toBe($tspec->result);
});
```

## Types of Specsuite Tests

### 1. Syntax Validation Tests
Test that the validator catches syntax errors.

Location: `tests/Unit/HclValidatorTest.php`

Examples:
- Single-line block violations
- Unclosed blocks
- Comma-separated attributes

### 2. Parser Compliance Tests
Test that the parser produces correct values.

Location: `tests/Unit/HclComplianceTest.php`

Examples:
- Primitive literals
- Operators
- Heredocs
- Comments

### 3. Schema Validation Tests
Some `.t` files test schema validation (not syntax). These should pass syntax validation but may fail schema validation.

```php
test('schema_test.t defines schema errors not syntax errors', function (): void {
    $tspec = TSpec::fromFile(testFixture('specsuite/schema_test.t'));

    // The .t expects errors, but they're schema errors
    expect($tspec->expectsErrors())->toBeTrue();

    // Our syntax validator should accept valid HCL syntax
    $hcl = file_get_contents(testFixture('specsuite/schema_test.hcl'));
    $result = $this->validator->validate($hcl);

    // Valid syntax, schema validation is application-specific
    expect($result->isValid())->toBeTrue();
});
```

## Quick Reference

| .t File Contains | Test Type | Test Location |
|------------------|-----------|---------------|
| `result = {...}` | Parser compliance | `HclComplianceTest.php` |
| `result_type = {...}` | Parser compliance | `HclComplianceTest.php` |
| `diagnostics { error {...} }` | Syntax validation | `HclValidatorTest.php` |
| Schema-level diagnostics | Document only | `HclValidatorTest.php` |

## Checklist for New Specsuite Files

- [ ] Copy `.hcl` file to `tests/Fixtures/specsuite/`
- [ ] Copy `.t` file to `tests/Fixtures/specsuite/`
- [ ] Determine test type (syntax vs parser vs schema)
- [ ] Add test using `TSpecParser` to read expectations
- [ ] Verify error locations if diagnostics expected
- [ ] Run `./vendor/bin/pest` to confirm tests pass

<a id="doc-cookbooks-config-cache-compatibility"></a>

# Config Cache Compatibility

Make Huckle work seamlessly with Laravel's `config:cache` command.

**Use case:** Production deployments where `php artisan config:cache` is used for performance optimization.

## The Problem

When you run `php artisan config:cache`, Laravel compiles all configuration files into a single cached file. After caching, `env()` calls return `null` because Laravel no longer reads from environment variables.

Traditional Huckle usage with `exportContextToEnv()` sets `$_ENV`, `$_SERVER`, and `putenv()` — but these are ignored by cached configs.

## The Solution

Huckle provides three mechanisms to write directly to Laravel's config repository, bypassing `env()` entirely:

1. **`config` blocks** — Define config paths directly in your HCL nodes
2. **`mappings` block** — Global env-to-config path mappings in HCL
3. **PHP config mappings** — Define mappings in `config/huckle.php`

## Method 1: Config Blocks (Per-Node)

Define config paths directly alongside your exports:

```hcl
partition "payments" {
  environment "production" {
    provider "stripe" {
      api_key    = sensitive("pk_live_xxx")
      api_secret = sensitive("sk_live_xxx")

      # Traditional env exports (still useful for non-cached scenarios)
      export {
        STRIPE_KEY    = self.api_key
        STRIPE_SECRET = self.api_secret
      }

      # Direct Laravel config paths
      config {
        "cashier.key"         = self.api_key
        "cashier.secret"      = self.api_secret
        "services.stripe.key" = self.api_key
      }
    }
  }
}
```

## Method 2: Global Mappings Block

Define a top-level `mappings` block to map environment variable names to config paths:

```hcl
# Global mappings apply to all exports with matching keys
mappings {
  STRIPE_KEY        = "cashier.key"
  STRIPE_SECRET     = "cashier.secret"
  REDIS_HOST        = "database.redis.default.host"
  REDIS_PASSWORD    = "database.redis.default.password"
  DB_HOST           = "database.connections.pgsql.host"
  DB_PASSWORD       = "database.connections.pgsql.password"
}

partition "payments" {
  environment "production" {
    provider "stripe" {
      api_key = sensitive("pk_live_xxx")

      export {
        STRIPE_KEY = self.api_key  # Will also write to cashier.key via mapping
      }
    }
  }
}
```

## Method 3: PHP Config Mappings

Define mappings in your `config/huckle.php`:

```php
return [
    'path' => base_path('config/huckle.hcl'),

    'mappings' => [
        'STRIPE_KEY'    => 'cashier.key',
        'STRIPE_SECRET' => 'cashier.secret',
        'REDIS_HOST'    => 'database.redis.default.host',
        'DB_HOST'       => 'database.connections.pgsql.host',
    ],
];
```

## Precedence

When multiple mapping sources exist, they're applied in this order (later overrides earlier):

1. PHP config mappings (`config/huckle.php`)
2. HCL `mappings` block
3. Node `config` blocks (highest precedence)

## Usage in Code

### Standard Usage (env + config)

```php
use Cline\Huckle\Facades\Huckle;

// Writes to both env AND Config
Huckle::exportContextToEnv([
    'partition' => 'payments',
    'environment' => 'production',
]);

// Both work:
$key = getenv('STRIPE_KEY');      // 'pk_live_xxx'
$key = config('cashier.key');      // 'pk_live_xxx'
```

### Config-Only Usage (for config:cache)

```php
use Cline\Huckle\Facades\Huckle;

// Writes ONLY to Config (no env)
Huckle::exportContextToConfig([
    'partition' => 'payments',
    'environment' => 'production',
]);

// Only config works (env is not set):
$key = config('cashier.key');  // 'pk_live_xxx'
```

## Production Setup

### Service Provider Integration

Create a service provider that loads Huckle config early in the boot process:

```php
<?php

namespace App\Providers;

use Cline\Huckle\Facades\Huckle;
use Illuminate\Support\ServiceProvider;

class HuckleServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Determine context from your application
        $context = [
            'partition' => config('app.tenant', 'default'),
            'environment' => app()->environment(),
        ];

        // Use exportContextToConfig for config:cache compatibility
        Huckle::exportContextToConfig($context);
    }
}
```

Register it early in `config/app.php`:

```php
'providers' => [
    // Load before other providers that need the config values
    App\Providers\HuckleServiceProvider::class,

    // Other providers...
],
```

### Multi-Tenant Example

```php
<?php

namespace App\Http\Middleware;

use Cline\Huckle\Facades\Huckle;
use Closure;
use Illuminate\Http\Request;

class ApplyTenantConfig
{
    public function handle(Request $request, Closure $next)
    {
        $tenant = $request->route('tenant') ?? session('tenant');

        if ($tenant) {
            Huckle::exportContextToConfig([
                'partition' => $tenant,
                'environment' => app()->environment(),
            ]);
        }

        return $next($request);
    }
}
```

## Complete Example

```hcl
# config/huckle.hcl

# Global mappings for common env vars
mappings {
  DB_HOST       = "database.connections.pgsql.host"
  DB_PORT       = "database.connections.pgsql.port"
  DB_DATABASE   = "database.connections.pgsql.database"
  DB_USERNAME   = "database.connections.pgsql.username"
  DB_PASSWORD   = "database.connections.pgsql.password"
  REDIS_HOST    = "database.redis.default.host"
  STRIPE_KEY    = "cashier.key"
  STRIPE_SECRET = "cashier.secret"
}

partition "US" {
  environment "production" {
    provider "database" {
      host     = "db-us.prod.internal"
      port     = 5432
      database = "app_us"
      username = "app_user"
      password = sensitive("prod_password")

      export {
        DB_HOST     = self.host
        DB_PORT     = self.port
        DB_DATABASE = self.database
        DB_USERNAME = self.username
        DB_PASSWORD = self.password
      }
    }

    provider "stripe" {
      api_key    = sensitive("pk_live_us_xxx")
      api_secret = sensitive("sk_live_us_xxx")

      export {
        STRIPE_KEY    = self.api_key
        STRIPE_SECRET = self.api_secret
      }

      # Additional config paths not in global mappings
      config {
        "services.stripe.webhook_secret" = sensitive("whsec_us_xxx")
      }
    }
  }
}

partition "EU" {
  environment "production" {
    provider "database" {
      host     = "db-eu.prod.internal"
      port     = 5432
      database = "app_eu"
      username = "app_user"
      password = sensitive("eu_prod_password")

      export {
        DB_HOST     = self.host
        DB_PORT     = self.port
        DB_DATABASE = self.database
        DB_USERNAME = self.username
        DB_PASSWORD = self.password
      }
    }

    provider "stripe" {
      api_key    = sensitive("pk_live_eu_xxx")
      api_secret = sensitive("sk_live_eu_xxx")

      export {
        STRIPE_KEY    = self.api_key
        STRIPE_SECRET = self.api_secret
      }
    }
  }
}
```

```php
// app/Providers/HuckleServiceProvider.php

public function boot(): void
{
    $region = config('app.region', 'US');

    Huckle::exportContextToConfig([
        'partition' => $region,
        'environment' => app()->environment(),
    ]);

    // Now these work even with config:cache:
    // config('database.connections.pgsql.host') => 'db-us.prod.internal'
    // config('cashier.key') => 'pk_live_us_xxx'
}
```

## Verifying It Works

```bash
# Cache your config
php artisan config:cache

# Verify values are accessible
php artisan tinker --execute="dump(config('cashier.key'))"
# Should output your Stripe key, not null

# Clear cache for development
php artisan config:clear
```

## API Reference

### Facade Methods

```php
// Export to both env and Config (applies mappings + config blocks)
Huckle::exportContextToEnv(array $context): HuckleManager

// Export only to Config (for config:cache compatibility)
Huckle::exportContextToConfig(array $context): HuckleManager

// Get config mappings for a context
Huckle::configsForContext(array $context): array

// Get all mappings (HCL + PHP config merged)
Huckle::mappings(): array
```

### HuckleConfig Methods

```php
$config = Huckle::config();

// Get global mappings from HCL
$config->mappings(): array

// Get specific mapping
$config->mapping('STRIPE_KEY'): ?string

// Check if mapping exists
$config->hasMapping('STRIPE_KEY'): bool

// Get configs for a node path
$config->configs('payments.production.stripe'): array

// Get all configs matching context
$config->configsForContext(['partition' => 'US']): array
```

### Node Methods

```php
$node = Huckle::get('payments.production.stripe');

// Get resolved config mappings
$node->config(): array  // ['cashier.key' => 'pk_live_xxx', ...]

// Access raw configs property
$node->configs  // ['cashier.key' => 'self.api_key', ...] (unresolved)
```

<a id="doc-cookbooks-encryption"></a>

# Configuration Encryption

Encrypt and decrypt HCL configuration files for secure storage and deployment using Laravel's Encrypter with AES-256-CBC.

**Use cases:** Encrypting sensitive configs at rest, storing encrypted configs in version control, and decrypting during deployment.

## Basic Encryption

```php
use Cline\Huckle\Facades\Huckle;

// Encrypt a configuration file (generates a new key)
$result = Huckle::encrypt('/path/to/credentials.hcl');

// $result contains:
// [
//     'path' => '/path/to/credentials.hcl.encrypted',  // The encrypted file
//     'key'  => 'base64:ABC123...',                    // Store this securely!
// ]
```

> **Important:** Save the key securely (environment variable, secret manager, etc.). You'll need it to decrypt the file later.

## Decryption

```php
// Decrypt using the key from encryption
$decryptedPath = Huckle::decrypt(
    '/path/to/credentials.hcl.encrypted',
    'base64:ABC123...',  // The key from encrypt()
);

// Returns: '/path/to/credentials.hcl' (original filename without .encrypted)
```

## Using a Custom Key

```php
// Generate your own key (must be valid for AES-256-CBC = 32 bytes)
$myKey = 'base64:' . base64_encode(random_bytes(32));

// Encrypt with your key
$result = Huckle::encrypt('/path/to/config.hcl', $myKey);

// Decrypt with the same key
Huckle::decrypt('/path/to/config.hcl.encrypted', $myKey);
```

## Force Overwrite

```php
// By default, decrypt() throws if the target file already exists
// Use force: true to overwrite
$decryptedPath = Huckle::decrypt(
    '/path/to/credentials.hcl.encrypted',
    $key,
    force: true,
);
```

## Custom Cipher

```php
// Use a different cipher (default is AES-256-CBC)
$result = Huckle::encrypt(
    '/path/to/config.hcl',
    cipher: 'AES-128-CBC',  // 16-byte key
);

// Decrypt with matching cipher
Huckle::decrypt(
    '/path/to/config.hcl.encrypted',
    $result['key'],
    cipher: 'AES-128-CBC',
);
```

## CLI Commands

```bash
# Encrypt a file (generates and displays key)
php artisan huckle:config:encrypt config/credentials.hcl

# Encrypt with a specific key
php artisan huckle:config:encrypt config/credentials.hcl --key="base64:ABC123..."

# Encrypt using APP_KEY
php artisan huckle:config:encrypt config/credentials.hcl --app-key

# Encrypt and delete original
php artisan huckle:config:encrypt config/credentials.hcl --prune

# Decrypt a file
php artisan huckle:config:decrypt config/credentials.hcl.encrypted --key="base64:ABC123..."

# Decrypt using APP_KEY
php artisan huckle:config:decrypt config/credentials.hcl.encrypted --app-key

# Decrypt with force overwrite
php artisan huckle:config:decrypt config/credentials.hcl.encrypted --key="..." --force

# Decrypt to custom path
php artisan huckle:config:decrypt config/credentials.hcl.encrypted --key="..." --path=/var/www/config

# Decrypt with custom filename
php artisan huckle:config:decrypt config/credentials.hcl.encrypted --key="..." --filename=decrypted.hcl

# Use custom cipher
php artisan huckle:config:encrypt config/credentials.hcl --cipher=AES-128-CBC
php artisan huckle:config:decrypt config/credentials.hcl.encrypted --key="..." --cipher=AES-128-CBC

# Environment-specific encryption (suffix style)
php artisan huckle:config:encrypt config/credentials.hcl --environment=production
php artisan huckle:config:decrypt config/credentials.hcl --key="..." --environment=production

# Environment-specific encryption (directory style)
php artisan huckle:config:encrypt config/credentials.hcl --environment=production --env-style=directory
php artisan huckle:config:decrypt config/credentials.hcl --key="..." --environment=production --env-style=directory
```

## Environment-Specific Files: Suffix Style (Default)

Suffix style transforms `config.hcl` → `config.production.hcl`. This matches Laravel's `.env` pattern.

```php
$result = Huckle::encrypt(
    '/path/to/config.hcl',
    env: 'production',  // Encrypts /path/to/config.production.hcl
);

// Decrypts /path/to/config.production.hcl.encrypted
Huckle::decrypt(
    '/path/to/config.hcl',
    $result['key'],
    env: 'production',
);
```

## Environment-Specific Files: Directory Style

Directory style transforms `config/credentials/db.hcl` → `config/credentials/production/db.hcl`. Perfect for organizing configs in environment subdirectories.

```php
$result = Huckle::encrypt(
    '/path/to/config/credentials/db.hcl',
    env: 'production',
    envStyle: 'directory',
);

Huckle::decrypt(
    '/path/to/config/credentials/db.hcl',
    $result['key'],
    env: 'production',
    envStyle: 'directory',
);
```

## Prune Option

Delete the source file after the operation completes.

```php
// Delete the original file after encryption
$result = Huckle::encrypt('/path/to/credentials.hcl', prune: true);
// /path/to/credentials.hcl is deleted, only .encrypted remains

// Delete the encrypted file after decryption
Huckle::decrypt('/path/to/credentials.hcl.encrypted', $key, prune: true);
// .encrypted file is deleted, only decrypted file remains
```

## Custom Output Location

```php
// Output to a different directory
$decryptedPath = Huckle::decrypt(
    '/path/to/credentials.hcl.encrypted',
    $key,
    path: '/var/www/app/config',
);
// Returns: /var/www/app/config/credentials.hcl

// Use a custom filename
$decryptedPath = Huckle::decrypt(
    '/path/to/credentials.hcl.encrypted',
    $key,
    filename: 'decrypted-credentials.hcl',
);
// Returns: /path/to/decrypted-credentials.hcl

// Combine path and filename
$decryptedPath = Huckle::decrypt(
    '/path/to/credentials.hcl.encrypted',
    $key,
    path: '/var/www/app/config',
    filename: 'app-credentials.hcl',
);
// Returns: /var/www/app/config/app-credentials.hcl
```

## Directory Encryption

Encrypt all files in a directory with a single key. Perfect for encrypting entire config directories like `.huckle/`.

```php
use Cline\Huckle\Facades\Huckle;

// Encrypt all files in a directory
$result = Huckle::encryptDirectory('/path/to/.huckle');

// $result contains:
// [
//     'files' => [
//         ['path' => '/path/to/.huckle/config.hcl.encrypted', 'key' => '...'],
//         ['path' => '/path/to/.huckle/secrets.hcl.encrypted', 'key' => '...'],
//     ],
//     'key' => 'base64:ABC123...',  // Same key for all files
// ]
```

### Recursive Directory Encryption

```php
// Encrypt files in subdirectories too
$result = Huckle::encryptDirectory(
    '/path/to/.huckle',
    recursive: true,
);
```

### Filter Files with Glob Pattern

```php
// Only encrypt HCL files
$result = Huckle::encryptDirectory(
    '/path/to/.huckle',
    glob: '*.hcl',
);

// Encrypt HCL files recursively
$result = Huckle::encryptDirectory(
    '/path/to/.huckle',
    recursive: true,
    glob: '*.hcl',
);
```

### Directory Decryption

```php
// Decrypt all .encrypted files in directory
$decryptedPaths = Huckle::decryptDirectory(
    '/path/to/.huckle',
    'base64:ABC123...',  // Key from encryptDirectory()
);

// Returns array of decrypted file paths:
// ['/path/to/.huckle/config.hcl', '/path/to/.huckle/secrets.hcl']

// Recursive decryption
$decryptedPaths = Huckle::decryptDirectory(
    '/path/to/.huckle',
    $key,
    recursive: true,
);
```

### Prune and Force Options

```php
// Delete originals after encryption
$result = Huckle::encryptDirectory(
    '/path/to/.huckle',
    prune: true,
);

// Overwrite existing encrypted files
$result = Huckle::encryptDirectory(
    '/path/to/.huckle',
    force: true,
);

// Delete encrypted files after decryption, overwrite existing
$decryptedPaths = Huckle::decryptDirectory(
    '/path/to/.huckle',
    $key,
    prune: true,
    force: true,
);
```

### CLI Commands for Directories

```bash
# Encrypt directory
php artisan huckle:config:encrypt .huckle

# Encrypt recursively with glob filter
php artisan huckle:config:encrypt .huckle --recursive --glob='*.hcl'

# Delete originals after encryption
php artisan huckle:config:encrypt .huckle --recursive --prune

# Decrypt directory
php artisan huckle:config:decrypt .huckle --key="base64:ABC123..."

# Decrypt recursively, keep encrypted files
php artisan huckle:config:decrypt .huckle --key="..." --recursive --keep
```

## Complete Example: Secure Deployment Workflow

```php
/**
 * Encrypt sensitive configs before committing to version control.
 * Run this locally before pushing code.
 */
function encryptForDeployment(): void
{
    $sensitiveFiles = [
        base_path('config/huckle.hcl'),
        base_path('config/credentials.hcl'),
        base_path('config/api-keys.hcl'),
    ];

    $deployKey = env('CONFIG_ENCRYPTION_KEY');

    foreach ($sensitiveFiles as $filepath) {
        if (file_exists($filepath)) {
            Huckle::encrypt($filepath, $deployKey);
            unlink($filepath);  // Delete unencrypted version
            echo "Encrypted: {$filepath}\n";
        }
    }
}

/**
 * Decrypt sensitive configs during deployment.
 * Run this on the server after pulling code.
 */
function decryptForRuntime(): void
{
    $encryptedFiles = glob(base_path('config/*.encrypted')) ?: [];
    $deployKey = env('CONFIG_ENCRYPTION_KEY');

    foreach ($encryptedFiles as $encryptedPath) {
        Huckle::decrypt($encryptedPath, $deployKey, force: true);
        unlink($encryptedPath);  // Delete encrypted version
        echo "Decrypted: {$encryptedPath}\n";
    }
}
```

## Complete Example: Per-Environment Keys

```php
/**
 * Each environment has its own encryption key.
 * Encrypt once per environment, store encrypted files in version control.
 */
function encryptForEnvironment(string $environment): void
{
    $keyEnvVar = mb_strtoupper($environment) . '_CONFIG_KEY';
    $key = env($keyEnvVar);

    if ($key === null) {
        throw new RuntimeException("Missing encryption key: {$keyEnvVar}");
    }

    $configPath = base_path("config/credentials.{$environment}.hcl");
    $result = Huckle::encrypt($configPath, $key);

    echo "Encrypted {$configPath} -> {$result['path']}\n";
}

// Usage:
// PRODUCTION_CONFIG_KEY=base64:xxx encryptForEnvironment('production');
// STAGING_CONFIG_KEY=base64:yyy encryptForEnvironment('staging');
```

## Complete Example: Key Rotation

```php
function rotateEncryptionKey(string $filepath, string $oldKey, string $newKey): void
{
    // Decrypt with old key
    $decryptedPath = Huckle::decrypt($filepath, $oldKey, force: true);

    // Re-encrypt with new key
    Huckle::encrypt($decryptedPath, $newKey);

    // Clean up unencrypted file
    unlink($decryptedPath);
}
```

## Complete Example: Directory-Style Environment Workflow

```php
/**
 * Encrypt credential configs for deployment.
 * Structure: config/credentials/{env}/database.hcl
 */
function encryptCredentialConfigs(string $environment): void
{
    $key = env(mb_strtoupper($environment) . '_CONFIG_KEY');
    $types = ['database', 'cache', 'queue'];

    foreach ($types as $type) {
        $basePath = base_path("config/credentials/{$type}.hcl");

        Huckle::encrypt(
            $basePath,
            $key,
            env: $environment,
            envStyle: 'directory',
            prune: true,
        );
    }
}

/**
 * Decrypt credential configs during deployment.
 */
function decryptCredentialConfigs(string $environment): void
{
    $key = env(mb_strtoupper($environment) . '_CONFIG_KEY');
    $types = ['database', 'cache', 'queue'];

    foreach ($types as $type) {
        $basePath = base_path("config/credentials/{$type}.hcl");

        Huckle::decrypt(
            $basePath,
            $key,
            env: $environment,
            envStyle: 'directory',
            prune: true,
            force: true,
        );
    }
}
```

<a id="doc-cookbooks-nested-hierarchy-credentials"></a>

# Nested Hierarchy Nodes

Load nodes dynamically based on context (division, environment, provider, country) using hierarchical HCL blocks.

**Use case:** Managing service provider nodes across multiple divisions, environments, and region-specific configurations.

## The Problem

You have environment variables with suffixes like:
- `SERVICE_A_API_KEY_FI`, `SERVICE_A_API_KEY_SE`, `SERVICE_A_API_KEY_EE`
- `SERVICE_B_CUSTOMER_NUMBER_EE`, `SERVICE_B_CUSTOMER_NUMBER_LV`, `SERVICE_B_CUSTOMER_NUMBER_LT`

This becomes unwieldy with multiple divisions, environments, and providers.

## The Solution

Use nested hierarchy blocks with `Huckle::loadEnv()` to load context-specific nodes:

```php
use Cline\Huckle\Facades\Huckle;

// Load nodes for FI division, production environment, provider_a, region EE
Huckle::loadEnv('nodes.hcl', [
    'division' => 'FI',
    'environment' => 'production',
    'provider' => 'provider_a',
    'country' => 'EE',
]);

// Now access standard env vars without suffixes
$username = env('PROVIDER_A_USERNAME');         // 'provider-a-fi-prod-user'
$customerNumber = env('PROVIDER_A_CUSTOMER_NUMBER'); // 'provider-a-ee-customer'
```

## Hierarchy Structure

```
division > environment > provider > country > service
```

Additional block types supported for organizational hierarchies:
```
organization > team > user
```

Each level can have fields, exports, and nested children. Exports accumulate from parent to child, with deeper levels overriding same-named keys.

## Creating the Nodes File

```hcl
# nodes.hcl

division "FI" {
  environment "production" {
    provider "provider_a" {
      username = "provider-a-fi-prod-user"
      password = sensitive("provider-a-fi-prod-pass")

      export {
        PROVIDER_A_USERNAME = self.username
        PROVIDER_A_PASSWORD = self.password
      }

      country "EE" {
        customer_number = "provider-a-ee-customer"

        export {
          PROVIDER_A_CUSTOMER_NUMBER = self.customer_number
        }
      }

      country "LV" {
        customer_number = "provider-a-lv-customer"

        export {
          PROVIDER_A_CUSTOMER_NUMBER = self.customer_number
        }
      }

      country "LT" {
        customer_number = "provider-a-lt-customer"

        export {
          PROVIDER_A_CUSTOMER_NUMBER = self.customer_number
        }
      }
    }

    provider "provider_b" {
      api_key = "provider-b-fi-prod-key"
      customer_number = "provider-b-fi-prod-customer"

      export {
        PROVIDER_B_API_KEY = self.api_key
        PROVIDER_B_CUSTOMER_NUMBER = self.customer_number
      }
    }

    provider "provider_c" {
      username = "provider-c-global-user"
      password = sensitive("provider-c-global-pass")

      export {
        PROVIDER_C_USERNAME = self.username
        PROVIDER_C_PASSWORD = self.password
      }

      country "EE" {
        bearer_token = "provider-c-ee-token"
        base_url = "https://api.example.com/ee"

        export {
          PROVIDER_C_BEARER_TOKEN = self.bearer_token
          PROVIDER_C_BASE_URL = self.base_url
        }
      }

      country "LT" {
        bearer_token = "provider-c-lt-token"
        base_url = "https://api.example.com/lt"

        export {
          PROVIDER_C_BEARER_TOKEN = self.bearer_token
          PROVIDER_C_BASE_URL = self.base_url
        }
      }
    }
  }

  environment "staging" {
    provider "provider_a" {
      username = "provider-a-fi-staging-user"
      password = sensitive("provider-a-fi-staging-pass")

      export {
        PROVIDER_A_USERNAME = self.username
        PROVIDER_A_PASSWORD = self.password
      }
    }

    provider "provider_b" {
      api_key = "provider-b-fi-staging-key"
      customer_number = "provider-b-fi-staging-customer"

      export {
        PROVIDER_B_API_KEY = self.api_key
        PROVIDER_B_CUSTOMER_NUMBER = self.customer_number
      }
    }
  }
}

division "SE" {
  environment "production" {
    provider "provider_b" {
      api_key = "provider-b-se-prod-key"
      customer_number = "provider-b-se-prod-customer"

      export {
        PROVIDER_B_API_KEY = self.api_key
        PROVIDER_B_CUSTOMER_NUMBER = self.customer_number
      }
    }

    provider "provider_d" {
      api_uid = "provider-d-se-prod-uid"
      api_key = "provider-d-se-prod-key"
      customer_number = "provider-d-se-prod-customer"

      export {
        PROVIDER_D_API_UID = self.api_uid
        PROVIDER_D_API_KEY = self.api_key
        PROVIDER_D_CUSTOMER_NUMBER = self.customer_number
      }
    }
  }
}
```

## Loading Context-Specific Nodes

### Provider-Level Only

```php
// Load provider_b nodes for FI production (no country-specific config needed)
Huckle::loadEnv('nodes.hcl', [
    'division' => 'FI',
    'environment' => 'production',
    'provider' => 'provider_b',
]);

// Sets:
// PROVIDER_B_API_KEY = 'provider-b-fi-prod-key'
// PROVIDER_B_CUSTOMER_NUMBER = 'provider-b-fi-prod-customer'
```

### With Country Context

```php
// Load provider_a nodes for FI production, region EE
$exports = Huckle::loadEnv('nodes.hcl', [
    'division' => 'FI',
    'environment' => 'production',
    'provider' => 'provider_a',
    'country' => 'EE',
]);

// Sets and returns:
// [
//     'PROVIDER_A_USERNAME' => 'provider-a-fi-prod-user',
//     'PROVIDER_A_PASSWORD' => 'provider-a-fi-prod-pass',
//     'PROVIDER_A_CUSTOMER_NUMBER' => 'provider-a-ee-customer',
// ]
```

### Switching Countries

```php
// Region LV
Huckle::loadEnv('nodes.hcl', [
    'division' => 'FI',
    'environment' => 'production',
    'provider' => 'provider_a',
    'country' => 'LV',
]);
// PROVIDER_A_CUSTOMER_NUMBER = 'provider-a-lv-customer'

// Region LT
Huckle::loadEnv('nodes.hcl', [
    'division' => 'FI',
    'environment' => 'production',
    'provider' => 'provider_a',
    'country' => 'LT',
]);
// PROVIDER_A_CUSTOMER_NUMBER = 'provider-a-lt-customer'
```

## Export Accumulation

Exports accumulate from parent levels to children. Deeper levels override same-named keys:

```php
Huckle::loadEnv('nodes.hcl', [
    'division' => 'FI',
    'environment' => 'production',
    'provider' => 'provider_c',
    'country' => 'EE',
]);

// Accumulates from provider + country:
// PROVIDER_C_USERNAME = 'provider-c-global-user'    (from provider)
// PROVIDER_C_PASSWORD = 'provider-c-global-pass'    (from provider)
// PROVIDER_C_BEARER_TOKEN = 'provider-c-ee-token'   (from country)
// PROVIDER_C_BASE_URL = 'https://api.example.com/ee'(from country)
```

## Using in Laravel Service Provider

```php
// AppServiceProvider.php
public function boot(): void
{
    // Load nodes based on application context
    $division = config('app.division', 'FI');
    $environment = config('app.env') === 'production' ? 'production' : 'staging';

    Huckle::loadEnv(base_path('nodes.hcl'), [
        'division' => $division,
        'environment' => $environment,
    ]);
}
```

## Using with a Service

```php
class ExternalApiService
{
    public function createRequest(string $targetRegion): void
    {
        // Load nodes for the target region
        Huckle::loadEnv(base_path('nodes.hcl'), [
            'division' => config('app.division'),
            'environment' => app()->environment(),
            'provider' => 'provider_a',
            'country' => $targetRegion,
        ]);

        // Now use standard env vars
        $client = new ApiClient(
            username: env('PROVIDER_A_USERNAME'),
            password: env('PROVIDER_A_PASSWORD'),
            customerNumber: env('PROVIDER_A_CUSTOMER_NUMBER'),
        );

        // Make request...
    }
}
```

## Querying Without Loading

Use `exportsForContext()` to get exports without setting environment variables:

```php
use Cline\Huckle\Facades\Huckle;

$parser = new \Cline\Huckle\Parser\HuckleParser();
$config = $parser->parseFile(base_path('nodes.hcl'));

$exports = $config->exportsForContext([
    'division' => 'SE',
    'environment' => 'production',
    'provider' => 'provider_d',
]);

// $exports = [
//     'PROVIDER_D_API_UID' => 'provider-d-se-prod-uid',
//     'PROVIDER_D_API_KEY' => 'provider-d-se-prod-key',
//     'PROVIDER_D_CUSTOMER_NUMBER' => 'provider-d-se-prod-customer',
// ]
```

## Context Matching Rules

- If context has `division`, only matching division is processed
- If context has `environment`, only matching environment is processed
- If context has `provider`, only matching provider is processed
- If context has `country`, only matching country is processed
- Missing context keys match all blocks at that level

```php
// No provider specified = exports from ALL providers in FI/production
Huckle::loadEnv('nodes.hcl', [
    'division' => 'FI',
    'environment' => 'production',
]);

// Non-existent division = empty exports
Huckle::loadEnv('nodes.hcl', [
    'division' => 'NONEXISTENT',
]);
// Returns []
```

## Supported Block Types

The following block types are supported for building hierarchies:

**Organizational:**
- `division` - Top-level organizational unit
- `organization` - Alternative top-level for org structures
- `team` - Team-level grouping
- `user` - User-level configuration
- `tenant` - Multi-tenant SaaS scenarios
- `client` - Per-client/customer nodes

**Geographic (with validation):**
- `continent` - Continental grouping, validated values: `europe`, `asia`, `africa`, `north_america`, `south_america`, `oceania`, `antarctica`
- `zone` - Trading/economic zone, validated values: `eu`, `eea`, `efta`, `schengen`, `eurozone`, `usmca`, `mercosur`, `caricom`, `asean`, `rcep`, `cptpp`, `gcc`, `saarc`, `afcfta`, `ecowas`, `sadc`, `comesa`, `eac`, `au`
- `country` - Country-specific configuration, validated against ISO 3166-1 (alpha-2: `FI`, `SE` or alpha-3: `FIN`, `SWE`)
- `state` - State/province-level configuration, validated against ISO 3166-2 (full: `US-CA` or short: `CA` when nested in country block)

**Geographic (no validation):**
- `region` - Regional grouping (e.g., "nordic", "baltic", "apac") - flexible, no validation

**Infrastructure:**
- `environment` - Environment (production, staging, etc.)
- `provider` - Service provider or carrier
- `service` - Service type (express, economy, etc.)
- `carrier` - Carrier-specific configuration

## Geographic Validation

Geographic blocks (`continent`, `zone`, `country`, `state`) are validated by default. Invalid values throw a `ValidationException`:

```php
// This will throw ValidationException: Invalid country code 'INVALID'
$parser = new HuckleParser();
$config = $parser->parseFile('nodes.hcl'); // with country "INVALID" block
```

### Disabling Validation

To disable geographic validation (e.g., for testing or custom values):

```php
use Cline\Huckle\Parser\HuckleParser;

$parser = new HuckleParser();
$config = $parser->withoutGeoValidation()->parseFile('nodes.hcl');

// Or use the explicit method:
$config = $parser->withGeoValidation(false)->parseFile('nodes.hcl');
```

### Validation-Only Mode

To validate without building the full config:

```php
$parser = new HuckleParser();
$result = $parser->validateFile('nodes.hcl');

if (!$result['valid']) {
    foreach ($result['errors'] as $error) {
        echo $error . PHP_EOL;
    }
}
```

<a id="doc-docs-readme"></a>

Create and manage nodes using HCL syntax with environment organization, tagging, and sensitive value protection.

**Use case:** Managing database, API, and service nodes across development, staging, and production environments.

## Creating Your First Nodes File

Create a `nodes.hcl` file in your project root or a secure location:

```hcl
# nodes.hcl

# Default values applied to all nodes
defaults {
  owner      = "platform-team"
  expires_in = "90d"
}

# Database nodes for production
partition "database" {
  environment "production" {
    tags = ["prod", "postgres", "critical"]

    provider "main" {
      host     = "db.prod.internal"
      port     = 5432
      username = "app_user"
      password = sensitive("your-secret-password")
      database = "myapp_production"
      ssl_mode = "require"

      # Metadata
      owner   = "dba-team"
      expires = "2025-12-31"
      notes   = "Primary production database"

      # Environment variable exports
      export {
        DB_HOST     = self.host
        DB_PORT     = self.port
        DB_USERNAME = self.username
        DB_PASSWORD = self.password
        DB_DATABASE = self.database
      }

      # Connection command
      connect "psql" {
        command = "psql -h ${self.host} -p ${self.port} -U ${self.username} -d ${self.database}"
      }
    }
  }
}
```

## Configuration

Update your `config/huckle.php`:

```php
return [
    // Path to your nodes file
    'path' => env('HUCKLE_PATH', base_path('nodes.hcl')),

    // Default days to check for expiring nodes
    'expiring_days' => 30,

    // Auto-export all nodes to env on boot
    'auto_export' => false,
];
```

## Accessing Nodes in Code

```php
use Cline\Huckle\Facades\Huckle;

// Get a specific node by path
$db = Huckle::get('database.production.main');

// Access fields directly
$host = $db->host;           // 'db.prod.internal'
$port = $db->port;           // 5432

// Access sensitive values
$password = $db->password;   // SensitiveValue object
$password->reveal();         // 'your-secret-password'
$password->masked();         // '********'

// Check if node exists
if (Huckle::has('database.production.main')) {
    // ...
}
```

## Listing Nodes

Use the artisan command to see all nodes:

```bash
php artisan huckle:list

# Output:
# +--------------------------------+------------+--------+
# | Path                           | Environment| Tags   |
# +--------------------------------+------------+--------+
# | database.production.main       | production | prod   |
# +--------------------------------+------------+--------+
```

## Showing Node Details

```bash
php artisan huckle:show database.production.main

# Shows all fields, exports, and connection commands
# Sensitive values are masked by default
# Use --reveal to show actual values
```

## Validating Configuration

Check your nodes file for errors:

```bash
php artisan huckle:lint

# Validates:
# - HCL syntax
# - Required fields
# - File permissions
```

## Using Blade Directives

Access nodes in your views:

```blade
@hasHuckle('database.production.main')
    <p>Database: @huckle('database.production.main.host')</p>
@endhasHuckle
```

## Complete Example

```php
use Cline\Huckle\Facades\Huckle;

class DatabaseService
{
    public function getConnectionConfig(): array
    {
        $node = Huckle::get('database.production.main');

        return [
            'driver'   => 'pgsql',
            'host'     => $node->host,
            'port'     => $node->port,
            'database' => $node->database,
            'username' => $node->username,
            'password' => $node->password->reveal(),
            'sslmode'  => $node->ssl_mode,
        ];
    }
}
```

<a id="doc-docs-artisan-commands"></a>

## Available Commands

| Command | Description |
|---------|-------------|
| `huckle:list` | List all nodes |
| `huckle:show {path}` | Show node details |
| `huckle:export` | Export as environment variables |
| `huckle:sync` | Sync exports to .env file |
| `huckle:lint` | Validate configuration |
| `huckle:connect {path} {connection}` | Execute connection command |
| `huckle:diff {env1} {env2}` | Compare environments |
| `huckle:expiring` | List expiring nodes |
| `huckle:hcl2json {input} [output]` | Convert HCL to JSON |
| `huckle:json2hcl {input} [output]` | Convert JSON to HCL |

## Command Details

### huckle:list

List all nodes with their paths, environments, and tags:

```bash
php artisan huckle:list

# Filter by single environment
php artisan huckle:list --environment=production

# Filter by multiple environments (OR logic)
php artisan huckle:list --environment=local --environment=sandbox --environment=staging

# Filter by tag
php artisan huckle:list --tag=postgres
```

### huckle:show

Display detailed information about a specific node:

```bash
php artisan huckle:show database.production.main

# Reveal sensitive values
php artisan huckle:show database.production.main --reveal
```

### huckle:export

Export nodes as environment variables:

```bash
# Export specific node
php artisan huckle:export database.production.main

# Export all nodes
php artisan huckle:export --all
```

### huckle:sync

Sync exported values to your .env file:

```bash
php artisan huckle:sync

# Sync to specific file
php artisan huckle:sync --file=.env.production
```

### huckle:lint

Validate your nodes configuration:

```bash
php artisan huckle:lint

# With expiry and rotation checks
php artisan huckle:lint --check-expiry --check-rotation --check-permissions

# Show environment variables table
php artisan huckle:lint --table

# Filter table by partition/environment/provider/country
php artisan huckle:lint --table --partition=FI --environment=production
php artisan huckle:lint --table --provider=stripe --country=SE
```

### huckle:connect

Execute a connection command defined in a node:

```bash
php artisan huckle:connect database.production.main psql
```

### huckle:diff

Compare nodes between environments:

```bash
php artisan huckle:diff production staging
```

### huckle:expiring

List nodes that are expiring soon:

```bash
# Default: 30 days
php artisan huckle:expiring

# Custom threshold
php artisan huckle:expiring --days=7
```

### huckle:hcl2json

Convert HCL file to JSON:

```bash
# Output to stdout
php artisan huckle:hcl2json nodes.hcl

# Output to file
php artisan huckle:hcl2json nodes.hcl nodes.json
```

### huckle:json2hcl

Convert JSON file to HCL:

```bash
# Output to stdout
php artisan huckle:json2hcl nodes.json

# Output to file
php artisan huckle:json2hcl nodes.json nodes.hcl
```

<a id="doc-docs-config-cache-compatibility"></a>

Make Huckle work seamlessly with Laravel's `config:cache` command.

**Use case:** Production deployments where `php artisan config:cache` is used for performance optimization.

## The Problem

When you run `php artisan config:cache`, Laravel compiles all configuration files into a single cached file. After caching, `env()` calls return `null` because Laravel no longer reads from environment variables.

Traditional Huckle usage with `exportContextToEnv()` sets `$_ENV`, `$_SERVER`, and `putenv()` — but these are ignored by cached configs.

## The Solution

Huckle provides three mechanisms to write directly to Laravel's config repository, bypassing `env()` entirely:

1. **`config` blocks** — Define config paths directly in your HCL nodes
2. **`mappings` block** — Global env-to-config path mappings in HCL
3. **PHP config mappings** — Define mappings in `config/huckle.php`

## Method 1: Config Blocks (Per-Node)

Define config paths directly alongside your exports:

```hcl
partition "payments" {
  environment "production" {
    provider "stripe" {
      api_key    = sensitive("pk_live_xxx")
      api_secret = sensitive("sk_live_xxx")

      # Traditional env exports (still useful for non-cached scenarios)
      export {
        STRIPE_KEY    = self.api_key
        STRIPE_SECRET = self.api_secret
      }

      # Direct Laravel config paths
      config {
        "cashier.key"         = self.api_key
        "cashier.secret"      = self.api_secret
        "services.stripe.key" = self.api_key
      }
    }
  }
}
```

## Method 2: Global Mappings Block

Define a top-level `mappings` block to map environment variable names to config paths:

```hcl
# Global mappings apply to all exports with matching keys
mappings {
  STRIPE_KEY        = "cashier.key"
  STRIPE_SECRET     = "cashier.secret"
  REDIS_HOST        = "database.redis.default.host"
  REDIS_PASSWORD    = "database.redis.default.password"
  DB_HOST           = "database.connections.pgsql.host"
  DB_PASSWORD       = "database.connections.pgsql.password"
}

partition "payments" {
  environment "production" {
    provider "stripe" {
      api_key = sensitive("pk_live_xxx")

      export {
        STRIPE_KEY = self.api_key  # Will also write to cashier.key via mapping
      }
    }
  }
}
```

## Method 3: PHP Config Mappings

Define mappings in your `config/huckle.php`:

```php
return [
    'path' => base_path('config/huckle.hcl'),

    'mappings' => [
        'STRIPE_KEY'    => 'cashier.key',
        'STRIPE_SECRET' => 'cashier.secret',
        'REDIS_HOST'    => 'database.redis.default.host',
        'DB_HOST'       => 'database.connections.pgsql.host',
    ],
];
```

## Precedence

When multiple mapping sources exist, they're applied in this order (later overrides earlier):

1. PHP config mappings (`config/huckle.php`)
2. HCL `mappings` block
3. Node `config` blocks (highest precedence)

## Usage in Code

### Standard Usage (env + config)

```php
use Cline\Huckle\Facades\Huckle;

// Writes to both env AND Config
Huckle::exportContextToEnv([
    'partition' => 'payments',
    'environment' => 'production',
]);

// Both work:
$key = getenv('STRIPE_KEY');      // 'pk_live_xxx'
$key = config('cashier.key');      // 'pk_live_xxx'
```

### Config-Only Usage (for config:cache)

```php
use Cline\Huckle\Facades\Huckle;

// Writes ONLY to Config (no env)
Huckle::exportContextToConfig([
    'partition' => 'payments',
    'environment' => 'production',
]);

// Only config works (env is not set):
$key = config('cashier.key');  // 'pk_live_xxx'
```

## Production Setup

### Service Provider Integration

Create a service provider that loads Huckle config early in the boot process:

```php
<?php

namespace App\Providers;

use Cline\Huckle\Facades\Huckle;
use Illuminate\Support\ServiceProvider;

class HuckleServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Determine context from your application
        $context = [
            'partition' => config('app.tenant', 'default'),
            'environment' => app()->environment(),
        ];

        // Use exportContextToConfig for config:cache compatibility
        Huckle::exportContextToConfig($context);
    }
}
```

Register it early in `config/app.php`:

```php
'providers' => [
    // Load before other providers that need the config values
    App\Providers\HuckleServiceProvider::class,

    // Other providers...
],
```

### Multi-Tenant Example

```php
<?php

namespace App\Http\Middleware;

use Cline\Huckle\Facades\Huckle;
use Closure;
use Illuminate\Http\Request;

class ApplyTenantConfig
{
    public function handle(Request $request, Closure $next)
    {
        $tenant = $request->route('tenant') ?? session('tenant');

        if ($tenant) {
            Huckle::exportContextToConfig([
                'partition' => $tenant,
                'environment' => app()->environment(),
            ]);
        }

        return $next($request);
    }
}
```

## API Reference

### Facade Methods

```php
// Export to both env and Config (applies mappings + config blocks)
Huckle::exportContextToEnv(array $context): HuckleManager

// Export only to Config (for config:cache compatibility)
Huckle::exportContextToConfig(array $context): HuckleManager

// Get config mappings for a context
Huckle::configsForContext(array $context): array

// Get all mappings (HCL + PHP config merged)
Huckle::mappings(): array
```

### HuckleConfig Methods

```php
$config = Huckle::config();

// Get global mappings from HCL
$config->mappings(): array

// Get specific mapping
$config->mapping('STRIPE_KEY'): ?string

// Check if mapping exists
$config->hasMapping('STRIPE_KEY'): bool

// Get configs for a node path
$config->configs('payments.production.stripe'): array

// Get all configs matching context
$config->configsForContext(['partition' => 'US']): array
```

### Node Methods

```php
$node = Huckle::get('payments.production.stripe');

// Get resolved config mappings
$node->config(): array  // ['cashier.key' => 'pk_live_xxx', ...]

// Access raw configs property
$node->configs  // ['cashier.key' => 'self.api_key', ...] (unresolved)
```

## Verifying It Works

```bash
# Cache your config
php artisan config:cache

# Verify values are accessible
php artisan tinker --execute="dump(config('cashier.key'))"
# Should output your Stripe key, not null

# Clear cache for development
php artisan config:clear
```

<a id="doc-docs-connection-commands"></a>

Define and execute CLI connection commands for nodes like database clients, SSH, and APIs.

**Use case:** Quick access to database shells, SSH connections, and other CLI tools using stored nodes.

## Defining Connections

Add `connect` blocks inside nodes:

```hcl
partition "database" {
  environment "production" {
    provider "main" {
      host     = "db.prod.internal"
      port     = 5432
      username = "app_user"
      password = sensitive("secret123")
      database = "myapp"

      # PostgreSQL connection
      connect "psql" {
        command = "psql -h ${self.host} -p ${self.port} -U ${self.username} -d ${self.database}"
      }

      # pgAdmin connection string
      connect "pgadmin" {
        command = "pgadmin4 --server=${self.host}:${self.port}"
      }
    }
  }
}

partition "server" {
  environment "production" {
    tags = ["prod", "ssh"]

    provider "web" {
      host     = "web.prod.internal"
      user     = "deploy"
      key_path = "~/.ssh/prod_key"
      port     = 22

      connect "ssh" {
        command = "ssh -i ${self.key_path} -p ${self.port} ${self.user}@${self.host}"
      }

      connect "scp" {
        command = "scp -i ${self.key_path} -P ${self.port}"
      }
    }
  }
}
```

## Listing Available Connections

```bash
# List connections for a node
php artisan huckle:connect database.production.main --list

# Output:
# Available connections for database.production.main:
#   psql: psql -h db.prod.internal -p 5432 -U app_user -d myapp
#   pgadmin: pgadmin4 --server=db.prod.internal:5432
```

## Executing Connections

```bash
# Execute a specific connection
php artisan huckle:connect database.production.main psql

# Opens an interactive psql session
```

## Copying Commands to Clipboard

```bash
# Copy command instead of executing
php artisan huckle:connect database.production.main psql --copy

# Output:
# Command copied to clipboard: psql -h db.prod.internal -p 5432 -U app_user -d myapp
```

## Accessing Connections in Code

```php
use Cline\Huckle\Facades\Huckle;

$node = Huckle::get('database.production.main');

// Get all connection names
$names = $node->connectionNames();
// ['psql', 'pgadmin']

// Get a specific connection command
$psqlCommand = $node->connection('psql');
// 'psql -h db.prod.internal -p 5432 -U app_user -d myapp'
```

## Common Connection Examples

### MySQL/MariaDB

```hcl
connect "mysql" {
  command = "mysql -h ${self.host} -P ${self.port} -u ${self.username} -p${self.password} ${self.database}"
}

connect "mycli" {
  command = "mycli -h ${self.host} -P ${self.port} -u ${self.username} -p ${self.password} ${self.database}"
}
```

### Redis

```hcl
connect "redis-cli" {
  command = "redis-cli -h ${self.host} -p ${self.port} -a ${self.password}"
}
```

### MongoDB

```hcl
connect "mongosh" {
  command = "mongosh mongodb://${self.username}:${self.password}@${self.host}:${self.port}/${self.database}"
}
```

### SSH with Jump Host

```hcl
connect "ssh-jump" {
  command = "ssh -J ${self.jump_host} ${self.user}@${self.host}"
}
```

## Complete Example: Database Team Setup

```hcl
# nodes.hcl

partition "database" {
  environment "production" {
    tags = ["prod", "postgres", "critical"]

    provider "primary" {
      host     = "db-primary.prod.internal"
      port     = 5432
      username = "admin"
      password = sensitive("admin-secret")
      database = "myapp"

      connect "psql" {
        command = "psql -h ${self.host} -p ${self.port} -U ${self.username} -d ${self.database}"
      }

      connect "pg_dump" {
        command = "pg_dump -h ${self.host} -p ${self.port} -U ${self.username} -d ${self.database}"
      }
    }

    provider "readonly" {
      host     = "db-replica.prod.internal"
      port     = 5432
      username = "readonly"
      password = sensitive("readonly-secret")
      database = "myapp"

      connect "psql" {
        command = "psql -h ${self.host} -p ${self.port} -U ${self.username} -d ${self.database}"
      }
    }
  }
}
```

```bash
# Connect to primary for admin work
php artisan huckle:connect database.production.primary psql

# Connect to replica for read queries
php artisan huckle:connect database.production.readonly psql

# Dump production database
php artisan huckle:connect database.production.primary pg_dump --copy
# Then paste and redirect: > backup.sql
```

<a id="doc-docs-encryption"></a>

Encrypt and decrypt HCL configuration files for secure storage and deployment using Laravel's Encrypter with AES-256-CBC.

**Use cases:** Encrypting sensitive configs at rest, storing encrypted configs in version control, and decrypting during deployment.

## Basic Encryption

```php
use Cline\Huckle\Facades\Huckle;

// Encrypt a configuration file (generates a new key)
$result = Huckle::encrypt('/path/to/credentials.hcl');

// $result contains:
// [
//     'path' => '/path/to/credentials.hcl.encrypted',  // The encrypted file
//     'key'  => 'base64:ABC123...',                    // Store this securely!
// ]
```

:::caution
Save the key securely (environment variable, secret manager, etc.). You'll need it to decrypt the file later.
:::

## Decryption

```php
// Decrypt using the key from encryption
$decryptedPath = Huckle::decrypt(
    '/path/to/credentials.hcl.encrypted',
    'base64:ABC123...',  // The key from encrypt()
);

// Returns: '/path/to/credentials.hcl' (original filename without .encrypted)
```

## Using a Custom Key

```php
// Generate your own key (must be valid for AES-256-CBC = 32 bytes)
$myKey = 'base64:' . base64_encode(random_bytes(32));

// Encrypt with your key
$result = Huckle::encrypt('/path/to/config.hcl', $myKey);

// Decrypt with the same key
Huckle::decrypt('/path/to/config.hcl.encrypted', $myKey);
```

## Force Overwrite

```php
// By default, decrypt() throws if the target file already exists
// Use force: true to overwrite
$decryptedPath = Huckle::decrypt(
    '/path/to/credentials.hcl.encrypted',
    $key,
    force: true,
);
```

## Custom Cipher

```php
// Use a different cipher (default is AES-256-CBC)
$result = Huckle::encrypt(
    '/path/to/config.hcl',
    cipher: 'AES-128-CBC',  // 16-byte key
);

// Decrypt with matching cipher
Huckle::decrypt(
    '/path/to/config.hcl.encrypted',
    $result['key'],
    cipher: 'AES-128-CBC',
);
```

## CLI Commands

```bash
# Encrypt a file (generates and displays key)
php artisan huckle:encrypt config/credentials.hcl

# Encrypt with a specific key
php artisan huckle:encrypt config/credentials.hcl --key="base64:ABC123..."

# Encrypt using APP_KEY
php artisan huckle:encrypt config/credentials.hcl --app-key

# Encrypt and delete original
php artisan huckle:encrypt config/credentials.hcl --prune

# Decrypt a file
php artisan huckle:decrypt config/credentials.hcl.encrypted --key="base64:ABC123..."

# Decrypt using APP_KEY
php artisan huckle:decrypt config/credentials.hcl.encrypted --app-key

# Decrypt with force overwrite
php artisan huckle:decrypt config/credentials.hcl.encrypted --key="..." --force

# Decrypt to custom path
php artisan huckle:decrypt config/credentials.hcl.encrypted --key="..." --path=/var/www/config

# Decrypt with custom filename
php artisan huckle:decrypt config/credentials.hcl.encrypted --key="..." --filename=decrypted.hcl

# Use custom cipher
php artisan huckle:encrypt config/credentials.hcl --cipher=AES-128-CBC
php artisan huckle:decrypt config/credentials.hcl.encrypted --key="..." --cipher=AES-128-CBC

# Environment-specific encryption (suffix style)
php artisan huckle:encrypt config/credentials.hcl --env=production
php artisan huckle:decrypt config/credentials.hcl --key="..." --env=production

# Environment-specific encryption (directory style)
php artisan huckle:encrypt config/credentials.hcl --env=production --env-style=directory
php artisan huckle:decrypt config/credentials.hcl --key="..." --env=production --env-style=directory
```

## Environment-Specific Files

### Suffix Style (Default)

Suffix style transforms `config.hcl` → `config.production.hcl`. This matches Laravel's `.env` pattern.

```php
$result = Huckle::encrypt(
    '/path/to/config.hcl',
    env: 'production',  // Encrypts /path/to/config.production.hcl
);

// Decrypts /path/to/config.production.hcl.encrypted
Huckle::decrypt(
    '/path/to/config.hcl',
    $result['key'],
    env: 'production',
);
```

### Directory Style

Directory style transforms `config/credentials/db.hcl` → `config/credentials/production/db.hcl`. Perfect for organizing configs in environment subdirectories.

```php
$result = Huckle::encrypt(
    '/path/to/config/credentials/db.hcl',
    env: 'production',
    envStyle: 'directory',
);

Huckle::decrypt(
    '/path/to/config/credentials/db.hcl',
    $result['key'],
    env: 'production',
    envStyle: 'directory',
);
```

## Prune Option

Delete the source file after the operation completes.

```php
// Delete the original file after encryption
$result = Huckle::encrypt('/path/to/credentials.hcl', prune: true);
// /path/to/credentials.hcl is deleted, only .encrypted remains

// Delete the encrypted file after decryption
Huckle::decrypt('/path/to/credentials.hcl.encrypted', $key, prune: true);
// .encrypted file is deleted, only decrypted file remains
```

## Custom Output Location

```php
// Output to a different directory
$decryptedPath = Huckle::decrypt(
    '/path/to/credentials.hcl.encrypted',
    $key,
    path: '/var/www/app/config',
);
// Returns: /var/www/app/config/credentials.hcl

// Use a custom filename
$decryptedPath = Huckle::decrypt(
    '/path/to/credentials.hcl.encrypted',
    $key,
    filename: 'decrypted-credentials.hcl',
);
// Returns: /path/to/decrypted-credentials.hcl

// Combine path and filename
$decryptedPath = Huckle::decrypt(
    '/path/to/credentials.hcl.encrypted',
    $key,
    path: '/var/www/app/config',
    filename: 'app-credentials.hcl',
);
// Returns: /var/www/app/config/app-credentials.hcl
```

## Directory Encryption

Encrypt all files in a directory with a single key. Perfect for encrypting entire config directories like `.huckle/`.

```php
use Cline\Huckle\Facades\Huckle;

// Encrypt all files in a directory
$result = Huckle::encryptDirectory('/path/to/.huckle');

// $result contains:
// [
//     'files' => [
//         ['path' => '/path/to/.huckle/config.hcl.encrypted', 'key' => '...'],
//         ['path' => '/path/to/.huckle/secrets.hcl.encrypted', 'key' => '...'],
//     ],
//     'key' => 'base64:ABC123...',  // Same key for all files
// ]
```

### Recursive Directory Encryption

```php
// Encrypt files in subdirectories too
$result = Huckle::encryptDirectory(
    '/path/to/.huckle',
    recursive: true,
);
```

### Filter Files with Glob Pattern

```php
// Only encrypt HCL files
$result = Huckle::encryptDirectory(
    '/path/to/.huckle',
    glob: '*.hcl',
);

// Encrypt HCL files recursively
$result = Huckle::encryptDirectory(
    '/path/to/.huckle',
    recursive: true,
    glob: '*.hcl',
);
```

### Directory Decryption

```php
// Decrypt all .encrypted files in directory
$decryptedPaths = Huckle::decryptDirectory(
    '/path/to/.huckle',
    'base64:ABC123...',  // Key from encryptDirectory()
);

// Returns array of decrypted file paths:
// ['/path/to/.huckle/config.hcl', '/path/to/.huckle/secrets.hcl']

// Recursive decryption
$decryptedPaths = Huckle::decryptDirectory(
    '/path/to/.huckle',
    $key,
    recursive: true,
);
```

### Prune and Force Options

```php
// Delete originals after encryption
$result = Huckle::encryptDirectory(
    '/path/to/.huckle',
    prune: true,
);

// Overwrite existing encrypted files
$result = Huckle::encryptDirectory(
    '/path/to/.huckle',
    force: true,
);

// Delete encrypted files after decryption, overwrite existing
$decryptedPaths = Huckle::decryptDirectory(
    '/path/to/.huckle',
    $key,
    prune: true,
    force: true,
);
```

### CLI Commands for Directories

```bash
# Encrypt directory
php artisan huckle:encrypt .huckle

# Encrypt recursively with glob filter
php artisan huckle:encrypt .huckle --recursive --glob='*.hcl'

# Delete originals after encryption
php artisan huckle:encrypt .huckle --recursive --prune

# Decrypt directory
php artisan huckle:decrypt .huckle --key="base64:ABC123..."

# Decrypt recursively with force
php artisan huckle:decrypt .huckle --key="..." --recursive --force
```

<a id="doc-docs-environment-management"></a>

Organize nodes by environment (development, staging, production) and switch contexts dynamically.

**Use case:** Managing the same services across multiple environments with environment-specific configurations.

## Environment Structure

```hcl
partition "database" {
  environment "development" {
    provider "main" {
      host     = "localhost"
      port     = 5432
      username = "dev_user"
      password = sensitive("dev_password")
    }
  }

  environment "staging" {
    provider "main" {
      host     = "staging-db.internal"
      port     = 5432
      username = "staging_user"
      password = sensitive("staging_password")
    }
  }

  environment "production" {
    provider "main" {
      host     = "prod-db.internal"
      port     = 5432
      username = "prod_user"
      password = sensitive("prod_password")
    }
  }
}
```

## Filtering by Environment

### CLI

```bash
# List nodes in production
php artisan huckle:list --environment=production

# List nodes in multiple environments
php artisan huckle:list --environment=staging --environment=production
```

### Code

```php
use Cline\Huckle\Facades\Huckle;

// Get all nodes for an environment
$prodNodes = Huckle::forEnvironment('production');

// Export all production configs
Huckle::exportContextToEnv([
    'environment' => 'production',
]);
```

## Dynamic Environment Switching

```php
use Cline\Huckle\Facades\Huckle;

class DatabaseService
{
    public function getConfig(): array
    {
        $env = app()->environment();
        $node = Huckle::get("database.{$env}.main");

        return [
            'host' => $node->host,
            'port' => $node->port,
            'username' => $node->username,
            'password' => $node->password->reveal(),
        ];
    }
}
```

## Comparing Environments

```bash
# Compare production and staging
php artisan huckle:diff production staging

# Output shows differences in:
# - Node values
# - Missing nodes
# - Extra nodes
```

## Environment-Specific Tags

```hcl
partition "api" {
  environment "production" {
    tags = ["prod", "critical", "monitored"]

    provider "main" {
      # ...
    }
  }

  environment "development" {
    tags = ["dev", "local"]

    provider "main" {
      # ...
    }
  }
}
```

Filter by environment AND tag:

```bash
php artisan huckle:list --environment=production --tag=critical
```

<a id="doc-docs-exports-syncing"></a>

Export node values to environment variables and sync them to your .env file.

**Use case:** Keeping your `.env` file in sync with your HCL configuration, especially during development.

## Defining Exports

Add `export` blocks inside nodes to define which values should be exported:

```hcl
partition "database" {
  environment "production" {
    provider "main" {
      host     = "db.prod.internal"
      port     = 5432
      username = "app_user"
      password = sensitive("secret123")
      database = "myapp"

      export {
        DB_HOST     = self.host
        DB_PORT     = self.port
        DB_USERNAME = self.username
        DB_PASSWORD = self.password
        DB_DATABASE = self.database
      }
    }
  }
}
```

## Exporting to Environment

### CLI

```bash
# Export specific node
php artisan huckle:export database.production.main

# Export all nodes
php artisan huckle:export --all
```

### Code

```php
use Cline\Huckle\Facades\Huckle;

// Export specific node
$node = Huckle::get('database.production.main');
$node->exportToEnv();

// Export by context
Huckle::exportContextToEnv([
    'partition' => 'database',
    'environment' => 'production',
]);
```

## Syncing to .env File

The `huckle:sync` command writes exported values directly to your `.env` file:

```bash
# Sync to default .env
php artisan huckle:sync

# Sync to specific file
php artisan huckle:sync --file=.env.production

# Preview changes without writing
php artisan huckle:sync --dry-run
```

## How Syncing Works

1. Reads the current `.env` file
2. Finds all exported values from your nodes
3. Updates existing keys or adds new ones
4. Preserves comments and formatting
5. Writes the updated file

## Example Workflow

```hcl
# nodes.hcl
partition "services" {
  environment "local" {
    provider "stripe" {
      api_key    = sensitive("pk_test_xxx")
      api_secret = sensitive("sk_test_xxx")

      export {
        STRIPE_KEY    = self.api_key
        STRIPE_SECRET = self.api_secret
      }
    }
  }
}
```

```bash
# Your .env before:
# APP_NAME=MyApp
# APP_ENV=local

php artisan huckle:sync

# Your .env after:
# APP_NAME=MyApp
# APP_ENV=local
# STRIPE_KEY=pk_test_xxx
# STRIPE_SECRET=sk_test_xxx
```

## Context-Based Export

Export only nodes matching specific criteria:

```php
use Cline\Huckle\Facades\Huckle;

// Export all nodes for a partition
Huckle::exportContextToEnv([
    'partition' => 'services',
]);

// Export all nodes for an environment
Huckle::exportContextToEnv([
    'environment' => 'production',
]);

// Combine filters
Huckle::exportContextToEnv([
    'partition' => 'database',
    'environment' => 'production',
    'provider' => 'main',
]);
```

## Viewing Exports

```bash
# Show what would be exported (table format)
php artisan huckle:lint --table

# Filter by context
php artisan huckle:lint --table --environment=production
php artisan huckle:lint --table --partition=database
```

<a id="doc-docs-hcl-conversion"></a>

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

<a id="doc-docs-nested-hierarchy-credentials"></a>

Organize credentials in deeply nested hierarchies for complex multi-tenant or multi-region setups.

**Use case:** Managing credentials across multiple countries, tenants, environments, and service providers.

## Hierarchy Structure

```hcl
partition "EU" {
  environment "production" {
    tags = ["eu", "prod", "gdpr"]

    provider "stripe" {
      country "DE" {
        api_key    = sensitive("pk_live_de_xxx")
        api_secret = sensitive("sk_live_de_xxx")
        webhook_secret = sensitive("whsec_de_xxx")
      }

      country "FR" {
        api_key    = sensitive("pk_live_fr_xxx")
        api_secret = sensitive("sk_live_fr_xxx")
        webhook_secret = sensitive("whsec_fr_xxx")
      }

      country "SE" {
        api_key    = sensitive("pk_live_se_xxx")
        api_secret = sensitive("sk_live_se_xxx")
        webhook_secret = sensitive("whsec_se_xxx")
      }
    }

    provider "database" {
      country "DE" {
        host     = "db-de.eu.prod.internal"
        port     = 5432
        username = "app_de"
        password = sensitive("de_prod_secret")
      }

      country "FR" {
        host     = "db-fr.eu.prod.internal"
        port     = 5432
        username = "app_fr"
        password = sensitive("fr_prod_secret")
      }
    }
  }
}
```

## Accessing Nested Nodes

```php
use Cline\Huckle\Facades\Huckle;

// Full path access
$deStripe = Huckle::get('EU.production.stripe.DE');
$frDb = Huckle::get('EU.production.database.FR');

// Access fields
$apiKey = $deStripe->api_key->reveal();  // 'pk_live_de_xxx'
$host = $frDb->host;  // 'db-fr.eu.prod.internal'
```

## Filtering by Country

```bash
# List all nodes for Germany
php artisan huckle:lint --table --country=DE

# List Stripe nodes for Sweden in production
php artisan huckle:lint --table --provider=stripe --country=SE --environment=production
```

## Context-Based Export

```php
use Cline\Huckle\Facades\Huckle;

// Export all German production configs
Huckle::exportContextToEnv([
    'partition' => 'EU',
    'environment' => 'production',
    'country' => 'DE',
]);

// Export all Stripe configs across EU
Huckle::exportContextToEnv([
    'partition' => 'EU',
    'provider' => 'stripe',
]);
```

## Multi-Region Setup

```hcl
# US region
partition "US" {
  environment "production" {
    provider "stripe" {
      country "US" {
        api_key = sensitive("pk_live_us_xxx")
      }
    }
  }
}

# EU region
partition "EU" {
  environment "production" {
    provider "stripe" {
      country "DE" {
        api_key = sensitive("pk_live_de_xxx")
      }
      country "FR" {
        api_key = sensitive("pk_live_fr_xxx")
      }
    }
  }
}

# APAC region
partition "APAC" {
  environment "production" {
    provider "stripe" {
      country "JP" {
        api_key = sensitive("pk_live_jp_xxx")
      }
      country "AU" {
        api_key = sensitive("pk_live_au_xxx")
      }
    }
  }
}
```

## Dynamic Country Selection

```php
use Cline\Huckle\Facades\Huckle;

class PaymentService
{
    public function getStripeKey(string $region, string $country): string
    {
        $node = Huckle::get("{$region}.production.stripe.{$country}");

        return $node->api_key->reveal();
    }
}

// Usage
$service = new PaymentService();
$key = $service->getStripeKey('EU', 'DE');  // German Stripe key
$key = $service->getStripeKey('US', 'US');  // US Stripe key
```

## Comparing Countries

```bash
# Compare German and French configs
php artisan huckle:diff EU.production.stripe.DE EU.production.stripe.FR
```

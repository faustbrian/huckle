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

# Getting Started with Huckle

Create and manage credentials using HCL syntax with environment organization, tagging, and sensitive value protection.

**Use case:** Managing database, API, and service credentials across development, staging, and production environments.

## Creating Your First Credentials File

Create a `credentials.hcl` file in your project root or a secure location:

```hcl
# credentials.hcl

# Default values applied to all credentials
defaults {
  owner      = "platform-team"
  expires_in = "90d"
}

# Database credentials for production
group "database" "production" {
  tags = ["prod", "postgres", "critical"]

  credential "main" {
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
```

## Configuration

Update your `config/huckle.php`:

```php
return [
    // Path to your credentials file
    'path' => env('HUCKLE_PATH', base_path('credentials.hcl')),

    // Default days to check for expiring credentials
    'expiring_days' => 30,

    // Auto-export all credentials to env on boot
    'auto_export' => false,
];
```

## Accessing Credentials in Code

```php
use Cline\Huckle\Facades\Huckle;

// Get a specific credential by path
$db = Huckle::get('database.production.main');

// Access fields directly
$host = $db->host;           // 'db.prod.internal'
$port = $db->port;           // 5432

// Access sensitive values
$password = $db->password;   // SensitiveValue object
$password->reveal();         // 'your-secret-password'
$password->masked();         // '********'

// Check if credential exists
if (Huckle::has('database.production.main')) {
    // ...
}
```

## Listing Credentials

Use the artisan command to see all credentials:

```bash
php artisan huckle:list

# Output:
# +--------------------------------+------------+--------+
# | Path                           | Environment| Tags   |
# +--------------------------------+------------+--------+
# | database.production.main       | production | prod   |
# +--------------------------------+------------+--------+
```

## Showing Credential Details

```bash
php artisan huckle:show database.production.main

# Shows all fields, exports, and connection commands
# Sensitive values are masked by default
# Use --reveal to show actual values
```

## Validating Configuration

Check your credentials file for errors:

```bash
php artisan huckle:lint

# Validates:
# - HCL syntax
# - Required fields
# - File permissions
```

## Using Blade Directives

Access credentials in your views:

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
        $credential = Huckle::get('database.production.main');

        return [
            'driver'   => 'pgsql',
            'host'     => $credential->host,
            'port'     => $credential->port,
            'database' => $credential->database,
            'username' => $credential->username,
            'password' => $credential->password->reveal(),
            'sslmode'  => $credential->ssl_mode,
        ];
    }
}
```

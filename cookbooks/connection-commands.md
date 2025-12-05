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

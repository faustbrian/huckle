# Artisan Commands

All CLI commands available in Huckle for managing credentials.

## Available Commands

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

## Command Details

### huckle:list

List all credentials with their paths, environments, and tags:

```bash
php artisan huckle:list

# Filter by environment
php artisan huckle:list --env=production

# Filter by tag
php artisan huckle:list --tag=postgres
```

### huckle:show

Display detailed information about a specific credential:

```bash
php artisan huckle:show database.production.main

# Reveal sensitive values
php artisan huckle:show database.production.main --reveal
```

### huckle:export

Export credentials as environment variables:

```bash
# Export specific credential
php artisan huckle:export database.production.main

# Export all credentials
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

Validate your credentials configuration:

```bash
php artisan huckle:lint

# Validates:
# - HCL syntax
# - Required fields
# - File permissions
```

### huckle:connect

Execute a connection command defined in a credential:

```bash
php artisan huckle:connect database.production.main psql
```

### huckle:diff

Compare credentials between environments:

```bash
php artisan huckle:diff production staging
```

### huckle:expiring

List credentials that are expiring soon:

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
php artisan huckle:hcl2json credentials.hcl

# Output to file
php artisan huckle:hcl2json credentials.hcl credentials.json
```

### huckle:json2hcl

Convert JSON file to HCL:

```bash
# Output to stdout
php artisan huckle:json2hcl credentials.json

# Output to file
php artisan huckle:json2hcl credentials.json credentials.hcl
```

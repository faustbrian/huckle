---
title: Artisan Commands
description: All CLI commands available in Huckle for managing nodes.
---

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

# Basic Huckle configuration for testing

defaults {
  owner      = "platform-team"
  expires_in = "90d"
}

group "database" "production" {
  tags = ["prod", "postgres", "critical"]

  credential "main" {
    host     = "db.prod.internal"
    port     = 5432
    username = "app_user"
    password = sensitive("secret123")
    database = "myapp_production"
    ssl_mode = "require"
    expires  = "2026-06-01"
    rotated  = "2025-01-15"
    owner    = "dba-team"

    export {
      DB_HOST       = self.host
      DB_PORT       = self.port
      DB_USERNAME   = self.username
      DB_PASSWORD   = self.password
      DB_DATABASE   = self.database
      DATABASE_URL  = "postgres://${self.username}:${self.password}@${self.host}:${self.port}/${self.database}"
    }

    connect "psql" {
      command = "psql -h ${self.host} -p ${self.port} -U ${self.username} -d ${self.database}"
    }
  }

  credential "readonly" {
    host     = "db.prod.internal"
    port     = 5432
    username = "readonly_user"
    password = sensitive("readonly123")
    database = "myapp_production"

    export {
      DB_READONLY_HOST     = self.host
      DB_READONLY_USERNAME = self.username
      DB_READONLY_PASSWORD = self.password
    }
  }
}

group "database" "staging" {
  tags = ["staging", "postgres"]

  credential "main" {
    host     = "db.staging.internal"
    port     = 5432
    username = "app_user"
    password = sensitive("staging_secret")
    database = "myapp_staging"

    export {
      DB_HOST     = self.host
      DB_USERNAME = self.username
      DB_PASSWORD = self.password
      DB_DATABASE = self.database
    }
  }
}

group "aws" "production" {
  tags = ["prod", "aws"]

  credential "deploy" {
    access_key = sensitive("AKIAIOSFODNN7EXAMPLE")
    secret_key = sensitive("wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY")
    region     = "us-east-1"

    export {
      AWS_ACCESS_KEY_ID     = self.access_key
      AWS_SECRET_ACCESS_KEY = self.secret_key
      AWS_DEFAULT_REGION    = self.region
    }
  }
}

group "redis" "production" {
  tags = ["prod", "redis", "cache"]

  credential "cache" {
    host     = "redis.prod.internal"
    port     = 6379
    password = sensitive("redis_secret")
    database = 0

    export {
      REDIS_HOST     = self.host
      REDIS_PORT     = self.port
      REDIS_PASSWORD = self.password
    }

    connect "redis-cli" {
      command = "redis-cli -h ${self.host} -p ${self.port} -a ${self.password}"
    }
  }
}

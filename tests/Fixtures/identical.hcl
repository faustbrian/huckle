# Fixture for testing diff with identical credentials

defaults {
  owner      = "platform-team"
  expires_in = "90d"
}

group "database" "production" {
  # Credential with some identical and some different fields
  credential "partial" {
    host     = "db.internal"
    port     = 5432
    username = "prod_user"
    database = "myapp"
  }

  credential "different" {
    host     = "db.prod.internal"
    port     = 5432
    username = "prod_user"
    password = sensitive("prod123")
  }
}

group "database" "staging" {
  # Same host, port, database as production but different username
  # This tests the continue branch when host/port/database are identical
  credential "partial" {
    host     = "db.internal"
    port     = 5432
    username = "staging_user"
    database = "myapp"
  }

  # Different from production
  credential "different" {
    host     = "db.staging.internal"
    port     = 5432
    username = "staging_user"
    password = sensitive("staging123")
  }
}

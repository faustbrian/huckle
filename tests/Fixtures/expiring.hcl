# Fixture for testing expiring, expired, and rotation scenarios

group "database" "production" {
  # Credential expiring in 16 days (within the 30-day warning window)
  credential "expiring_soon" {
    host     = "db.prod.internal"
    port     = 5432
    username = "expiring_user"
    password = sensitive("expiring123")
    database = "myapp"
    expires  = "2026-01-12"  # ~16 days from 2025-12-27
    rotated  = "2025-11-27"

    export {
      DB_HOST_EXPIRING = self.host
    }
  }

  # Credential already expired
  credential "already_expired" {
    host     = "db.prod.internal"
    port     = 5432
    username = "expired_user"
    password = sensitive("expired123")
    database = "myapp"
    expires  = "2025-12-01"  # Already expired (26 days ago from 2025-12-27)
    rotated  = "2025-10-01"

    export {
      DB_HOST_EXPIRED = self.host
    }
  }

  # Credential needing rotation (last rotated 120 days ago - beyond 90-day window)
  credential "needs_rotation" {
    host     = "db.prod.internal"
    port     = 5432
    username = "rotation_user"
    password = sensitive("rotation123")
    database = "myapp"
    expires  = "2027-12-01"  # Far in future - not expiring
    rotated  = "2025-08-29"  # 120 days ago from 2025-12-27

    export {
      DB_HOST_ROTATION = self.host
    }
  }

  # Credential that is fine (not expiring, recently rotated)
  credential "healthy" {
    host     = "db.prod.internal"
    port     = 5432
    username = "healthy_user"
    password = sensitive("healthy123")
    database = "myapp"
    expires  = "2027-12-01"  # Far in future - not expiring
    rotated  = "2025-11-27"  # Recently rotated (30 days ago)

    export {
      DB_HOST_HEALTHY = self.host
    }
  }
}

group "database" "staging" {
  # Identical credential for diff testing
  credential "same" {
    host     = "db.staging.internal"
    port     = 5432
    username = "same_user"
    password = sensitive("same123")
    database = "myapp"
  }
}

# Test fixture for expiration and rotation testing

defaults {
  owner      = "platform-team"
  expires_in = "90d"
}

group "expired" "production" {
  credential "past_expiration" {
    host     = "expired.db.internal"
    username = "expired_user"
    password = sensitive("expired_pass")
    expires  = "2024-01-01"  # Expired
    rotated  = "2024-01-01"  # Old rotation
  }

  credential "recently_expired" {
    host     = "recent.db.internal"
    username = "recent_user"
    password = sensitive("recent_pass")
    expires  = "2024-12-01"  # Recently expired
    rotated  = "2024-12-01"
  }
}

group "rotation" "production" {
  credential "needs_rotation" {
    host     = "rotate.db.internal"
    username = "rotate_user"
    password = sensitive("rotate_pass")
    expires  = "2026-06-01"  # Not expired
    rotated  = "2024-01-01"  # Old rotation (>90 days)
  }

  credential "never_rotated" {
    host     = "never.db.internal"
    username = "never_user"
    password = sensitive("never_pass")
    expires  = "2026-06-01"  # Not expired
    # No rotated field - never rotated
  }

  credential "recently_rotated" {
    host     = "fresh.db.internal"
    username = "fresh_user"
    password = sensitive("fresh_pass")
    expires  = "2026-06-01"  # Not expired
    rotated  = "2025-12-01"  # Recent rotation
  }
}

group "expiring" "production" {
  credential "expiring_soon" {
    host     = "expiring.db.internal"
    username = "expiring_user"
    password = sensitive("expiring_pass")
    expires  = "2025-12-15"  # Expiring soon (11 days from 2025-12-04)
    rotated  = "2025-10-01"
  }

  credential "expiring_later" {
    host     = "later.db.internal"
    username = "later_user"
    password = sensitive("later_pass")
    expires  = "2026-01-15"  # Expiring later (42 days from 2025-12-04, within 90 but not 30)
    rotated  = "2025-11-01"
  }

  credential "not_expiring" {
    host     = "noexpire.db.internal"
    username = "noexpire_user"
    password = sensitive("noexpire_pass")
    expires  = "2026-06-01"  # Not expiring soon (179 days from 2025-12-04)
    rotated  = "2025-11-01"
  }
}

# Test fixture for edge cases in parsing

defaults {
  owner = "platform-team"
}

# Test extractTags with null (no tags field)
group "notags" "production" {
  credential "no_tags" {
    host     = "notags.internal"
    username = "user"
    password = sensitive("pass")
  }
}

# Test extractTags with empty array
group "emptytags" "production" {
  tags = []

  credential "empty_tags" {
    host     = "emptytags.internal"
    username = "user"
    password = sensitive("pass")
    tags     = []
  }
}

# Test extractScalarValue edge cases
group "scalars" "production" {
  # Test with string value directly
  credential "string_scalar" {
    host     = "scalar.internal"
    username = "user"
    password = sensitive("pass")
    owner    = "direct-string"
    notes    = "Simple notes"
  }

  # Test with null/missing values
  credential "null_scalars" {
    host     = "null.internal"
    username = "user"
    password = sensitive("pass")
    # No owner, notes, expires, rotated fields
  }
}

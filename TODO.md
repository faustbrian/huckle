# HCL Full Compliance TODO

This document tracks the gaps between our current HCL parser implementation and the official [HashiCorp HCL specification](https://github.com/hashicorp/hcl/blob/main/hclsyntax/spec.md).

## Overview

Our parser now supports the complete **structural language** (attributes, blocks, bodies), **literal values**, **heredoc templates**, and **expression operators**. The remaining features are for expressions and template directives.

---

## Implemented Features

### 1. Heredoc Templates - COMPLETE

**Status:** Implemented
**Files:** `src/Parser/Lexer.php`, `src/Parser/TokenType.php`

Both standard (`<<EOT`) and flush (`<<-EOT`) heredocs are supported with:
- Multi-line string content
- Indentation stripping for flush heredocs
- Proper delimiter handling

### 2. Expression Operators - COMPLETE

**Status:** Implemented
**Files:** `src/Parser/ExpressionParser.php`, `src/Parser/TokenType.php`, `src/Parser/Lexer.php`

Full operator support with Pratt precedence climbing:

| Level | Operators | Description |
|-------|-----------|-------------|
| 7 | `*` `/` `%` | Multiply, Divide, Modulo |
| 6 | `+` `-` | Add, Subtract |
| 5 | `>` `>=` `<` `<=` | Numeric comparison |
| 4 | `==` `!=` | Equality |
| 3 | `&&` | Logical AND |
| 2 | `||` | Logical OR |
| 1 | `? :` | Ternary conditional |

Also supports:
- Unary operators (`!`, `-`)
- Parenthesized grouping
- Index access (`foo[0]`, `foo["key"]`)
- Attribute access (`foo.bar`)

---

## Remaining Features

### 3. For Expressions

**Status:** Not implemented
**Priority:** Medium

```hcl
# Tuple for
doubled = [for n in numbers: n * 2]

# Object for
by_name = {for u in users: u.name => u}

# With condition
adults = [for u in users: u if u.age >= 18]

# With grouping
by_role = {for u in users: u.role => u.name...}
```

**Tasks:**
- [ ] Parse for expression syntax in ExpressionParser
- [ ] Implement iteration with key/value binding
- [ ] Support conditional filtering (`if`)
- [ ] Support grouping mode (`...`)

### 4. Splat Operators

**Status:** Not implemented
**Priority:** Low

```hcl
# Attribute-only splat
names = users.*.name

# Full splat
first_items = data[*].items[0]
```

**Tasks:**
- [ ] Parse `.*` and `[*]` operators
- [ ] Implement iteration over collections
- [ ] Handle null coercion to empty list

### 5. Template Directives

**Status:** Not implemented
**Priority:** Low

```hcl
# If directive
result = "Status: %{ if enabled }ON%{ else }OFF%{ endif }"

# For directive
list = <<EOT
%{ for item in items }
- ${item}
%{ endfor }
EOT
```

**Tasks:**
- [ ] Parse `%{if}`, `%{else}`, `%{endif}` directives
- [ ] Parse `%{for}`, `%{endfor}` directives
- [ ] Implement control flow in template evaluation

### 6. Template Interpolation Evaluation

**Status:** Partial (lexer detects, not evaluated)
**Priority:** Medium

```hcl
message = "Hello, ${name}!"
url = "https://${host}:${port}/api"
```

**Current Status:** Lexer detects interpolation and marks strings, but expressions inside are not evaluated.

**Tasks:**
- [ ] Parse expressions within `${...}`
- [ ] Evaluate and substitute into string

---

## Testing Status

### Compliance Tests Passing

- `specsuite/tests/expressions/primitive_literals.hcl` - All cases
- `specsuite/tests/expressions/operators.hcl` - All cases
- `specsuite/tests/expressions/heredoc.hcl` - All cases
- `specsuite/tests/structure/attributes/` - All cases
- `specsuite/tests/structure/blocks/` - All cases
- `specsuite/tests/comments/` - All cases

---

## References

- [HCL Native Syntax Specification](https://github.com/hashicorp/hcl/blob/main/hclsyntax/spec.md)
- [HCL Information Model](https://github.com/hashicorp/hcl/blob/main/spec.md)
- [HCL JSON Syntax](https://github.com/hashicorp/hcl/blob/main/json/spec.md)
- [HashiCorp HCL Repository](https://github.com/hashicorp/hcl)
- [Specsuite Tests](https://github.com/hashicorp/hcl/tree/main/specsuite/tests)

# Security Model

## Authentication

### Login Flow
1. User submits email + password + group_code via `login.php`
2. `authenticateUser()` in `includes/auth.php`:
   - Checks login attempt limits
   - Queries `users` by email + group_code
   - Verifies user status = 'active'
   - Checks linked member status (if member_id exists)
   - Verifies password with `password_verify()`
   - On success: creates session, updates last_login, logs audit
3. `createUserSession()` stores user_id, role_id, role_slug, member data in `$_SESSION`

### Password Security
- Hashed with `password_hash(PASSWORD_DEFAULT)` → bcrypt
- Minimum length enforced (configurable in Settings)
- Login attempt limiting with lockout
- No plaintext storage ever

## Authorization

### Role System
- 9 roles in `roles` table
- Permissions assigned via `role_permissions` bridge table
- `hasPermission($slug)` checks Super Admin bypass (role_id = 1)
- `requirePermission($slug)` redirects or returns 403 AJAX response

### Permission Verification Flow
```
Controller → requirePermission('manage_members')
  → isLoggedIn() check
  → hasPermission() checks role_permissions table
  → Super Admin auto-passes (role_id = 1)
  → Deny → redirect to access-denied.php or 403 JSON
```

## CSRF Protection

### Implementation
- Every form includes `<input type="hidden" name="csrf_token">`
- `generateCsrf()` creates token stored in `$_SESSION['csrf_token']`
- `verifyCsrf($token)` compares against session + regenerates on success
- `jsonResponse()` includes `csrf_token` in every AJAX response
- DOM tokens refreshed from AJAX responses via `settings.php` JS

### Token Handling
- Token regenerated after each successful verification
- Prevents replay attacks
- DOM updated with new token from every JSON response

## SQL Injection Prevention
- All database queries use PDO prepared statements with parameterized queries
- No string concatenation in SQL
- PDO `EMULATE_PREPARES = false` for real prepared statements

## Session Security
| Measure | Implementation |
|---------|---------------|
| HttpOnly | `session.cookie_httponly = 1` |
| Secure | On HTTPS only |
| SameSite | `Strict` |
| Timeout | Configurable (default 3600s) |
| Regenerate ID | On login |
| Lockout | After max failed attempts |

## File Upload Security
| Check | Rule |
|-------|------|
| Extension whitelist | jpg, jpeg, png, gif, pdf, doc, docx |
| Max size | Configurable (default 2MB) |
| Storage | Outside webroot in `uploads/` |
| Rename | Randomized filename on save |

## Headers
```php
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
```

## Audit Trail
- All CRUD operations logged via `logAudit()` to `audit_logs` table
- Captures: user, action, module, record_id, old/new values (JSON), IP, user agent
- Login/logout events tracked
- Immutable — no delete UI for audit logs

# Troubleshooting Guide

## Login Issues

### "Invalid email, password, or group code"
1. Verify email is correct (check `users.email`)
2. Verify password is correct (reset in `users.php` if needed)
3. Verify `group_code` matches the user's record
4. Check `users.status` = 'active'
5. Check `members.status` = 'active' (if member_id is set)

### "Account locked"
- Wait for lockout period to expire (configurable in Settings)
- Admin can reset attempts via database: `UPDATE users SET login_attempts = 0, locked_until = NULL WHERE email = ?`

### "Account is suspended/inactive"
- Admin must change status in Users module

### "Your registration is pending..." (various messages)
- Member status is not 'active' — check `members.status`
- Complete registration/verification/approval workflow

## DataTables Issues

### "Requested unknown parameter" warning
- Column count mismatch between `<thead>` and `<tbody>`
- Run in browser console: `dtHelper.validate('table')`
- Verify `<tfoot>` colspan matches if present

### "Cannot reinitialise DataTable"
- Table already initialized. The `dtHelper.initAll()` skips initialized tables.
- If you need to force re-init: `dtHelper.init('#tableId')` (destroys first)

### Missing buttons/sorting
- Check if `no-sort` class is on `<th>` for action columns
- Ensure `dt-helper.js` is loaded before `main.js`

## Page Errors

### PHP Notice/Warning
- Check XAMPP error log at `C:\xampp\htdocs\chama-system\logs\error.log`
- Common causes: undefined array keys, null values in queries

### 404 Not Found
| Missing File | Status |
|-------------|--------|
| fines.php | Not implemented |
| assets.php | Not implemented |
| data-export.php | Not implemented |
| help.php | Not implemented |

### 500 Internal Server Error
- Check Apache error log
- Common: PHP parse error, database connection failure, missing file include

## Database Issues

### "Table doesn't exist"
- Run `migrations/run_007.php` and `migrations/run_009.php` to apply missing schema changes
- Verify database name in `config/database.php`

### "Unknown column"
- Schema mismatch — run migration scripts
- Check AGENTS.md for recent schema changes

## AJAX Issues

### CSRF token mismatch
- Refresh the page
- Token is regenerated after each request — DOM is updated via JSON response
- Check that `jsonResponse()` includes `csrf_token`

### 405 Method Not Allowed
- AJAX endpoints are POST-only (contact.php, resend-verification.php)
- Use POST method, not GET

## Browser-Specific

### jQuery not defined
- Clear browser cache (old cached pages without jQuery in header)
- Verify `views/layouts/header.php` loads jQuery before inline scripts

### Select2 not working
- Ensure jQuery is loaded first
- Check Select2 CSS/JS are loaded in header.php
- Verify select element has class `select2` or is initialized in JS

## SMTP / Email Issues

### Emails not sending
1. Configure SMTP in Settings → Email tab
2. Click "Test Email" to verify configuration
3. Check PHP error log for PHPMailer errors
4. Verify no firewall blocking outbound SMTP

### Registration emails not received
- SMTP must be configured first
- Check spam folder
- Verify member email address is correct

## Performance

### Slow page loads
- DataTables with large datasets: use server-side processing (check `serverSide` option)
- Enable MySQL query caching
- Check for missing indexes on joined columns

### High memory usage
- Increase PHP memory_limit in php.ini
- Reduce rows per page in DataTables (default: 25)

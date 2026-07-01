# Audit Logs Module

**File:** `audit-logs.php`  
**Access:** Super Admin, Admin, Auditor

## Description
System-wide audit trail tracking all user actions. Immutable log of who did what and when.

## DataTable Columns

| # | Column | Type | Description |
|---|--------|------|-------------|
| 1 | Date/Time | DateTime | When action occurred |
| 2 | User | Text | Username who performed action |
| 3 | Action | Text | Action type (create/update/delete/login) |
| 4 | Module | Text | Affected module/table |
| 5 | Record ID | Number | Affected record ID |
| 6 | Old Value | Text | Previous value (JSON) |
| 7 | New Value | Text | New value (JSON) |
| 8 | IP Address | Text | User's IP address |

## Filter Controls
| Filter | Type |
|--------|------|
| Date From | Date |
| Date To | Date |
| User | Select (from users) |
| Action | Select (create/update/delete/login) |
| Module | Select (all tables) |

## Features
- Search across all fields
- Date range filtering
- Export to CSV
- Auto-logging on all CRUD operations via `logAudit()` function
- Login/logout events tracked

## Related Modules
- All modules — every action is logged here

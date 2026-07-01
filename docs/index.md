# Chama System — User Manual

**Version:** 1.0  
**Last Updated:** July 2026  
**System URL:** `http://localhost/chama-system/`

---

## Table of Contents

1. [System Overview](#1-system-overview)
2. [Quick Start Guide](#2-quick-start-guide)
3. [Role-Based Access](#3-role-based-access)
4. [Module Reference](#4-module-reference)
   - [Dashboard](modules/dashboard.md)
   - [Members](modules/members.md)
   - [Users](modules/users.md)
   - [Meetings](modules/meetings.md)
   - [Contributions](modules/contributions.md)
   - [Loans](modules/loans.md)
   - [Shares — Products](modules/share-products.md)
   - [Shares — Purchases](modules/shares.md)
   - [Shares — Dividends](modules/dividends.md)
   - [Accounting — Income](modules/income.md)
   - [Accounting — Expenses](modules/expenses.md)
   - [Accounting — Journal](modules/journal.md)
   - [Accounting — Ledger](modules/ledger.md)
   - [Accounting — Trial Balance](modules/trial-balance.md)
   - [Reports](modules/reports.md)
   - [Settings](modules/settings.md)
   - [Audit Logs](modules/audit-logs.md)
   - [Contact Messages](modules/contact-messages.md)
   - [Profile](modules/profile.md)
5. [Database Schema](database-schema.md)
6. [Security Model](security.md)
7. [Troubleshooting](troubleshooting.md)

---

## 1. System Overview

Chama System is a web-based group management platform for chamas (investment groups). It manages members, contributions, loans, shares, dividends, meetings, and accounting.

### Technology Stack
- **Backend:** PHP 8.x (PDO/MySQL)
- **Frontend:** Bootstrap 5.3, jQuery 3.7, DataTables 2.x, Font Awesome 6, Select2
- **Database:** MySQL 5.7+ / MariaDB 10.3+
- **Session:** PHP native sessions with CSRF protection

### Key Features
- Role-based access control (9 roles)
- Multi-group isolation via `group_code`
- Member-user one-to-one integrity
- Automated share certificate generation
- Dividend declaration with auto-payment creation
- Full accounting module (double-entry)
- DataTables with server-side processing
- CSRF protection on all forms + AJAX

---

## 2. Quick Start Guide

### First-Time Setup
1. Ensure MySQL is running with database `chama_system` imported
2. Point Apache document root to `C:\xampp\htdocs\chama-system`
3. Open `http://localhost/chama-system/`
4. Login as **jack** (Super Admin)
   - Email: `jacksonlopito@gmail.com`
   - Password: `admin123`
   - Group Code: `CHAMA001`
5. Navigate to **Settings** to configure:
   - SMTP email settings
   - Group details
   - Default interest rates

### Navigation
- **Sidebar:** Main navigation (collapsible)
- **Top navbar:** Search, notifications, user menu
- **Breadcrumbs:** Location indicator below navbar

---

## 3. Role-Based Access

| Role | ID | Access Level |
|------|----|-------------|
| Super Admin | 1 | Full system access, all modules |
| Admin | 2 | All modules except system settings |
| Chairperson | 3 | Read + approve, no financial edits |
| Secretary | 4 | Create/edit meetings, member records |
| Treasurer | 5 | Contributions, loans, accounting |
| Loan Officer | 6 | Loan processing only |
| Member | 7 | Own profile, contributions, shares |
| Auditor | 8 | Read-only for all modules |
| Guest | 9 | Minimal public access |

### Permission Matrix
| Permission | Super Admin | Admin | Chair | Sec | Treas | Loan Off | Member | Auditor |
|-----------|:-----------:|:-----:|:-----:|:---:|:-----:|:--------:|:------:|:-------:|
| Manage Members | ✓ | ✓ | ✓ | ✓ | - | - | - | - |
| Manage Users | ✓ | ✓ | - | - | - | - | - | - |
| Manage Meetings | ✓ | ✓ | ✓ | ✓ | - | - | - | - |
| Manage Contributions | ✓ | ✓ | - | - | ✓ | - | - | - |
| Manage Loans | ✓ | ✓ | ✓ | - | ✓ | ✓ | - | - |
| Manage Accounting | ✓ | ✓ | - | - | ✓ | - | - | - |
| Manage Shares | ✓ | ✓ | - | - | ✓ | - | - | - |
| Manage Settings | ✓ | - | - | - | - | - | - | - |
| View Reports | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | - | ✓ |
| View Audit Logs | ✓ | ✓ | - | - | - | - | - | ✓ |
| Approve Members | ✓ | ✓ | ✓ | ✓ | - | - | - | - |

---

## 4. Module Reference

Each module is documented in its own file under `docs/modules/`.

### Module Documentation Format
Each module doc includes:
- **Description** — What the module does
- **Access Control** — Which roles can access
- **Fields Reference** — All form fields explained
- **Buttons/Actions** — Every button and what it does
- **DataTable Columns** — Column-by-column breakdown
- **Workflows** — Step-by-step procedures
- **Reports** — Associated reports and exports
- **Validation Rules** — All field validations
- **Related Modules** — Cross-references

---

## 5. Database Schema

See [Database Schema](database-schema.md) for the complete schema documentation including all 52+ tables, columns, types, indexes, and relationships.

---

## 6. Security Model

- **Authentication:** Password hashed via `password_hash()` (bcrypt)
- **CSRF:** Token regenerated per request; verified on all POST/GET mutations
- **Authorization:** `requirePermission()` with role-permission mapping
- **SQL Injection:** Parameterized PDO queries throughout
- **File Uploads:** Extension + size validation
- **Session:** HttpOnly, Secure (on HTTPS), SameSite=Strict
- **Headers:** X-Frame-Options, X-Content-Type-Options, Referrer-Policy set globally

---

## 7. Troubleshooting

| Problem | Solution |
|---------|----------|
| Login fails | Verify email, password, and group_code match. Check account status is 'active' and member status is 'active'. |
| 404 errors | `fines.php`, `assets.php`, `data-export.php`, `help.php` are not implemented in this version. |
| DataTables warning | Check column count in `<thead>` matches `<tbody>`. Run `dtHelper.validate('#tableId')` in browser console. |
| CSRF error on AJAX | Refresh the page to get a new CSRF token. The token is included in every `jsonResponse()`. |
| SMTP emails not sending | Configure SMTP in Settings → Email. No email features work without SMTP. |
| "Please fill in all fields" on login | The `group_code` field is required. Enter your group's code. |

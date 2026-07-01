# Advanced Chama Management System

A comprehensive member-based savings, loans, shares, and accounting management system built with **PHP 8**, **MySQL**, **Bootstrap 5**, and **jQuery**.

## Features

- **Membership Management** — Full member lifecycle: registration, verification, approval, suspension
- **Contributions** — Record member savings with receipt generation
- **Loans** — Multi-step approval workflow (Apply → Approve → Disburse → Repay) with configurable products
- **Shares** — Share products, member purchases, auto certificate generation, dividends management
- **Accounting** — Double-entry bookkeeping with journal, ledger, trial balance
- **Meetings** — Schedule, attendance tracking, minutes
- **Reports** — Member, meeting, loan, contribution reports with export
- **Multi-Group** — Isolated groups via `group_code` — single installation serves multiple chamas
- **Role-Based Access** — 9 roles: Super Admin, Admin, Chairperson, Secretary, Treasurer, Loan Officer, Member, Auditor, Guest
- **Homepage CMS** — Dynamic hero, about, services, stats, footer management
- **Security** — CSRF protection, PDO prepared statements, bcrypt hashing, session lockout, audit trail

## Requirements

- PHP 8.0+
- MySQL 5.7+ / MariaDB 10.3+
- Apache with mod_rewrite (or Nginx with equivalent rewrite rules)
- Composer (for PHPMailer dependency)

## Installation

### 1. Clone the Repository

```bash
git clone https://github.com/jacksonlopito-cell/chama_system.git
cd chama_system
```

### 2. Install Dependencies

```bash
composer install
```

### 3. Create the Database

```sql
CREATE DATABASE chama_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Then import the schema and seed data:

```bash
mysql -u root -p chama_system < database/sql/schema.sql
mysql -u root -p chama_system < database/sql/seed.sql
```

### 4. Configure Database Connection

```bash
cp config/database.example.php config/database.php
```

Edit `config/database.php` with your MySQL credentials:

| Constant | Default | Description |
|----------|---------|-------------|
| `DB_HOST` | `localhost` | Database server |
| `DB_NAME` | `chama_system` | Database name |
| `DB_USER` | `root` | Database username |
| `DB_PASS` | *(empty)* | Database password |

The application also reads from environment variables (`DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`) which is recommended for production.

### 5. Set Up Base URL

Edit `includes/constants.php` and update `BASE_URL` to your deployment path, for example:

```php
define('BASE_URL', 'http://localhost/chama-system/');
```

### 6. Set Directory Permissions

Ensure these directories are writable by the web server:

```bash
chmod -R 775 uploads/
```

### 7. Configure SMTP (Optional — Required for Emails)

1. Log in as Super Admin
2. Navigate to **Settings → Email**
3. Enter your SMTP credentials
4. Click **Test Email** to verify

Email features include: member registration verification, approval notifications, credentials delivery, and dividend notifications.

### 8. Access the Application

```
http://localhost/chama-system/
```

## Default Credentials (Development Only)

After running `database/sql/seed.sql`, the following demo accounts are available. **Change these immediately in production.**

| Role | Email | Password | Group Code |
|------|-------|----------|------------|
| Super Admin | admin@chama.com | Admin@123 | CHAMA001 |
| Admin | manager@chama.com | Admin@123 | CHAMA001 |
| Chairperson | chair@chama.com | Admin@123 | CHAMA001 |
| Secretary | secretary@chama.com | Admin@123 | CHAMA001 |
| Treasurer | treasurer@chama.com | Admin@123 | CHAMA001 |
| Loan Officer | loanofficer@chama.com | Admin@123 | CHAMA001 |
| Member | member1@chama.com | Admin@123 | CHAMA001 |
| Auditor | auditor@chama.com | Admin@123 | CHAMA001 |

> **⚠️ WARNING:** The demo credentials above are for **local development only**. In production:
> 1. Delete or re-seed the database with real credentials
> 2. Set `APP_ENV=production` to hide the development credential hint on the login page
> 3. Configure strong unique passwords for all users

## Project Structure

```
chama-system/
├── accounting/         # Accounting module (income, expenses, journal, ledger, trial balance)
├── ajax/               # AJAX endpoints
├── assets/             # CSS, JS, images, FPDF
│   ├── css/            # style.css
│   ├── js/             # main.js, datatables-helper.js
│   └── fpdf/           # FPDF library
├── config/             # Database configuration (database.php is gitignored)
├── controllers/        # Business logic
├── database/           # SQL schema and seed data
├── docs/               # Documentation (PDF guides, manuals)
├── includes/           # Core: auth, functions, CSRF, security, validation, session
├── migrations/         # Schema migration scripts
├── models/             # Data models
├── reports/            # Report pages
├── uploads/            # Member photos and file uploads
├── views/              # Layout (header, sidebar, navbar, footer)
├── .gitignore          # Excludes config/database.php, vendor/, logs/, uploads/, etc.
├── .htaccess           # Apache security rules
├── index.php           # Landing page / homepage CMS
├── login.php           # Authentication
├── dashboard.php       # Admin dashboard
├── members.php         # Member CRUD
├── users.php           # User account management
├── shares.php          # Share purchases
├── share-products.php  # Share product definitions
├── dividends.php       # Dividend declarations
├── settings.php        # System settings + homepage CMS
└── README.md
```

## Security

| Layer | Implementation |
|-------|---------------|
| **CSRF** | Token regenerated per request; verified on all mutations |
| **SQL Injection** | PDO prepared statements with `EMULATE_PREPARES = false` |
| **Passwords** | `password_hash()` bcrypt (cost 10) |
| **Session** | HttpOnly, SameSite=Strict, timeout, login lockout |
| **Authorization** | Role-permission bridge table; Super Admin auto-passes |
| **File Uploads** | Extension whitelist, size limit, randomized filenames |
| **Audit Trail** | All mutations logged to `audit_logs` table |
| **Headers** | X-Frame-Options: DENY, X-Content-Type-Options: nosniff |

## License

Private — All rights reserved.

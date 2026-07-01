# Administrator Guide

**Advanced Chama Management System** — For Super Admin and Admin users.

---

## 1. Roles and Responsibilities

As an administrator, you have full access to all system features. Your responsibilities include:

- **User management** — Creating and managing user accounts
- **System settings** — Configuring application behaviour
- **Security** — Monitoring audit logs and managing permissions
- **Backup** — Ensuring data is backed up regularly
- **Role management** — Assigning roles to users

---

## 2. Managing Users

### 2.1 Creating a User

1. Go to **System > Users** in the sidebar.
2. Click **"Add User"**.
3. Fill in:
   - **Username** — Unique login name (required)
   - **Email** — Unique email address (required)
   - **Role** — Select from available roles (required)
   - **Password** — Leave blank to use the default (`Admin@123`)
   - **Phone** — Contact number
   - **Group Code** — The user's chama group code
4. Click **"Save User"**.

### 2.2 Editing a User

1. Click the **edit icon** next to the user.
2. Update fields as needed. Leave password blank to keep the current password.
3. Click **"Save"**.

### 2.3 Suspending/Activating a User

- Click the **toggle status** button to suspend or activate a user account.
- Suspended users cannot log in.

### 2.4 Available Roles

| ID | Role | Description |
|----|------|-------------|
| 1 | Super Admin | Full system access |
| 2 | Admin | System administrator |
| 3 | Chairperson | Group leader |
| 4 | Secretary | Records keeper |
| 5 | Treasurer | Finance manager |
| 6 | Loan Officer | Loan management |
| 7 | Member | Regular member |
| 8 | Auditor | Financial reviewer |

---

## 3. System Settings

### 3.1 General Settings

Go to **System > Settings > General** to configure:

- **Site Name** — Changes the browser title
- **Site Tagline** — Subtitle displayed on the login page
- **Currency** — Affects how all monetary values are displayed
- **Currency Symbol** — The symbol placed before amounts
- **Timezone** — Affects all date/time displays
- **Date Format** — How dates appear throughout the system

### 3.2 Branding

Go to **Settings > Branding** to customise:

- **Primary Color** — Main navigation and heading colour (default: `#161950`)
- **Secondary Color** — Button and accent colour (default: `#465fff`)
- **Logo** — Upload a logo image (appears on login page)
- **Favicon** — Upload a browser tab icon

### 3.3 Security

Go to **Settings > Security** to configure:

- **Max Login Attempts** — Failed attempts before account lockout (default: 5)
- **Session Timeout** — Minutes of inactivity before auto-logout (default: 60)
- **Password Min Length** — Minimum password length (default: 8)

### 3.4 Email (SMTP)

Go to **Settings > Email** to enter mail server details. Note: email sending is not currently functional even with valid SMTP settings.

### 3.5 Backup

Go to **Settings > Backup**:

- Toggle **Auto Backup** on/off
- Set **Backup Frequency** (daily/weekly/monthly)
- Click **"Run Manual Backup"** to create an immediate SQL backup
- Backup files appear in a list below the settings form

---

## 4. Managing Loan Products

1. Go to **Loans > Loan Products** in the sidebar.
2. Click **"Add Product"**.
3. Configure:
   - **Name** — Product name (e.g., "Emergency Loan")
   - **Interest Rate** — Percentage (e.g., 5.00)
   - **Interest Type** — Flat or Reducing
   - **Max/Min Amount** — Loan amount limits
   - **Max Tenure** — Maximum repayment period in months
   - **Late Penalty** — Penalty percentage for late payments
   - **Grace Period** — Days before a payment is considered late
   - **Processing Fee** — Percentage charged upfront
   - **Description** — Terms and conditions
   - **Requirements** — Documents or conditions for eligibility
4. Click **"Save"**.

---

## 5. Managing Contribution Types

Contribution types are created directly in the database. To add a new type, insert a row into the `contribution_types` table with:

- **name** — Display name (e.g., "Monthly Savings")
- **slug** — URL-friendly name (e.g., "monthly_savings")
- **frequency** — daily, weekly, monthly, quarterly, yearly, or custom
- **amount** — Default amount
- **status** — active or inactive

---

## 6. Monitoring Audit Logs

1. Go to **System > Audit Logs** in the sidebar.
2. Use filters:
   - **Action** — Create, Update, Delete, Login, Logout, Export, Approve, Reject, Disburse
   - **Table** — The database table that was changed
   - **User** — Filter by user ID
   - **Date Range** — Start and end dates
3. The log shows:
   - **User** — Who made the change
   - **Action** — What was done
   - **Table** — Which table was affected
   - **Record ID** — Which record was changed
   - **Description** — Summary of the change
   - **IP Address** — Where the change was made from
   - **Date** — When it happened

---

## 7. Database Backup and Restore

### 7.1 Manual Backup (via Settings)

1. Go to **Settings > Backup**.
2. Click **"Run Manual Backup"**.
3. The system creates a complete SQL dump of the database.
4. The backup file appears in the backup list with timestamp and size.

### 7.2 Restore

The system does **not** include a restore interface. To restore from a backup:

1. Open **phpMyAdmin** (`http://localhost/phpmyadmin`).
2. Select the `chama_system` database.
3. Click the **"Import"** tab.
4. Choose the backup SQL file.
5. Click **"Go"** to execute the restore.

### 7.3 Manual Backup via phpMyAdmin

1. Open phpMyAdmin.
2. Select the `chama_system` database.
3. Click the **"Export"** tab.
4. Choose **"Quick"** method and **SQL** format.
5. Click **"Go"** to download the backup.

---

## 8. Security Best Practices

- Change the default admin password immediately after installation.
- Use strong passwords (12+ characters with mixed case, numbers, and symbols).
- Regularly review the **Audit Logs** for suspicious activity.
- Keep the server and PHP version up to date.
- Restrict access to the `config/database.php` file (contains database credentials).
- Enable HTTPS on the production server.
- Schedule regular database backups (use the backup frequency setting).

---

## 9. Initial Setup Checklist

- [ ] Import `database/sql/schema.sql` to create all tables
- [ ] Import `database/sql/seed.sql` for demo data
- [ ] Log in with `admin@chama.com` / `Admin@123` / `CHAMA001`
- [ ] Change the admin password
- [ ] Configure **Settings > General** (site name, currency, timezone)
- [ ] Configure **Settings > Branding** (colours, logo)
- [ ] Configure **Settings > Security** (login attempts, timeout)
- [ ] Create user accounts for all group officers
- [ ] Review and create **Loan Products** as needed
- [ ] Add members through **Members** page
- [ ] Run a manual backup and verify the file was created

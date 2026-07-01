# Quick Start Guide

## System Access
- **URL:** `http://localhost/chama-system/`
- **Super Admin:** jacksonlopito@gmail.com / admin123 / CHAMA001

## First-Time Admin Tasks

### 1. Configure System
Navigate to **Settings** → configure:
- General: Group name, code, currency
- Email: SMTP settings (required for all email features)
- Security: Password policy, session timeout

### 2. Add Members
Navigate to **Members** → **Add Member**
- Fill personal + contact details
- Save → status = 'active'

### 3. Approve Members (if registration used)
1. Go to **Members**
2. Find member with status = 'pending_approval'
3. Click **Approve**
4. Enter password + confirm password
5. System creates user account automatically

### 4. Create User Accounts
Navigate to **Users** → **Add User**
- Link to existing member (optional)
- Assign role

### 5. Set Up Share Products
Navigate to **Share Products** → **Add Product**
- Define name, price, max per member

### 6. Record First Meeting
Navigate to **Meetings** → **Schedule Meeting**

## Daily Operations

### Recording Contributions
1. Go to **Contributions**
2. Click **Record Contribution**
3. Select member, enter amount, payment method
4. Save

### Processing Loans
1. Go to **Loans**
2. Click **Apply** → fill details
3. **Approve** → **Disburse**
4. Record repayments as they come

### Buying Shares
1. Go to **Shares → Purchases**
2. Click **Buy Shares**
3. Select member, product, quantity
4. Certificate auto-generated

### Declaring Dividends
1. Go to **Shares → Dividends**
2. Click **Declare Dividend**
3. Select product, per-share amount, financial year
4. Payment records auto-created
5. Click **Pay** to mark all as paid

## Common Workflows

### Member Registration → Login (Full Flow)
1. Member registers at `register.php`
2. Verification email sent (requires SMTP)
3. Member verifies email → status = 'email_verified'
4. Admin approves member in Members → approves with password
5. System creates user + sends credentials email
6. Member logs in with credentials

### End-of-Month Accounting
1. Record all income (Accounting → Income)
2. Record all expenses (Accounting → Expenses)
3. Post journal entries if needed (Accounting → Journal)
4. Run Trial Balance (Accounting → Trial Balance)
5. Generate reports (Reports menu)

## Role-Specific Guides

### Super Admin
- Full access to everything
- Start with Settings configuration
- Create other admin users
- Monitor audit logs

### Treasurer
- Manage contributions, loans, accounting
- Declare dividends, manage shares
- Generate financial reports

### Secretary
- Schedule meetings, record minutes
- Manage member records
- Track attendance

### Member
- Update profile
- View own contributions and shares
- Buy shares (if enabled)
- View loan status

### Auditor
- View all reports (read-only)
- View audit logs
- Review trial balance

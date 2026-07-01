# Menu Index

**Advanced Chama Management System** — Complete navigation reference.

---

## Sidebar Menu

| Menu Item | Icon | URL | Permission Required | Notes |
|-----------|------|-----|-------------------|-------|
| **MAIN MENU** | | | | |
| Dashboard | ⬜ | `dashboard.php` | `view_dashboard` (all) | Default landing page after login |
| Members | 👥 | `members.php` | `view_members` | Member management |
| **FINANCE** | | | | |
| Contributions | 🐷 | `contributions.php` | `view_contributions` | Record and view contributions |
| Shares | 📊 | `shares.php` | `view_shares` | Share purchases |
| Loans ▸ | 🤝 | _(expandable)_ | `view_loans` | |
| ├ Loan Products | | `loan-products.php` | `view_loans` | Define loan terms |
| ├ Applications | | `loan-applications.php` | `view_loans` | Apply/approve/disburse |
| ├ Active Loans | | `loans.php` | `view_loans` | Loan list by status |
| └ Repayments | | `loan-repayments.php` | `view_loan_payments` | Record payments |
| **ORGANIZATION** | | | | |
| Meetings ▸ | 📅 | _(expandable)_ | `view_meetings` | |
| ├ All Meetings | | `meetings.php` | `view_meetings` | List/manage meetings |
| ├ Schedule Meeting | | `meetings.php?action=create` | `create_meetings` | Create new meeting |
| └ Attendance | | `attendance.php` | `record_attendance` | Note: file may not exist separately |
| **COMMUNICATION** | | | | |
| Messaging | ✉️ | `messaging.php` | `view_messages` | Inbox/sent/compose |
| Announcements | 📢 | `announcements.php` | `view_announcements` | Group announcements |
| **MANAGEMENT** | | | | |
| Accounting ▸ | 📒 | _(expandable)_ | `view_accounting` | |
| ├ Expenses | | `accounting/expenses.php` | `view_accounting` | Record expenses |
| ├ Income | | `accounting/income.php` | `view_accounting` | Record income |
| ├ Journal | | `accounting/journal.php` | `view_accounting` | Double-entry entries |
| ├ Ledger | | `accounting/ledger.php` | `view_accounting` | Account ledger |
| ├ Trial Balance | | `accounting/trial-balance.php` | `view_accounting` | Trial balance report |
| ├ Financial Reports | | `accounting/reports.php` | `view_accounting` | Note: file may not exist |
| └ Bank Accounts | | `accounting/bank-accounts.php` | `view_bank_accounts` | Bank account management |
| Reports ▸ | 📄 | _(expandable)_ | `view_reports` | |
| ├ Contribution Report | | `reports/contributions.php` | `view_reports` | Contribution summaries |
| ├ Loan Report | | `reports/loans.php` | `view_reports` | Loan summaries |
| ├ Member Report | | `reports/members.php` | `view_reports` | Member directory |
| ├ Meeting Report | | `reports/meetings.php` | `view_reports` | Meeting records |
| └ Financial Report | | `reports/financial.php` | `view_reports` | Period financial summary |
| **SYSTEM** | | | | |
| Settings | ⚙️ | `settings.php` | `view_settings` | System configuration |
| Users | 🛡️ | `users.php` | `manage_users` | User account management |
| Audit Logs | 📋 | `audit-logs.php` | `view_audit_logs` | Change history |
| Notifications | 🔔 | `notifications.php` | (all) | All notifications |
| **ACCOUNT** | | | | |
| Profile | 👤 | `profile.php` | (all) | User profile |
| Sign Out | 🚪 | `logout.php` | (all) | Logout |

---

## Top Navigation Bar

| Item | Icon | URL | Description |
|------|------|-----|-------------|
| Sidebar toggle | ☰ | `#` | Show/hide sidebar |
| Mobile sidebar | ☰ | `#` | Show sidebar on small screens |
| Global search | 🔍 | `ajax/global-search.php` | Search members, users, loans |
| Dark mode | 🌙/☀️ | `#` | Toggle dark/light theme |
| Notifications | 🔔 | `notifications.php` | Dropdown with recent notifications |
| Messages | ✉️ | `messaging.php` | Link to messaging |
| Fullscreen | ⛶ | `#` | Toggle fullscreen mode |
| User menu | 👤 | `profile.php` | Dropdown: Profile, Settings, Activity Log, Sign Out |

---

## Public Pages (No Login Required)

| Page | URL | Description |
|------|-----|-------------|
| Landing | `index.php` | Marketing page with features, FAQ, contact form |
| Login | `login.php` | Login form |
| Forgot Password | `forgot-password.php` | Request password reset |
| Reset Password | `reset-password.php` | Complete password reset |
| Contact API | `ajax/contact.php` | Contact form handler (POST only) |

---

## Icon Reference

| Icon | Meaning |
|------|---------|
| ⬜ (grid) | Dashboard |
| 👥 (users) | Members |
| 🐷 (piggy) | Contributions |
| 📊 (chart) | Shares |
| 🤝 (hand) | Loans |
| 📅 (calendar) | Meetings |
| ✉️ (envelope) | Messaging |
| 📢 (bullhorn) | Announcements |
| 📒 (book) | Accounting |
| 📄 (file) | Reports |
| ⚙️ (gear) | Settings |
| 🛡️ (shield) | Users |
| 📋 (clipboard) | Audit Logs |
| 🔔 (bell) | Notifications |
| 👤 (user) | Profile |
| 🚪 (door) | Sign Out |
| 🌙/☀️ | Dark/Light mode |
| 🔍 | Search |
| ⛶ | Fullscreen |

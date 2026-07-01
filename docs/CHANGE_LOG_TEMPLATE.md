# Change Log Template

**Advanced Chama Management System**

Use this template to document changes for each version update.

---

## Version X.Y.Z — YYYY-MM-DD

### Added
- 
- 

### Changed
- 
- 

### Fixed
- 
- 

### Removed
- 
- 

### Security
- 
- 

---

## [1.0.0] — 2024-01-01

### Added
- Initial release of the Advanced Chama Management System
- Member management (CRUD, photo upload, search, filter, pagination)
- Contribution recording with auto-generated receipt numbers
- Share purchase management with certificates
- Loan management: products, applications, multi-level approval, disbursement, repayment
- Loan amortization schedules with reducing balance and flat rate calculation
- Meeting scheduling with dynamic agenda items, attendance, and minutes
- Internal messaging (inbox/sent/compose) with read tracking
- Announcements with pinning, priority levels, and role targeting
- Notifications system (in-app)
- Role-based access control with 9 roles and 30+ permissions
- Double-entry accounting: journal entries, general ledger, trial balance
- Expense and income tracking with auto receipt numbers
- Bank account management
- Reports module: contributions, loans, members, meetings, financial
- PDF export using FPDF library
- Dashboard with stat cards, charts (Chart.js), and recent activity
- Global search across members, users, and loans
- Settings: general, branding, security, email/SMTP, backup
- User management with role assignment
- Audit log viewer with filters
- Login security: CSRF tokens, bcrypt passwords, session timeout, account lockout
- "Remember Me" cookie authentication
- Forgot password / reset password flow
- Profile management with photo upload and activity history
- Responsive design (Bootstrap 5) with dark mode
- Landing page with contact form
- Public contact form (AJAX endpoint)
- Database: 46 tables with indexes and foreign keys
- Seed data: default roles, permissions, users, sample members, contributions, loans

### Notes
- Email sending via SMTP is configured but not functional
- SMS notifications are configured but not functional
- Several database tables (assets, liabilities, documents, dividends, votes, beneficiaries) have no UI
- Auto-backup cron job is not installed (manual backup only)

# Reports Module

**Files:**
- `reports/members.php` — Member listings
- `reports/meetings.php` — Meeting summaries
- `reports/loans.php` — Loan portfolio
- `reports/contributions.php` — Contribution summaries

**Access:** Super Admin, Admin, Chairperson, Secretary, Treasurer, Loan Officer, Auditor

| Report | File | Description |
|--------|------|-------------|
| Member Report | `reports/members.php` | All members with status, contact, join date |
| Meeting Report | `reports/meetings.php` | Meeting attendance and minutes |
| Loan Report | `reports/loans.php` | Loan portfolio by status, member |
| Contribution Report | `reports/contributions.php` | Contributions by member, date range |

## Common Features (All Reports)

| Feature | Description |
|---------|-------------|
| DataTable | Server-side processed with search, sort, pagination |
| Filter | Date range, status, member filters |
| Export | CSV, Excel, PDF export buttons |
| Print | Print-friendly view |
| Column Toggle | Show/hide columns |

## Member Report
### Columns
Member No, Name, Phone, Email, County, Status, Joined Date

### Filters
- Status (active/suspended/terminated)
- County
- Date range (joined)

## Meeting Report
### Columns
Title, Date, Time, Venue, Attendance Count, Status

### Filters
- Status
- Date range

## Loan Report
### Columns
Loan No, Member, Amount, Interest Rate, Duration, Status, Balance, Disbursement Date

### Filters
- Status
- Member
- Date range

## Contribution Report
### Columns
Receipt No, Member, Amount, Method, Date, Reference, Status

### Filters
- Member
- Payment Method
- Date range
- Status

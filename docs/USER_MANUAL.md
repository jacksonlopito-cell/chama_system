# Advanced Chama Management System — User Manual

---

## Chapter 1: Introduction

### 1.1 What Is the Chama System?

The Advanced Chama Management System is a web-based application designed to help informal savings groups (chamas) in Kenya manage their financial and administrative affairs online. It replaces paper-based record-keeping with a secure, centralised digital platform accessible from any device with a web browser.

### 1.2 Why It Exists

Most chamas still rely on handwritten ledgers, spreadsheets, or WhatsApp messages to track member contributions, loans, meeting minutes, and share purchases. This leads to errors, disputes, and lost records. The Chama System solves these problems by providing:

- A single source of truth for all financial transactions
- Role-based access so the right people see the right information
- Automated receipt numbers, loan schedules, and audit trails
- Digital reports that can be printed or exported to PDF

### 1.3 Main Objectives

1. **Record Keeping** — Maintain accurate, permanent records of every member, contribution, loan, share purchase, meeting, and expense.
2. **Financial Management** — Track cash flow with double-entry accounting, trial balances, and ledgers.
3. **Loan Management** — Handle the full loan lifecycle: application → approval (multi-level) → disbursement → repayment → completion.
4. **Transparency** — Give every member visibility into their own contributions, loans, and shares.
5. **Compliance** — Maintain audit logs of all changes for accountability.

### 1.4 Who Should Use It

| Role | Typical User |
|------|-------------|
| Super Admin | System owner / IT administrator |
| Admin | Group manager with full operational access |
| Chairperson | Group leader who oversees approvals |
| Secretary | Records keeper who schedules meetings and manages members |
| Treasurer | Finance officer who records contributions and expenses |
| Loan Officer | Person who manages loan applications and repayments |
| Member | Ordinary group member |
| Auditor | Independent person reviewing financial records |

### 1.5 Benefits

- **Time saved** — Record a contribution in under 30 seconds
- **Accuracy** — Auto-generated receipt numbers and running balances
- **Trust** — Every change is logged with who made it and when
- **Accessibility** — Works on phones, tablets, and computers
- **Cost** — Free, self-hosted, no monthly subscription

---

## Chapter 2: System Requirements

### 2.1 Supported Browsers

- Google Chrome (version 90 or later)
- Mozilla Firefox (version 88 or later)
- Microsoft Edge (version 90 or later)
- Safari (version 14 or later)
- Opera (version 76 or later)

### 2.2 Screen Resolutions

- **Desktop**: 1024 × 768 or higher (recommended 1366 × 768)
- **Tablet**: 768 × 1024 or higher
- **Mobile**: 360 × 640 or higher (responsive layout)

### 2.3 Internet Requirements

- Broadband or mobile data connection (3G or better)
- Minimum speed: 256 Kbps

### 2.4 Recommended Devices

- Desktop or laptop computer for administration tasks
- Smartphone or tablet for member self-service (viewing balances, reading announcements)

---

## Chapter 3: Getting Started

### 3.1 How to Access the System

1. Open your web browser.
2. Type the system URL (e.g., `http://localhost/chama-system/` or the domain provided by your administrator).
3. The landing page will appear with information about the system.

### 3.2 Opening the Website

The landing page (`index.php`) is publicly accessible and contains:

- **Hero section** — Welcome message with "Get Started" and "Sign In" buttons
- **About section** — Brief description of what the system does
- **Services section** — Overview of core features (members, contributions, loans, meetings, reports, accounting)
- **Statistics bar** — Membership, contributions, loans, years established counts
- **Testimonials** — Placeholder testimonials
- **FAQ** — Common questions about the system
- **Contact form** — Send a message to the system administrator
- **Footer** — Quick links and social media icons

### 3.3 Logging In

1. Click the **"Sign In"** button (top-right corner or hero section).
2. On the login page, enter:
   - **Email** — Your registered email address (e.g., `admin@chama.com`)
   - **Password** — Your account password (e.g., `Admin@123`)
   - **Group Code** — Your chama group code (e.g., `CHAMA001`)
3. Optionally tick **"Remember me"** to stay logged in for 30 days.
4. Click **"Sign In"**.

[Insert Screenshot: Login Page]

If the credentials are correct, you will be redirected to the **Dashboard**.

> **Note**: If you see "Invalid email, password, or group code", check that all three fields are correct. The group code is case-sensitive.

### 3.4 Logging Out

1. Click your profile photo or initials in the top-right corner.
2. Click **"Sign Out"** from the dropdown menu.
3. You will be returned to the login page.

You can also log out by visiting `logout.php` directly.

### 3.5 Forgot Password

1. On the login page, click **"Forgot password?"**.
2. Enter your registered email address.
3. Click **"Send Reset Link"**.
4. A reset link will be generated (note: in the current version, the email is not actually sent; an administrator must provide the reset link manually).

> **Note**: The password reset feature stores a token in the database but does not send emails automatically. Contact your administrator for the reset link.

### 3.6 Reset Password

1. Open the reset link provided by your administrator (URL format: `reset-password.php?token=...`).
2. Enter a **new password**.
3. Confirm the **new password**.
4. Click **"Reset Password"**.
5. On success, you will be redirected to the login page.

### 3.7 Changing Password

1. Log in and go to **Profile** (click your name in the top-right corner, then "My Profile").
2. Scroll to the **"Change Password"** section.
3. Enter:
   - **Current Password** — Your existing password
   - **New Password** — At least 8 characters, must contain uppercase, lowercase, digit, and special character
   - **Confirm Password** — Same as new password
4. Click **"Update Password"**.

### 3.8 Updating Profile

1. Go to **Profile** from the user menu.
2. Update any of the following:
   - **Username** — Your display name
   - **Email** — Your contact email
   - **Phone** — Your phone number
3. Click **"Update Profile"**.

### 3.9 Uploading Profile Photo

1. Go to **Profile**.
2. In the **"Update Profile"** section, click **"Choose File"** next to the Photo field.
3. Select an image file (JPG, PNG, or GIF, max 5 MB).
4. Click **"Update Profile"**.
5. Your photo will appear in the top navigation bar and on your profile.

---

## Chapter 4: Dashboard Overview

The Dashboard is the first page you see after logging in. It provides a summary of key information.

### 4.1 Statistics Cards

At the top of the dashboard, you will see four cards:

| Card | Description |
|------|-------------|
| **Total Members** | Number of active members in your group |
| **Total Contributions** | Sum of all member contributions |
| **Total Loans** | Sum of all loan amounts disbursed |
| **Total Shares** | Total value of shares purchased |

The numbers update automatically as data is entered into the system.

### 4.2 Charts

- **Contribution Trends** — A bar chart showing monthly contributions over time.
- **Loan Status Distribution** — A pie or doughnut chart showing the breakdown of loan statuses (pending, active, disbursed, paid, defaulted).

### 4.3 Recent Activities

A table showing the latest records across the system:

- **Recent Contributions** — Last 5 recorded contributions with member name, type, amount, and date.
- **Recent Loans** — Last 5 loan applications with member name, product, amount, and status.
- **Upcoming Meetings** — Next scheduled meetings.

### 4.4 Quick Actions

Depending on your role, the dashboard may show quick action buttons such as:

- **Record Contribution**
- **New Member**
- **Schedule Meeting**
- **View Reports**

### 4.5 Notifications

The bell icon in the top-right corner shows a red dot when you have unread notifications. Click it to see the most recent notifications. Click "View All Notifications" to go to the full notifications page.

### 4.6 Navigation Shortcuts

The sidebar on the left provides access to all system modules. The top navigation bar includes:

- **Search bar** — Search members, users, and loans
- **Dark mode toggle** — Switch between light and dark themes
- **Fullscreen toggle** — Enter or exit fullscreen mode

---

## Chapter 5: Navigation

### 5.1 Main Menu (Sidebar)

The sidebar is divided into labelled sections:

#### Main Menu
| Item | Icon | Description | Page |
|------|------|-------------|------|
| Dashboard | `fas fa-th-large` | Summary overview | `dashboard.php` |
| Members | `fas fa-users` | Member management | `members.php` |

#### Finance
| Item | Icon | Description | Page |
|------|------|-------------|------|
| Contributions | `fas fa-piggy-bank` | Record and view member contributions | `contributions.php` |
| Shares | `fas fa-chart-pie` | Share purchases and certificates | `shares.php` |
| Loans ▸ | `fas fa-hand-holding-usd` | Loan management (expandable) | — |
| ├ Loan Products | | Define loan types and terms | `loan-products.php` |
| ├ Applications | | View and process loan applications | `loan-applications.php` |
| ├ Active Loans | | Track active loans | `loans.php` |
| └ Repayments | | Record loan payments | `loan-repayments.php` |

#### Organization
| Item | Icon | Description | Page |
|------|------|-------------|------|
| Meetings ▸ | `fas fa-calendar-check` | Meeting management (expandable) | — |
| ├ All Meetings | | View and manage meetings | `meetings.php` |
| ├ Schedule Meeting | | Create a new meeting | `meetings.php?action=create` |
| └ Attendance | | Record member attendance | `attendance.php` |

#### Communication
| Item | Icon | Description | Page |
|------|------|-------------|------|
| Messaging | `fas fa-envelope` | Internal messaging (inbox/sent) | `messaging.php` |
| Announcements | `fas fa-bullhorn` | Group announcements | `announcements.php` |

#### Management
| Item | Icon | Description | Page |
|------|------|-------------|------|
| Accounting ▸ | `fas fa-book` | Financial accounting (expandable) | — |
| ├ Expenses | | Record group expenses | `accounting/expenses.php` |
| ├ Income | | Record other income | `accounting/income.php` |
| ├ Journal | | Double-entry journal entries | `accounting/journal.php` |
| ├ Ledger | | General ledger for each account | `accounting/ledger.php` |
| ├ Trial Balance | | Trial balance report | `accounting/trial-balance.php` |
| ├ Financial Reports | | Combined financial report | `accounting/reports.php` |
| └ Bank Accounts | | Manage group bank accounts | `accounting/bank-accounts.php` |
| Reports ▸ | `fas fa-file-alt` | Report generation (expandable) | — |
| ├ Contribution Report | | Contribution summaries | `reports/contributions.php` |
| ├ Loan Report | | Loan summaries | `reports/loans.php` |
| ├ Member Report | | Member lists | `reports/members.php` |
| ├ Meeting Report | | Meeting records | `reports/meetings.php` |
| └ Financial Report | | Financial summaries | `reports/financial.php` |

#### System
| Item | Icon | Description | Page |
|------|------|-------------|------|
| Settings | `fas fa-cog` | System configuration | `settings.php` |
| Users | `fas fa-user-shield` | User account management | `users.php` |
| Audit Logs | `fas fa-history` | Change history and logs | `audit-logs.php` |
| Notifications | `fas fa-bell` | All notifications | `notifications.php` |

#### Account
| Item | Icon | Description | Page |
|------|------|-------------|------|
| Profile | `fas fa-user` | Your profile and activity | `profile.php` |
| Sign Out | `fas fa-sign-out-alt` | Log out | `logout.php` |

### 5.2 Submenus

Menu items with a `▸` arrow have submenus. Click the arrow or the item text to expand/collapse the submenu. The submenu items appear indented below the parent.

### 5.3 Breadcrumbs

Breadcrumbs are not displayed on standard pages. The active menu item is highlighted in the sidebar to show your current location.

### 5.4 Search

A global search bar is available in the top navigation bar. It searches across:

- **Members** — By name, member number, or phone number
- **Users** — By username or email
- **Loans** — By member name

Type at least 2 characters to begin searching. Results appear in a dropdown with links to the relevant pages.

### 5.5 Filters

Many list pages (Members, Contributions, Loans, Meetings, Reports) include filter forms at the top. Filters narrow down the displayed records. Common filters include:

- **Search** — Free text search
- **Status** — Filter by record status
- **Date range** — Filter by date (from/to)
- **Dropdown** — Filter by category, type, or member

### 5.6 Pagination

When a list contains more records than the page limit (20 per page by default), pagination controls appear at the bottom. Use the page numbers or Previous/Next arrows to navigate between pages.

---

## Chapter 6: Member Management

### 6.1 Adding Members

1. Go to **Members** in the sidebar.
2. Click **"Add New Member"**.
3. Fill in the required fields (marked with `*`):
   - **First Name** — Member's first name
   - **Last Name** — Member's surname
   - **Phone** — Mobile number
   - **Date Joined** — Date the member joined the group
4. Fill in optional fields as needed:
   - **Gender** — Male / Female / Other
   - **National ID** — National identity card number
   - **Passport** — Passport number (if applicable)
   - **Date of Birth** — Birth date
   - **Email** — Email address
   - **Occupation** — Job title
   - **Employer** — Employer name
   - **Address** — Physical address
   - **County** — County of residence
   - **Sub-County** — Sub-county of residence
   - **Ward** — Ward of residence
   - **Photo** — Profile photograph (image file, max 5 MB)
   - **Emergency Name** — Emergency contact person
   - **Emergency Phone** — Emergency contact phone
   - **Emergency Relation** — Relationship to emergency contact
5. Click **"Save Member"**.

[Insert Screenshot: Add Member Form]

The member will receive a unique **Member Number** (e.g., `CHM-2024-0001`) and be listed on the Members page.

### 6.2 Editing Members

1. Go to **Members**.
2. Find the member in the list.
3. Click the **"Edit"** button (pencil icon) in the Actions column.
4. Update any fields.
5. Click **"Save"**.

### 6.3 Viewing Members

The Members page shows a table with the following columns:

- Member Number
- Photo
- Name (First + Last)
- Phone
- Email
- National ID
- Status
- Date Joined
- Actions

Click a member's name or the **"View"** button (eye icon) to see their full profile, including:

- Personal details
- Emergency contact information
- Contribution history
- Loan history
- Share holdings

### 6.4 Searching Members

1. Type a name, phone number, or member number into the **"Search"** field above the member table.
2. Press Enter or click the search icon.
3. The table filters to show matching members.

### 6.5 Filtering by Status

Use the **"Status"** dropdown to filter by:

- **Active** — Currently active members
- **Inactive** — Members who have left or been removed
- **Pending** — Members awaiting approval
- **Suspended** — Members who are temporarily suspended

### 6.6 Suspending/Activating Members

- Click the **"Toggle Status"** button in the Actions column to quickly switch between active and inactive/suspended.
- You can also use the **"Suspend"** or **"Activate"** buttons on the member's profile page.

### 6.7 Deleting (Deactivating) Members

- Click the **"Delete"** button (trash icon) in the Actions column.
- Confirm the deletion.
- The member's status is set to **Inactive** (soft delete). The record remains in the database for audit purposes.

### 6.8 Printing Member Profiles

Click the **"View"** button, then use your browser's Print function (Ctrl+P or Cmd+P) to print the member profile.

### 6.9 Uploading Photos

1. Edit the member's record.
2. Under **"Photo"**, click **"Choose File"**.
3. Select an image (JPG, PNG, or GIF, max 5 MB).
4. Save the member record.

### 6.10 Viewing Contribution History

On the member's profile page, scroll to the **"Contributions"** section to see all contributions made by that member, including dates, amounts, types, and receipt numbers.

### 6.11 Viewing Loan History

On the member's profile page, scroll to the **"Loans"** section to see all loan applications by that member, including amounts, statuses, and balances.

---

## Chapter 7: Contributions

### 7.1 Recording Contributions

1. Go to **Contributions** in the sidebar.
2. Click **"Record Contribution"**.
3. Fill in:
   - **Member** — Select the member from the dropdown (required)
   - **Contribution Type** — Select the type (required, e.g., Monthly Savings, Welfare Fund)
   - **Amount** — Enter the amount (required, must be greater than 0)
   - **Date** — Contribution date (defaults to today)
   - **Payment Method** — Cash, M-Pesa, Bank, Cheque, or Other
   - **Reference** — Transaction reference (e.g., M-Pesa code)
   - **Notes** — Any additional notes
4. Click **"Save"**.

[Insert Screenshot: Record Contribution Form]

A receipt number is automatically generated (e.g., `RCP-2024-0001`). The member's contribution balance is updated automatically.

### 7.2 Viewing Balances

Each member's total contributions by type can be viewed:

- On the **Contributions** page, the table shows individual contribution records.
- On the **Member Profile** page, a summary of contribution balances is displayed.

### 7.3 Printing Receipts

Contribution receipts are visible on the contributions list. Use your browser's Print function to print a page containing the receipt.

### 7.4 Searching and Filtering Contributions

Use the filter form above the contributions table:

- **Search** — Search by member name or receipt number
- **Type** — Filter by contribution type
- The table updates automatically after filtering.

### 7.5 Contribution Reports

See **Chapter 11: Reports** for detailed contribution reporting.

---

## Chapter 8: Shares

### 8.1 Share Products

Before members can buy shares, an administrator must define share products. Go to the Shares page to see available products.

| Product | Price per Share | Minimum | Maximum |
|---------|----------------|---------|---------|
| Ordinary Shares | KSh 100.00 | 1 | 1,000 |
| Preference Shares | KSh 200.00 | 1 | 500 |

### 8.2 Buying Shares

1. Go to **Shares** in the sidebar.
2. Click **"Purchase Shares"**.
3. Fill in:
   - **Member** — Select the member (required)
   - **Product** — Select the share product (required)
   - **Number of Shares** — Enter quantity (required, at least the minimum)
   - **Purchase Date** — Date of purchase (defaults to today)
   - **Payment Method** — Cash, M-Pesa, Bank, or Contribution
4. Click **"Save"**.

A certificate number is automatically generated (e.g., `CERT-2024-0001`). The total amount is calculated as `shares × price`.

### 8.3 Viewing Share Holdings

The Shares page lists all share purchases with member name, product, quantity, total amount, certificate number, and purchase date.

### 8.4 Certificates

Each share purchase receives a unique certificate number. This can be used for record-keeping and dividend distribution.

### 8.5 Share Reports

Share purchases are included in the member profile and can be exported from the Reports section.

---

## Chapter 9: Loans

### 9.1 Loan Products

Administrators define loan products that determine the terms available to members. Products include:

| Product | Interest | Type | Max Amount | Max Tenure |
|---------|----------|------|-----------|------------|
| Emergency Loan | 5% | Flat | KSh 50,000 | 6 months |
| Development Loan | 8% | Reducing | KSh 500,000 | 24 months |
| Business Loan | 10% | Reducing | KSh 1,000,000 | 36 months |
| School Fees Loan | 6% | Flat | KSh 200,000 | 12 months |

Interest types:
- **Flat** — Interest is calculated on the original principal for the full tenure
- **Reducing** — Interest is calculated on the outstanding balance (more favourable to the borrower)

### 9.2 Loan Application

1. Go to **Loans > Applications** in the sidebar.
2. Click **"New Application"**.
3. Fill in:
   - **Member** — Select the borrowing member (required)
   - **Product** — Select the loan product (required)
   - **Amount** — Loan amount requested (required, must be within product limits)
   - **Tenure** — Repayment period in months
   - **Purpose** — Why the loan is needed (required)
   - **Application Date** — Defaults to today
4. Add **Guarantors** (optional):
   - Click **"Add Guarantor"** to add a guarantor member
   - Specify the **amount** each guarantor is guaranteeing
   - You can add multiple guarantors
5. Add **Collateral** (optional):
   - Click **"Add Collateral"** to add an item
   - Enter **name**, **description**, and **estimated value**
   - You can add multiple collateral items
6. Click **"Submit Application"**.

[Insert Screenshot: Loan Application Form]

### 9.3 Loan Requirements

- The member must have an active status
- The loan amount must be within the product's minimum and maximum range
- The tenure must be within the product's limits
- Guarantors and collateral are optional but recommended

### 9.4 Approval Process

Loan approval follows a multi-level workflow based on the group's roles:

1. **Pending** — Application submitted, awaiting review
2. **Secretary Approved** — Secretary reviews and approves
3. **Treasurer Approved** — Treasurer reviews and approves
4. **Chairperson Approved** — Chairperson gives final approval
5. **Disbursed** — Loan officer disburses the funds

The approval buttons appear on the loan detail page. Each approver can also **Reject** the loan with a reason.

### 9.5 Adding Guarantors

During loan application, you can add guarantors who will be responsible if the borrower defaults. Each guarantor:

- Must be an active member
- Guarantees a specified amount
- Status is tracked as pending/accepted/declined

### 9.6 Adding Collateral

Collateral items can be attached to a loan application:

- Name (e.g., "Title Deed", "Logbook")
- Description
- Estimated value

### 9.7 Repayment

1. Go to **Loans > Repayments** in the sidebar.
2. Click **"Record Payment"**.
3. Select the **Loan** from active/disbursed loans (shows loan number, member, and balance).
4. Enter:
   - **Amount** — Payment amount (required)
   - **Principal Amount** — Portion going to principal
   - **Interest Amount** — Portion going to interest
   - **Payment Date** — Date of payment
   - **Payment Method** — Cash, M-Pesa, Bank, Cheque
   - **Reference** — Transaction reference
   - **Notes** — Additional notes
5. Click **"Save"**.

A receipt number is generated automatically (e.g., `RCP-LN-0001`). The loan balance is reduced by the principal portion.

### 9.8 Loan Schedule

When a loan is approved and disbursed, a repayment schedule is generated showing:

- Installment number
- Due date
- Principal amount
- Interest amount
- Total payment
- Remaining balance
- Status (pending/partial/paid/overdue)

View the schedule on the loan detail page under the **"Schedule"** tab.

### 9.9 Penalties

The system calculates late payment penalties based on the loan product's `late_penalty` percentage. When recording a repayment, you can specify a penalty amount.

### 9.10 Loan Statements

Loan statements showing all payments and the outstanding balance are available on the loan detail page.

### 9.11 Loan History

The **Loans > Active Loans** page shows all loans grouped by status tabs:

- **Pending** — Awaiting approval
- **Active** — Currently being repaid
- **Disbursed** — Recently disbursed
- **Paid** — Fully repaid
- **Defaulted** — In default

Click a loan to view its full details, schedule, payments, guarantors, and collateral.

---

## Chapter 10: Meetings

### 10.1 Scheduling a Meeting

1. Go to **Meetings > Schedule Meeting** in the sidebar.
2. Click **"Schedule Meeting"**.
3. Fill in:
   - **Title** — Meeting name (required)
   - **Description** — Purpose or notes
   - **Meeting Date** — Date of the meeting (required)
   - **Start Time** — Start time
   - **End Time** — Expected end time
   - **Venue** — Physical or virtual location
   - **Type** — Regular, Special, Annual, or Emergency
   - **Status** — Scheduled, Ongoing, Completed, or Cancelled
4. Add **Agenda Items** (optional):
   - Click **"Add Agenda Item"** to add a row
   - Enter **title**, **description**, and **order**
   - Use the **"Remove"** button to delete a row
5. Click **"Save Meeting"**.

[Insert Screenshot: Schedule Meeting Form]

### 10.2 Attendance

During or after a meeting:

1. Open the meeting's detail page.
2. Click **"Record Attendance"**.
3. For each member, select their status:
   - **Present** — Attended
   - **Absent** — Did not attend
   - **Excused** — Notified absence
   - **Late** — Arrived late
4. Enter any notes.
5. Click **"Save Attendance"**.

### 10.3 Agenda

Agenda items are added when scheduling the meeting. Each item has:

- Title
- Description
- Order number
- Discussion status (pending/discussed/deferred)

### 10.4 Minutes

To record minutes:

1. Open the meeting's detail page.
2. Go to the **"Minutes"** tab.
3. Click **"Record Minutes"**.
4. Select the **agenda item** this minute relates to.
5. Enter:
   - **Content** — What was discussed (required)
   - **Decision** — What was decided
   - **Action Items** — What needs to be done
6. Click **"Save Minutes"**.

### 10.5 Meeting Status Workflow

Meetings follow this status flow:

- **Scheduled** → **Ongoing** → **Completed** (or **Cancelled**)
- Click the **"Update Status"** button to move a meeting forward.
- A meeting must be marked **Ongoing** before it can be completed.

### 10.6 Meeting Reports

See **Chapter 11: Reports** for meeting reports.

---

## Chapter 11: Announcements

### 11.1 Reading Announcements

Go to **Announcements** in the sidebar to see all published announcements. They are displayed as cards with:

- **Title** — The announcement headline
- **Body** — Full message
- **Priority badge** — Low, Normal, High, or Urgent (colour-coded)
- **Pin indicator** — Pinned announcements appear first
- **Author** — Who created it
- **Date** — When it was published
- **Expiry** — If set, the date it expires

### 11.2 Creating Announcements

1. Go to **Announcements**.
2. Click **"New Announcement"**.
3. Fill in:
   - **Title** — Announcement headline (required)
   - **Body** — Full announcement text (required)
   - **Priority** — Normal, High, Urgent, or Low
   - **Target Role** — Optional, restrict visibility to a specific role
   - **Pinned** — Check to keep this announcement at the top
   - **Expires At** — Optional expiry date
4. Click **"Publish"**.

### 11.3 Editing Announcements

The current version does not include an edit feature for announcements. To update an announcement, delete it and create a new one.

### 11.4 Deleting Announcements

1. Go to **Announcements**.
2. Find the announcement you want to delete.
3. Click the **"Delete"** button (trash icon).
4. Confirm the deletion.

### 11.5 Visibility

Announcements can be targeted to a specific role (e.g., only Members, only Treasurers). If no target role is selected, the announcement is visible to all users.

Pinned announcements always appear at the top of the list, before unpinned ones.

---

## Chapter 12: Messaging

### 12.1 Inbox

1. Go to **Messaging** in the sidebar.
2. The **Inbox** tab shows messages sent to you.
3. Each message shows:
   - **Sender** — Who sent it
   - **Subject** — Message subject
   - **Body** — Message content (expandable)
   - **Date** — When it was sent
   - **Read status** — Unread messages are highlighted
4. Click a message to expand and read it. It will be marked as read automatically.

### 12.2 Sent Items

1. Click the **"Sent"** tab.
2. Messages you have sent are listed with:
   - **Recipient(s)** — Who received the message
   - **Subject** — Message subject
   - **Body** — Message content
   - **Date** — When it was sent

### 12.3 Composing a Message

1. Click the **"Compose"** button.
2. Fill in:
   - **Recipient** — Select a user from the dropdown (required)
   - **Subject** — Brief subject line
   - **Message** — Your message text (required)
3. Click **"Send"**.

### 12.4 Attachments

The current version does not support file attachments in messages.

### 12.5 Searching Messages

Use your browser's search function (Ctrl+F) to find specific text within the current tab.

### 12.6 Deleting Messages

The current version does not include a delete feature for individual messages.

---

## Chapter 13: Reports

### 13.1 Available Reports

The system provides five standard reports accessible from **Reports** in the sidebar:

| Report | Description |
|--------|-------------|
| **Contribution Report** | All member contributions with filters and totals |
| **Loan Report** | All loan applications with status and amounts |
| **Member Report** | Member directory with contact information |
| **Meeting Report** | Meeting records with details |
| **Financial Report** | Period-based financial summary |

### 13.2 Filters

Each report includes filter options:

- **Date Range** — Filter by start date and end date
- **Status** — Filter by record status
- **Member** — Filter by specific member
- **Category/Type** — Filter by contribution type, loan product, etc.

Apply filters and click **"Filter"** to update the report.

### 13.3 Export to PDF

Every report page includes an **"Export PDF"** link. Click it to download the report as a PDF file. The PDF includes:

- System name and report title
- Filter criteria
- Data table with all matching records
- Total amounts (where applicable)
- Generation date and time

### 13.4 Print

Use your browser's Print function (Ctrl+P or Cmd+P) to print any report page.

### 13.5 Financial Report

The Financial Report shows:

- **Total Contributions** — Sum of contributions in the period
- **Other Income** — Sum of income records
- **Loans Disbursed** — Total loan amounts given out
- **Loan Repayments** — Total repayments received
- **Total Expenses** — Sum of expenses
- **Net Position** — Income minus expenses

All values are filtered by the selected date range.

---

## Chapter 14: Notifications

### 14.1 Viewing Notifications

1. Click the **bell icon** in the top navigation bar to see recent notifications.
2. Click **"View All Notifications"** to go to the full Notifications page.
3. Notifications are displayed as cards with:
   - **Type badge** — Info (blue), Success (green), Warning (yellow), Danger (red)
   - **Title** — Notification heading
   - **Message** — Details (if any)
   - **Link** — Clickable link to the relevant page
   - **Time** — When it was created

### 14.2 Unread Notifications

- A **red dot** on the bell icon indicates you have unread notifications.
- Unread notifications have a solid background; read ones appear dimmed.

### 14.3 Marking as Read

- **Single notification**: Click the "Mark Read" button on an individual notification.
- **All notifications**: Click **"Mark All as Read"** to mark all notifications as read at once.

### 14.4 Deleting Notifications

- Click **"Clear All"** to delete all notifications.
- Individual deletion is not available in the current version.

### 14.5 Real-Time Alerts

The system does not provide real-time (WebSocket/push) notifications. Notifications are loaded when you refresh the page or navigate to the Notifications page.

### 14.6 Email Notifications

The system does not currently send email notifications. All notifications are in-app only.

### 14.7 SMS Notifications

The system does not currently send SMS notifications. Settings for SMS integration exist but are not functional.

---

## Chapter 15: Settings

### 15.1 Accessing Settings

Go to **Settings** in the sidebar. The Settings page has four tabs:

### 15.2 General Settings

| Setting | Description | Default |
|---------|-------------|---------|
| **Site Name** | The name displayed in the browser title | Advanced Chama System |
| **Site Tagline** | Short description below the site name | Manage your Chama affairs... |
| **Currency** | Base currency for financial values | KES |
| **Currency Symbol** | Symbol displayed before amounts | KSh |
| **Timezone** | Timezone for date/time display | Africa/Nairobi |
| **Date Format** | How dates are displayed | d/m/Y |

### 15.3 Branding Settings

| Setting | Description |
|---------|-------------|
| **Primary Color** | Main brand colour (default: `#161950` navy) |
| **Secondary Color** | Accent colour (default: `#465fff` blue) |
| **Logo** | Upload a logo image for the login page and reports |
| **Favicon** | Upload a favicon for the browser tab |

Changing the primary and secondary colours will update the system's appearance immediately.

### 15.4 Security Settings

| Setting | Description | Default |
|---------|-------------|---------|
| **Max Login Attempts** | Number of failed attempts before lockout | 5 |
| **Session Timeout** | Minutes of inactivity before auto-logout | 60 (3600 seconds) |
| **Password Min Length** | Minimum characters for passwords | 8 |

### 15.5 Email Settings (SMTP)

Configure email server details for sending system emails:

| Setting | Description |
|---------|-------------|
| **SMTP Host** | Mail server address |
| **SMTP Port** | Mail server port (default: 587) |
| **SMTP Username** | Mail account username |
| **SMTP Password** | Mail account password |
| **From Email** | Email address shown in the "From" field |
| **From Name** | Name shown in the "From" field |

> **Note**: Configuring SMTP does not automatically enable email sending. The current version does not send emails even when SMTP is configured.

### 15.6 Backup Settings

| Setting | Description |
|---------|-------------|
| **Auto Backup** | Enable scheduled automatic backups |
| **Backup Frequency** | Daily, Weekly, or Monthly |

Click **"Run Manual Backup"** to create a database backup immediately. Backup files are listed below the settings form.

---

## Chapter 16: Profile

### 16.1 Viewing Your Profile

Go to **Profile** in the sidebar (or click your name in the top-right corner > "My Profile").

Your profile page shows:

- **Profile Photo** — Your uploaded photo or initials
- **Account Details** — Username, email, phone, role
- **Linked Member** — If you are linked to a member record, their details appear here

### 16.2 Updating Profile

See **Chapter 3.8 — Updating Profile**.

### 16.3 Changing Password

See **Chapter 3.7 — Changing Password**.

### 16.4 Uploading Photo

See **Chapter 3.9 — Uploading Profile Photo**.

### 16.5 Viewing Activity History

Scroll to the **"Recent Activity"** section on your profile to see your last 20 actions (logins, record creations, updates, etc.).

### 16.6 Viewing Login History

Scroll to the **"Login Sessions"** section to see your recent login sessions, including:

- IP address
- Browser/device information
- Last activity time

---

## Chapter 17: Security

### 17.1 Password Rules

All passwords must meet these requirements:

- Minimum **8 characters**
- At least one **uppercase letter** (A-Z)
- At least one **lowercase letter** (a-z)
- At least one **digit** (0-9)
- At least one **special character** (e.g., !@#$%^&*)

### 17.2 Safe Login Practices

- Never share your password with anyone
- Use the **"Remember Me"** feature only on personal devices
- Always click **"Sign Out"** when using shared computers
- Do not use the same password for multiple services

### 17.3 Role Permissions

Each role has specific permissions. See the table below for a summary:

| Module | Super Admin | Admin | Chairperson | Secretary | Treasurer | Loan Officer | Member | Auditor |
|--------|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|
| Dashboard | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| View Members | ✓ | ✓ | ✓ | ✓ | ✓ | ✗ | ✓ | ✓ |
| Create/Edit Members | ✓ | ✓ | ✗ | ✓ | ✗ | ✗ | ✗ | ✗ |
| Contributions | ✓ | ✓ | ✗ | ✗ | ✓ | ✗ | ✓ | ✓ |
| Shares | ✓ | ✓ | ✓ | ✓ | ✓ | ✗ | ✓ | ✓ |
| Loans | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| Approve Loans | ✓ | ✓ | ✓ | ✗ | ✗ | ✗ | ✗ | ✗ |
| Disburse Loans | ✓ | ✗ | ✗ | ✗ | ✗ | ✓ | ✗ | ✗ |
| Meetings | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| Record Attendance | ✓ | ✓ | ✓ | ✓ | ✗ | ✗ | ✗ | ✗ |
| Messages | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✗ |
| Announcements | ✓ | ✓ | ✓ | ✓ | ✗ | ✗ | ✓ | ✗ |
| Reports | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✗ | ✓ |
| Accounting | ✓ | ✓ | ✓ | ✗ | ✓ | ✗ | ✗ | ✓ |
| Settings | ✓ | ✓ | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ |
| Manage Users | ✓ | ✓ | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ |
| Audit Logs | ✓ | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ | ✓ |

### 17.4 Session Timeout

- Inactive sessions expire after **60 minutes** by default.
- After timeout, you are redirected to the login page with a warning message.
- The timeout can be changed in **Settings > Security**.

### 17.5 Account Lockout

- After **5 failed login attempts** (configurable in Settings), the account is locked for **15 minutes**.
- During lockout, you will see: *"Account locked. Too many login attempts. Try again in 15 minutes."*
- Lockout applies per email address.

### 17.6 Password Reset

See **Chapter 3.5 — Forgot Password** and **Chapter 3.6 — Reset Password**.

---

## Chapter 18: Troubleshooting

| Problem | Likely Cause | Solution |
|---------|-------------|----------|
| **Cannot log in** | Wrong email, password, or group code | Check all three fields are correct. Use the demo credentials: `admin@chama.com` / `Admin@123` / `CHAMA001` |
| **"Account locked" message** | Too many failed attempts | Wait 15 minutes and try again |
| **Forgot password** | No email sent | Contact your administrator for a reset link |
| **Blank white page** | PHP error in the background | Enable error display in `config.php` (set `display_errors` to 1) or check the error log at `logs/error.log` |
| **"Permission denied"** | Your role does not have the required permission | Contact your administrator to update role permissions |
| **Record not saving** | Missing required field or CSRF token expired | Go back, refresh the page, and try again |
| **File upload failed** | File too large or wrong format | Check that the file is under 5 MB and is JPG, PNG, GIF, PDF, DOC, or XLS |
| **Reports not generating** | No data matching the filter criteria | Widen the date range or remove filters |
| **Notifications not appearing** | No notifications created yet | Create a contribution or loan application to trigger a notification |
| **Slow system** | Large dataset or server load | Use filters and pagination to limit displayed records |
| **Page not found (404)** | Typo in URL or moved page | Use the sidebar menu to navigate instead of typing URLs |
| **Browser shows old data** | Cache issue | Hard refresh (Ctrl+F5 or Cmd+Shift+R) |

---

## Chapter 19: Frequently Asked Questions

1. **What is a chama?** — A chama is an informal savings group common in Kenya where members pool money for savings, loans, and investments.

2. **Who can access the system?** — Only registered users with valid credentials and the correct group code can access the system.

3. **How do I add a new member?** — Go to **Members**, click **"Add New Member"**, fill in the required fields, and save.

4. **What is a group code?** — A unique code assigned to each chama group. All members of the same group share this code.

5. **How do I record a contribution?** — Go to **Contributions**, click **"Record Contribution"**, select the member and type, enter the amount, and save.

6. **Can I edit a contribution after saving?** — The current version does not support editing contributions. Delete and re-enter if needed.

7. **How are loan payments applied?** — You specify the principal and interest portions separately. The loan balance is reduced by the principal amount.

8. **What is reducing balance interest?** — Interest is calculated on the outstanding loan balance, so it decreases over time.

9. **What is flat rate interest?** — Interest is calculated on the original loan amount for the full term.

10. **How do I approve a loan?** — Open the loan application, check the details, and click the appropriate approval button based on your role.

11. **Can I reject a loan?** — Yes. On the loan detail page, click "Reject" and enter a reason.

12. **How do I record meeting minutes?** — Go to the meeting, click "Record Minutes", select the agenda item, enter the content, and save.

13. **Can I delete a member?** — Members are soft-deleted (set to Inactive). The record remains in the database.

14. **How do I export a report?** — Go to the report page, apply filters, and click **"Export PDF"**.

15. **Why can't I see certain menu items?** — Menu visibility is controlled by permissions. Your role may not have access to that module.

16. **How do I change the system colours?** — Go to **Settings > Branding**, change the primary and secondary colours, and save.

17. **What is the default admin account?** — Email: `admin@chama.com`, Password: `Admin@123`, Group Code: `CHAMA001`.

18. **How do I back up the database?** — Go to **Settings > Backup**, click **"Run Manual Backup"**.

19. **Can I restore from a backup?** — The current version does not include a restore function. Use phpMyAdmin to import backup SQL files.

20. **How many users can I create?** — No limit. You can create as many users as needed.

21. **Can a member have multiple loans?** — Yes, a member can have multiple loans simultaneously.

22. **How do I add guarantors?** — During loan application, click "Add Guarantor" and select a member.

23. **What happens to guarantors when a loan is paid?** — They remain in the record for audit purposes but are no longer liable.

24. **How do I add collateral?** — During loan application, click "Add Collateral" and enter the details.

25. **What are share certificates?** — Each share purchase gets a unique certificate number for record-keeping.

26. **How do dividends work?** — The Dividends table exists in the database, but the current version does not include a dividend payment interface.

27. **Can I schedule recurring contributions?** — No, each contribution must be recorded individually.

28. **How do I search for a member?** — Use the search bar in the top navigation or the search field on the Members page.

29. **Can I print member profiles?** — Yes, use your browser's Print function on the member's detail page.

30. **What is an audit log?** — A record of every change made in the system, including who made it and when.

31. **How long are audit logs kept?** — Audit logs are kept indefinitely unless manually deleted from the database.

32. **Can I see my login history?** — Yes, go to **Profile** and scroll to the **"Login Sessions"** section.

33. **What is the difference between Inbox and Sent?** — Inbox shows messages received; Sent shows messages you have sent.

34. **How do I pin an announcement?** — Check the "Pinned" box when creating the announcement.

35. **What happens when an announcement expires?** — It remains visible but is no longer marked as current. The system does not auto-hide expired announcements.

36. **Can I target announcements to specific roles?** — Yes, select a "Target Role" when creating the announcement.

37. **How do I record attendance for past meetings?** — Open the meeting and click "Record Attendance". The date and time are not restricted.

38. **What meeting types are available?** — Regular, Special, Annual, and Emergency.

39. **Can I cancel a meeting?** — Yes, change the status to "Cancelled" from the meeting page.

40. **How does the trial balance work?** — It shows all debit and credit totals for each account as of a given date, ensuring they balance.

41. **What is a journal entry?** — A double-entry record that debits one or more accounts and credits others by the same total amount.

42. **Can I view the general ledger?** — Yes, go to **Accounting > Ledger**, select an account, and view all transactions.

43. **How do bank accounts work?** — Bank accounts are tracked separately with their own balances, account numbers, and types.

44. **Can I edit a bank account?** — Yes, from the Bank Accounts page, click the edit icon.

45. **What payment methods are supported?** — Cash, M-Pesa, Bank, Cheque, and Other.

46. **How is currency displayed?** — With the currency symbol before the amount (e.g., KSh 1,000.00).

47. **Can I change the currency?** — Yes, go to **Settings > General** and select a different currency.

48. **What browsers are supported?** — Chrome, Firefox, Edge, Safari, and Opera (recent versions).

49. **Is the system mobile-friendly?** — Yes, the responsive layout works on smartphones and tablets.

50. **How do I suggest a new feature?** — Use the contact form on the landing page or speak to your system administrator.

51. **Can I export data to Excel?** — The current version only exports to PDF. Use your browser to copy data from tables if needed.

52. **What is a "contribution type"?** — A category of contribution (e.g., Monthly Savings, Welfare Fund) with its own amount and frequency.

53. **Why is my account showing "inactive"?** — An administrator may have deactivated your account. Contact them to reactivate it.

54. **How do I know if a loan has been approved?** — Check the loan status on the Loans page or your notifications.

55. **Can I restructure a loan?** — The database supports loan restructuring, but the current version does not have a restructuring interface.

---

## Chapter 20: Glossary

| Term | Definition |
|------|-----------|
| **Account** | A record in the chart of accounts used for double-entry bookkeeping (e.g., Cash at Bank, Loan Receivables). |
| **Agenda** | A list of items to be discussed in a meeting. |
| **Amortization** | The process of spreading out a loan into regular payments over time. |
| **Announcement** | A broadcast message visible to all users or a specific role. |
| **Attendance** | Records of which members were present, absent, or late for a meeting. |
| **Audit Log** | A chronological record of all changes made in the system for accountability. |
| **Balance** | The remaining amount owed on a loan or the current value of an account. |
| **Beneficiary** | A person designated to receive benefits (e.g., upon a member's death). Not fully implemented. |
| **Breadcrumb** | A navigation aid showing the user's current location in the system. Not implemented. |
| **Certificate** | A unique number issued for each share purchase. |
| **Chama** | A Kenyan informal savings and investment group. |
| **Chart of Accounts** | A list of all accounts used in the group's double-entry bookkeeping system. |
| **Collateral** | An asset pledged as security for a loan. |
| **Contribution** | Money paid by a member into the group's savings pool. |
| **Credit** | A double-entry bookkeeping term for the right-side entry (decreases assets, increases liabilities). |
| **CSRF Token** | A security token that prevents cross-site request forgery. |
| **Dashboard** | The main page showing a summary of key information and statistics. |
| **Debit** | A double-entry bookkeeping term for the left-side entry (increases assets, decreases liabilities). |
| **Disbursement** | The act of releasing approved loan funds to a member. |
| **Dividend** | A distribution of profits to shareholding members. Not fully implemented. |
| **Double-Entry** | An accounting method where every transaction affects at least two accounts (debit and credit). |
| **Expense** | Money spent by the group on operational costs. |
| **Favicon** | A small icon displayed in the browser tab. |
| **Flash Message** | A temporary notification that appears after an action (success, error, warning). |
| **Group Code** | A unique identifier shared by all members of a chama group. |
| **Guarantor** | A member who promises to repay a loan if the borrower defaults. |
| **Income** | Money received by the group from non-contribution sources (e.g., interest, fees). |
| **Journal Entry** | A double-entry record of a financial transaction. |
| **Ledger** | A book or page containing all transactions for a specific account. |
| **Loan Product** | A defined type of loan with specific terms (interest rate, tenure limits, etc.). |
| **Member** | An individual who belongs to the chama group. |
| **Member Number** | A unique identifier assigned to each member (e.g., CHM-2024-0001). |
| **Minutes** | The official written record of what was discussed and decided in a meeting. |
| **Notification** | An in-app alert about an event (e.g., loan approval, new announcement). |
| **Pagination** | Splitting a long list of records across multiple pages. |
| **PDO** | PHP Data Objects — a database access method used by the system. |
| **Permission** | A specific action a user is allowed to perform (e.g., create_members, approve_loans). |
| **Receipt** | A unique number generated for each contribution or payment transaction. |
| **Role** | A job function that determines what permissions a user has. |
| **Role Slug** | A machine-readable version of the role name (e.g., `treasurer`, `loan_officer`). |
| **Schedule** | A repayment plan showing each installment's due date, amount, and balance. |
| **Session** | A period of active login, tracked for security auditing. |
| **Share** | A unit of ownership in the group's investment pool. |
| **SMTP** | Simple Mail Transfer Protocol — used for sending emails. Not functional. |
| **Soft Delete** | Marking a record as inactive instead of removing it from the database. |
| **Tenure** | The duration of a loan in months. |
| **Trial Balance** | A report listing all accounts and their debit/credit totals to verify that total debits equal total credits. |

---

*End of User Manual*

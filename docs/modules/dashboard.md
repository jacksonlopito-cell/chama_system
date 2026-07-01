# Dashboard Module

**File:** `dashboard.php`  
**Access:** All authenticated users

## Description
The Dashboard is the landing page after login. It provides a real-time summary of key metrics, recent activity, and quick-action shortcuts.

## Sections

### 1. Statistics Cards
| Card | Description | Source Table |
|------|-------------|-------------|
| Total Members | Count of active members | `members` |
| Total Contributions | Sum of all contributions | `contributions` |
| Active Loans | Count of active (disbursed, not fully repaid) loans | `loans` |
| Total Shares | Sum of all share purchases | `share_purchases` |
| Pending Approvals | Count of members pending approval | `members` |
| Upcoming Meetings | Next scheduled meeting | `meetings` |

### 2. Recent Activity Feed
- Displays last 10 actions from `audit_logs`
- Shows user, action description, timestamp
- Auto-refreshes every 60 seconds

### 3. Quick Action Buttons
| Button | Action | Permission Required |
|--------|--------|-------------------|
| Add Member | Opens member creation form | `manage_members` |
| Record Contribution | Opens contribution form | `manage_contributions` |
| Schedule Meeting | Opens meeting form | `manage_meetings` |
| View Reports | Goes to reports page | `view_reports` |

### 4. Charts (if configured)
- Monthly contribution trends (bar chart)
- Loan distribution by status (pie chart)
- Member growth over time (line chart)

## Data Sources
All dashboard data is fetched via AJAX from `ajax/dashboard-stats.php`. Charts use Chart.js CDN.

## Roles & Visibility
- **Super Admin:** All cards + all quick actions
- **Admin:** All cards + all quick actions
- **Chairperson/Treasurer:** Financial cards hidden; approval card shown
- **Secretary:** Meeting-focused view
- **Member:** Own contributions, shares, loan balance only
- **Auditor:** Read-only cards

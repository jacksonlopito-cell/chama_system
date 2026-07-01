# Loans Module

**File:** `loans.php`  
**Access:** Super Admin, Admin, Chairperson, Treasurer, Loan Officer

## Description
Complete loan management: application, approval, disbursement, repayment, and tracking.

## DataTable Columns

| # | Column | Type | Description |
|---|--------|------|-------------|
| 1 | Loan No | Text | Auto-generated loan number |
| 2 | Member | Text | Member name |
| 3 | Amount | Currency | Principal amount |
| 4 | Interest | Percentage | Interest rate |
| 5 | Duration | Months | Repayment period |
| 6 | Status | Badge | pending/approved/disbursed/repaying/fully_paid/defaulted/written_off |
| 7 | Balance | Currency | Remaining balance |
| 8 | Actions | Buttons | View, Edit, Approve, Disburse, Repay, Delete |

## Form Fields — Add/Edit Loan

| Field | Type | Required | Validation |
|-------|------|----------|------------|
| Member | Select2 | Yes | Search from active members |
| Loan Amount | Number | Yes | Must be > 0 |
| Interest Rate | Number | Yes | Percentage (default from settings) |
| Duration (Months) | Number | Yes | 1-120 |
| Purpose | Textarea | No | Loan purpose description |
| Disbursement Date | Date | Conditional | Required for disbursed status |
| Repayment Method | Select | Yes | Monthly/Lump Sum |
| Guarantor | Select2 | No | Optional guarantor member |
| Status | Select | Yes | Based on workflow |

## Loan Workflow

1. **Application** → status = `pending`
2. **Approval** → status = `approved` (requires permission)
3. **Disbursement** → status = `disbursed` (records date, creates repayment schedule)
4. **Repayment** → status = `repaying` (partial payments recorded)
5. **Full Payment** → status = `fully_paid` (auto when balance = 0)
6. **Default** → status = `defaulted` (manually or after missed payments)

## Actions

| Button | Action | Condition |
|--------|--------|-----------|
| **Apply** | New loan application | - |
| **Approve** | Approve pending loan | status = pending |
| **Disburse** | Disburse approved loan | status = approved |
| **Record Repayment** | Record payment | status = disbursed/repaying |
| **View Schedule** | View amortization | status >= disbursed |
| **Print Statement** | Member statement | Any status |
| **Write Off** | Write off bad loan | status = defaulted |
| **Delete** | Remove loan | status = pending only |

## Related Modules
- [Contributions](contributions.md) — Member savings linked to loan eligibility
- [Dashboard](dashboard.md) — Active loan summary
- Reports — Loan portfolio reports

# Contributions Module

**File:** `contributions.php`  
**Access:** Super Admin, Admin, Treasurer

## Description
Records member contributions (savings). Per-member tracking with date, amount, and payment method.

## DataTable Columns

| # | Column | Type | Description |
|---|--------|------|-------------|
| 1 | Receipt No | Text | Auto-generated receipt |
| 2 | Member | Text | Member name |
| 3 | Amount | Currency | Contribution amount |
| 4 | Method | Badge | Cash/Bank/M-Pesa/Cheque |
| 5 | Date | Date | Contribution date |
| 6 | Reference | Text | Transaction reference |
| 7 | Status | Badge | confirmed/pending/cancelled |
| 8 | Actions | Buttons | View, Edit, Receipt, Delete |

## Form Fields — Add/Edit Contribution

| Field | Type | Required | Validation |
|-------|------|----------|------------|
| Member | Select2 | Yes | Search from active members |
| Amount | Number | Yes | > 0, 2 decimal places |
| Payment Method | Select | Yes | Cash/Bank/M-Pesa/Cheque |
| Payment Date | Date | Yes | Defaults to today |
| Reference | Text | No | Transaction ID/cheque no |
| Receipt No | Text | Auto | Auto-generated |
| Notes | Textarea | No | Max 1000 chars |
| Status | Select | Yes | confirmed/pending/cancelled |

## Actions

| Button | Action |
|--------|--------|
| **Record Contribution** | New contribution entry |
| **Print Receipt** | Generates printable receipt |
| **Edit** | Modify contribution details |
| **Cancel** | Change status to cancelled |
| **Export** | Export to CSV/Excel |

## Related Modules
- Reports — Contribution summaries by member, date range
- Members — Member balance overview on dashboard

# Accounting — Income Module

**File:** `accounting/income.php`  
**Access:** Super Admin, Admin, Treasurer

## Description
Records all income transactions for the group. Part of the double-entry accounting system.

## DataTable Columns

| # | Column | Type | Description |
|---|--------|------|-------------|
| 1 | Date | Date | Transaction date |
| 2 | Description | Text | Income description |
| 3 | Category | Text | Income category |
| 4 | Amount | Currency | Income amount |
| 5 | Payment Method | Text | Cash/Bank/M-Pesa |
| 6 | Reference | Text | Transaction reference |
| 7 | Status | Badge | confirmed/pending/cancelled |
| 8 | Actions | Buttons | Edit, Receipt, Delete |

## Form Fields — Add Income

| Field | Type | Required | Validation |
|-------|------|----------|------------|
| Date | Date | Yes | Valid date |
| Description | Text | Yes | 3-500 chars |
| Category | Select | Yes | From income_categories table |
| Amount | Number | Yes | > 0 |
| Payment Method | Select | Yes | Cash/Bank/M-Pesa/Cheque |
| Reference | Text | No | Optional |
| Notes | Textarea | No | Max 1000 chars |
| Status | Select | Yes | confirmed/pending/cancelled |

## Actions

| Button | Action |
|--------|--------|
| **Add Income** | New income record |
| **Print Receipt** | Printable receipt |
| **Edit** | Modify record |
| **Cancel** | Status → cancelled |
| **Export** | CSV/Excel export |

## Related Modules
- [Expenses](expenses.md) — Offset expenses
- [Journal](journal.md) — Double-entry posting
- [Trial Balance](trial-balance.md) — Account summary

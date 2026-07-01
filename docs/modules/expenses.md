# Accounting — Expenses Module

**File:** `accounting/expenses.php`  
**Access:** Super Admin, Admin, Treasurer

## Description
Records all expense transactions. Supports categorization and receipt attachment.

## DataTable Columns

| # | Column | Type | Description |
|---|--------|------|-------------|
| 1 | Date | Date | Expense date |
| 2 | Description | Text | Expense description |
| 3 | Category | Text | Expense category |
| 4 | Amount | Currency | Expense amount |
| 5 | Payment Method | Text | Cash/Bank/M-Pesa |
| 6 | Reference | Text | Receipt/Reference |
| 7 | Status | Badge | confirmed/pending/cancelled |
| 8 | Actions | Buttons | Edit, Receipt, Delete |

## Actions

| Button | Action |
|--------|--------|
| **Add Expense** | New expense record |
| **Upload Receipt** | Attach receipt image |
| **Print Voucher** | Printable expense voucher |
| **Edit** | Modify record |
| **Cancel** | Status → cancelled |
| **Export** | CSV/Excel export |

## Related Modules
- [Income](income.md) — Offset income
- [Journal](journal.md) — Double-entry posting
- [Trial Balance](trial-balance.md) — Account summary

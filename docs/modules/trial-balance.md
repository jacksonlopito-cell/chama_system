# Accounting — Trial Balance Module

**File:** `accounting/trial-balance.php`  
**Access:** Super Admin, Admin, Treasurer, Auditor

## Description
Period-end trial balance showing all accounts with debit/credit balances.

## DataTable Columns

| # | Column | Type | Description |
|---|--------|------|-------------|
| 1 | Account Code | Text | Account code |
| 2 | Account Name | Text | Account name |
| 3 | Debit | Currency | Total debits |
| 4 | Credit | Currency | Total credits |
| 5 | Balance | Currency | Net balance (debit - credit) |

## Filter Controls
| Filter | Type | Description |
|--------|------|-------------|
| Period From | Date | Start of period |
| Period To | Date | End of period |

## Features
- Debits and credits columns must balance (reported at bottom)
- Net balance (debit - credit) for quick reference
- Period filtering
- Print-friendly view

## Related Modules
- [Journal](journal.md) — Source entries
- [Ledger](ledger.md) — Account detail
- [Income](income.md) — Revenue accounts
- [Expenses](expenses.md) — Expense accounts

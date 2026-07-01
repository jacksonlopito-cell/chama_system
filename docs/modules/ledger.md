# Accounting — Ledger Module

**File:** `accounting/ledger.php`  
**Access:** Super Admin, Admin, Treasurer

## Description
Account ledger view — shows all transactions affecting each account with running balance.

## DataTable Columns

| # | Column | Type | Description |
|---|--------|------|-------------|
| 1 | Date | Date | Transaction date |
| 2 | Journal ID | Link | Source journal entry |
| 3 | Description | Text | Transaction narrative |
| 4 | Debit | Currency | Debit amount |
| 5 | Credit | Currency | Credit amount |
| 6 | Balance | Currency | Running balance |
| 7 | Actions | Buttons | View Journal, Drill-down |

## Filter Controls
| Filter | Type | Description |
|--------|------|-------------|
| Account | Select | Filter by chart of accounts |
| Date From | Date | Start date |
| Date To | Date | End date |

## Features
- Running balance calculated per transaction
- Account selector filters to specific account
- Date range filtering
- Export to CSV

## Related Modules
- [Journal](journal.md) — Source of ledger entries
- [Trial Balance](trial-balance.md) — Period-end summary

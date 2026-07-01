# Accounting — Journal Module

**File:** `accounting/journal.php`  
**Access:** Super Admin, Admin, Treasurer

## Description
Double-entry journal entries. Each entry has debits and credits that must balance.

## DataTable Columns

| # | Column | Type | Description |
|---|--------|------|-------------|
| 1 | Journal ID | Number | Entry number |
| 2 | Date | Date | Entry date |
| 3 | Description | Text | Narrative |
| 4 | Account | Text | Account name |
| 5 | Debit | Currency | Debit amount |
| 6 | Credit | Currency | Credit amount |
| 7 | Status | Badge | posted/draft/cancelled |
| 8 | Actions | Buttons | View, Edit, Reverse, Delete |

## Form Fields — Add Journal Entry

| Field | Type | Required | Validation |
|-------|------|----------|------------|
| Date | Date | Yes | Valid date |
| Description | Text | Yes | 3-500 chars |
| Entries (lines) | Dynamic | Yes | At least 2 lines |
| Account (per line) | Select | Yes | From chart of accounts |
| Debit (per line) | Number | Conditional | Must have debit OR credit |
| Credit (per line) | Number | Conditional | Must have debit OR credit |
| Status | Select | Yes | posted/draft |

### Validation
- Total debits must equal total credits
- Each line must have either debit or credit (not both, not neither)
- At least 2 lines required

## Actions

| Button | Action |
|--------|--------|
| **New Entry** | Create journal entry |
| **Post** | Change draft → posted |
| **Reverse** | Create reversing entry (posted only) |
| **Edit** | Modify (draft only) |
| **Delete** | Remove (draft only) |
| **Export** | CSV/Excel |

## Related Modules
- [Ledger](ledger.md) — Account balances from journal
- [Trial Balance](trial-balance.md) — Account summary

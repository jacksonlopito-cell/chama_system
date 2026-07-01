# Shares — Purchase Module

**File:** `shares.php`  
**Access:** Super Admin, Admin, Treasurer (view all); Members (view own)

## Description
Members purchase shares from available products. Features multi-product support, per-product + cumulative max validation, certificate auto-generation, and cancellation.

## DataTable Columns (Fixed 8 columns — TN-18 safe)

| # | Column | Type | Description |
|---|--------|------|-------------|
| 1 | Certificate No | Text | Auto-generated (CERT-YYYY-NNNN) |
| 2 | Member | Text | Member name |
| 3 | Product | Text | Share product name |
| 4 | Quantity | Number | Number of shares purchased |
| 5 | Total Amount | Currency | Quantity × Price Per Share |
| 6 | Purchase Date | Date | Date of purchase |
| 7 | Status | Badge | active/cancelled |
| 8 | Actions | Buttons | View Certificate, Cancel |

### Notes
- Actions column is **always present** (8 columns unconditional)
- Permission check is inside `<td>` content — column count never changes
- `$.fn.DataTable.isDataTable()` guard prevents double-initialization

## Form Fields — Buy Shares (Modal)

| Field | Type | Required | Validation |
|-------|------|----------|------------|
| Member | Select2 | Yes | Search from active members |
| Product | Select | Yes | Only active products shown |
| Quantity | Number | Yes | Min 1; ≤ product max_per_member minus current holdings |
| Amount | Display | Auto | Quantity × Price Per Share (calculated) |
| Payment Method | Select | Yes | Cash/Bank/M-Pesa/Cheque |
| Payment Reference | Text | No | Optional reference |
| Notes | Textarea | No | Max 500 chars |

## Certificate Auto-Generation
- Format: `CERT-YYYY-NNNN` (Year + 4-digit sequential)
- Automatically generated on purchase
- Printable from Actions → View Certificate

## Validation Rules
- Cumulative shares per product cannot exceed `max_per_member`
- Quantity must be ≥ 1
- Cannot cancel if dividends have been paid on these shares

## Actions

| Button | Action | Condition |
|--------|--------|-----------|
| **Buy Shares** | Opens purchase modal | - |
| **View Certificate** | Opens certificate modal with print button | - |
| **Cancel** | Changes status to cancelled | Only active shares |

## Related Modules
- [Share Products](share-products.md) — Product definitions
- [Dividends](dividends.md) — Dividend payments on shares

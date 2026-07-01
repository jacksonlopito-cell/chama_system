# Shares — Dividends Module

**File:** `dividends.php`  
**Access:** Super Admin, Admin, Treasurer

## Description
Declare, pay, and cancel dividends on share products. Automatically calculates total from active shares and creates payment records per shareholder.

## Dividend Cards Layout
Each declared dividend is displayed as a card with:
- Product name, per-share amount, financial year, total amount
- Status badge: declared/paid/cancelled
- Collapsible payment breakdown per member

## Form Fields — Declare Dividend (Modal)

| Field | Type | Required | Validation |
|-------|------|----------|------------|
| Product | Select | Yes | Only products with active shares |
| Per Share Amount | Number | Yes | > 0 |
| Financial Year | Text | Yes | e.g., 2025-2026 |
| Total Amount | Display | Auto | Per Share Amount × Total Active Shares |
| Group Code | Text | Yes | Defaults to CHAMA001 |

## Auto-Creation Process
On declaring a dividend:
1. System queries all active share purchases for the selected product
2. For each shareholder: `dividend_payment` record created with:
   - `member_id`, `share_purchase_id`, `dividend_id`
   - `shares_owned` = quantity from purchase
   - `amount_due` = shares_owned × per_share_amount
   - `status` = `pending`
3. Dividend total = sum of all `amount_due`

## Actions

| Button | Action | Condition |
|--------|--------|-----------|
| **Declare Dividend** | Opens declaration modal | - |
| **Pay Dividend** | Batch pay all pending payments | status = declared |
| **Cancel Dividend** | Cancel entire dividend | status = declared (→ cancelled; pending payments → cancelled) |
| **View Payments** | Expand collapsible breakdown | Any status |

## Payment Column Distribution
When Pay is triggered:
- All `dividend_payments` with status `pending` → `paid`
- Dividend status `declared` → `paid`
- Future enhancement: individual payment marking

## Member View
Members see their own dividend payments (amount_due, status, pay date) through their profile or a dedicated member view on this page (not yet implemented).

## Related Modules
- [Share Products](share-products.md) — Product definitions
- [Share Purchases](shares.md) — Share ownership records

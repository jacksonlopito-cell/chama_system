# Share Products Module

**File:** `share-products.php`  
**Access:** Super Admin, Admin, Treasurer

## Description
Defines share products that members can purchase. Each product has a name, description, price per share, and maximum shares per member.

## DataTable Columns

| # | Column | Type | Description |
|---|--------|------|-------------|
| 1 | Product Name | Text | Name of the share product |
| 2 | Description | Text | Short description |
| 3 | Price Per Share | Currency | Cost per share |
| 4 | Max Per Member | Number | Maximum shares a member can hold |
| 5 | Total Sold | Number | Total shares purchased (all members) |
| 6 | Status | Badge | active/inactive |
| 7 | Actions | Buttons | Edit, Activate/Deactivate, Delete |

## Form Fields — Add/Edit Product

| Field | Type | Required | Validation |
|-------|------|----------|------------|
| Product Name | Text | Yes | 3-200 chars, unique |
| Description | Textarea | No | Max 1000 chars |
| Price Per Share | Number | Yes | > 0, 2 decimal places |
| Max Per Member | Number | Yes | > 0 |
| Group Code | Text | Yes | Defaults to CHAMA001 |
| Status | Select | Yes | active/inactive |

## Actions

| Button | Action | Condition |
|--------|--------|-----------|
| **Add Product** | New share product | - |
| **Edit** | Modify product | - |
| **Activate/Deactivate** | Toggle product status | - |
| **Delete** | Remove product | Only if no purchases exist (otherwise deactivate instead) |

## Validation Rules
- Product name must be unique within the group
- Max per member must be >= 1
- Price must be > 0
- Cannot delete product with existing purchases — system suggests deactivation

## Related Modules
- [Share Purchases](shares.md) — Members buy shares of these products
- [Dividends](dividends.md) — Dividends are declared per product

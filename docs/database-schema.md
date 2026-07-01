# Database Schema

**Database:** `chama_system`  
**Engine:** InnoDB (all tables)  
**Charset:** utf8mb4  

## Tables (52+)

### Core Tables

#### `users`
| Column | Type | Notes |
|--------|------|-------|
| id | int(11) PK AI | |
| role_id | int(11) FK | → roles.id |
| member_id | int(11) FK NULL | → members.id (unique, ON DELETE SET NULL) |
| username | varchar(100) | Unique |
| email | varchar(255) | Unique |
| password | varchar(255) | password_hash() bcrypt |
| group_code | varchar(20) | Multi-group isolation |
| phone | varchar(20) | |
| photo | varchar(255) | |
| status | enum | active/inactive/suspended |
| is_verified | tinyint(1) | Email verified |
| last_login | timestamp | |
| login_attempts | int(11) | |
| locked_until | timestamp | |
| remember_token | varchar(255) | |
| two_factor_secret | varchar(255) | |
| two_factor_enabled | tinyint(1) | |
| created_at | timestamp | |
| updated_at | timestamp | |

#### `roles`
| Column | Type | Notes |
|--------|------|-------|
| id | int(11) PK AI | |
| name | varchar(50) | Super Admin, Admin, etc. |
| slug | varchar(50) | super_admin, admin, etc. |
| description | text | |
| created_at | timestamp | |

#### `members`
| Column | Type | Notes |
|--------|------|-------|
| id | int(11) PK AI | |
| member_no | varchar(50) | Auto-generated |
| first_name | varchar(100) | |
| last_name | varchar(100) | |
| email | varchar(255) | |
| phone | varchar(20) | |
| id_number | varchar(50) | |
| date_of_birth | date | |
| gender | enum | Male/Female/Other |
| photo | varchar(255) | |
| county_id | int(11) FK | |
| sub_county_id | int(11) FK | |
| ward_id | int(11) FK | |
| constituency | varchar(100) | |
| postal_address | varchar(255) | |
| emergency_contact | varchar(255) | |
| group_code | varchar(20) | Multi-group |
| join_date | date | |
| status | enum | draft/pending_verification/email_verified/pending_approval/active/suspended/terminated |
| created_by | int(11) FK NULL | → users.id |
| created_at | timestamp | |
| updated_at | timestamp | |

#### `permissions`
| Column | Type | Notes |
|--------|------|-------|
| id | int(11) PK AI | |
| name | varchar(100) | |
| slug | varchar(100) | Unique |
| module | varchar(50) | |
| description | text | |
| created_at | timestamp | |

#### `role_permissions`
| Column | Type | Notes |
|--------|------|-------|
| id | int(11) PK AI | |
| role_id | int(11) FK | → roles.id |
| permission_id | int(11) FK | → permissions.id |

### Shares Module

#### `share_products`
| Column | Type | Notes |
|--------|------|-------|
| id | int(11) PK AI | |
| name | varchar(200) | |
| description | text | |
| price_per_share | decimal(10,2) | |
| max_per_member | int(11) | |
| total_sold | int(11) | Default 0 |
| status | enum | active/inactive |
| group_code | varchar(20) | Multi-group |
| created_at | timestamp | |
| updated_at | timestamp | |

#### `share_purchases`
| Column | Type | Notes |
|--------|------|-------|
| id | int(11) PK AI | |
| certificate_no | varchar(50) | CERT-YYYY-NNNN |
| member_id | int(11) FK | |
| product_id | int(11) FK | → share_products.id |
| quantity | int(11) | |
| total_amount | decimal(12,2) | |
| purchase_date | date | |
| payment_method | varchar(50) | |
| payment_reference | varchar(255) | |
| status | enum | active/cancelled |
| group_code | varchar(20) | |
| notes | text | |
| created_by | int(11) FK | |
| created_at | timestamp | |

#### `dividends`
| Column | Type | Notes |
|--------|------|-------|
| id | int(11) PK AI | |
| product_id | int(11) FK | → share_products.id |
| per_share_amount | decimal(10,2) | |
| total_amount | decimal(12,2) | |
| financial_year | varchar(20) | |
| status | enum | declared/paid/cancelled |
| group_code | varchar(20) | |
| declared_by | int(11) FK | |
| declared_at | timestamp | |
| paid_at | timestamp | |

#### `dividend_payments`
| Column | Type | Notes |
|--------|------|-------|
| id | int(11) PK AI | |
| dividend_id | int(11) FK | → dividends.id |
| member_id | int(11) FK | |
| share_purchase_id | int(11) FK | |
| shares_owned | int(11) | |
| amount_due | decimal(12,2) | |
| status | enum | pending/paid/cancelled |
| paid_at | timestamp | |
| created_at | timestamp | |

### Accounting Module

#### `chart_of_accounts`
| Column | Type |
|--------|------|
| id | int(11) PK AI |
| account_code | varchar(20) |
| account_name | varchar(200) |
| account_type | enum (asset/liability/equity/income/expense) |
| parent_id | int(11) FK NULL |
| is_active | tinyint(1) |
| group_code | varchar(20) |
| created_at | timestamp |

#### `journal_entries`
| Column | Type |
|--------|------|
| id | int(11) PK AI |
| entry_date | date |
| description | text |
| status | enum (draft/posted/cancelled) |
| created_by | int(11) FK |
| created_at | timestamp |

#### `journal_entry_lines`
| Column | Type |
|--------|------|
| id | int(11) PK AI |
| journal_entry_id | int(11) FK |
| account_id | int(11) FK |
| debit | decimal(12,2) |
| credit | decimal(12,2) |
| description | text |

#### `income_categories`
| Column | Type |
|--------|------|
| id | int(11) PK AI |
| name | varchar(100) |
| description | text |
| group_code | varchar(20) |

#### `expense_categories`
| Column | Type |
|--------|------|
| id | int(11) PK AI |
| name | varchar(100) |
| description | text |
| group_code | varchar(20) |

### Chama Operations

#### `meetings`
| Column | Type |
|--------|------|
| id | int(11) PK AI |
| title | varchar(200) |
| meeting_date | date |
| meeting_time | time |
| venue | varchar(255) |
| agenda | text |
| minutes | text |
| status | enum (scheduled/ongoing/completed/cancelled) |
| group_code | varchar(20) |
| created_by | int(11) FK |
| created_at | timestamp |

#### `contributions`
| Column | Type |
|--------|------|
| id | int(11) PK AI |
| member_id | int(11) FK |
| receipt_no | varchar(50) |
| amount | decimal(12,2) |
| payment_method | varchar(50) |
| payment_date | date |
| reference | varchar(255) |
| notes | text |
| status | enum (confirmed/pending/cancelled) |
| group_code | varchar(20) |
| created_by | int(11) FK |
| created_at | timestamp |

#### `loans`
| Column | Type |
|--------|------|
| id | int(11) PK AI |
| member_id | int(11) FK |
| loan_no | varchar(50) |
| amount | decimal(12,2) |
| interest_rate | decimal(5,2) |
| duration_months | int(11) |
| purpose | text |
| disbursement_date | date |
| repayment_method | varchar(50) |
| status | enum (pending/approved/disbursed/repaying/fully_paid/defaulted/written_off) |
| guarantor_id | int(11) FK NULL |
| balance | decimal(12,2) |
| group_code | varchar(20) |
| created_by | int(11) FK |
| created_at | timestamp |

### System Tables

#### `settings`
| Column | Type |
|--------|------|
| id | int(11) PK AI |
| setting_key | varchar(100) UNIQUE |
| setting_value | text |
| group_code | varchar(20) |
| updated_at | timestamp |

#### `audit_logs`
| Column | Type |
|--------|------|
| id | int(11) PK AI |
| user_id | int(11) FK |
| username | varchar(100) |
| action | varchar(50) |
| module | varchar(50) |
| record_id | int(11) |
| old_values | longtext |
| new_values | longtext |
| ip_address | varchar(45) |
| user_agent | text |
| group_code | varchar(20) |
| created_at | timestamp |

#### `contact_messages`
| Column | Type |
|--------|------|
| id | int(11) PK AI |
| name | varchar(100) |
| email | varchar(255) |
| subject | varchar(200) |
| message | text |
| status | enum (unread/read/replied) |
| created_at | timestamp |

#### `notifications`
| Column | Type |
|--------|------|
| id | int(11) PK AI |
| user_id | int(11) FK |
| title | varchar(200) |
| message | text |
| type | varchar(50) |
| is_read | tinyint(1) |
| link | varchar(255) |
| created_at | timestamp |

#### `counties`, `sub_counties`, `wards`
Location reference tables for Kenyan administrative divisions.

#### `homepage_content`, `hp_sections`, `hp_items`
Homepage CMS tables for dynamic content management.

#### `member_verification_tokens`
Email verification tokens for member registration flow.

### Full Table List
```
accounting_journal_entries
accounting_journal_entry_lines
audit_logs
chart_of_accounts
contact_messages
contributions
counties
dividend_payments
dividends
expense_categories
homepage_content
hp_items
hp_sections
income_categories
journal_entries
journal_entry_lines
loan_repayments
loans
member_verification_tokens
members
meeting_attendance
meetings
notifications
permissions
role_permissions
roles
settings
share_products
share_purchases
sub_counties
users
wards
```

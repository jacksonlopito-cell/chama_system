# Users Module

**File:** `users.php`  
**Access:** Super Admin, Admin

## Description
Manages user accounts for system login. Users are linked to members (one-to-one via `member_id` FK). A user can be created automatically during member approval or manually here.

## DataTable Columns

| # | Column | Type | Description |
|---|--------|------|-------------|
| 1 | Username | Text | Unique login name |
| 2 | Email | Text | Login email |
| 3 | Member | Link | Linked member name (if any) |
| 4 | Role | Badge | Role name from roles table |
| 5 | Status | Badge | active/inactive/suspended |
| 6 | Last Login | DateTime | Last successful login |
| 7 | Actions | Buttons | Edit, Reset Password, Delete |

## Form Fields — Add/Edit User

| Field | Type | Required | Validation |
|-------|------|----------|------------|
| Username | Text | Yes | 3-50 chars, unique |
| Email | Email | Yes | Valid email, unique |
| Password | Password | Yes (add) | Min 8 chars, complex |
| Confirm Password | Password | Yes (add) | Must match password |
| Role | Select | Yes | From roles table |
| Member | Select2 | No | Search/select from members |
| Group Code | Text | Yes | Defaults to CHAMA001 |
| Status | Select | Yes | active/inactive/suspended |

## Actions

| Button | Action | Permission |
|--------|--------|------------|
| **Add User** | Opens creation form | `manage_users` |
| **Edit** | Opens edit form (password optional) | `manage_users` |
| **Reset Password** | Sends password reset email | `manage_users` |
| **Delete** | Removes user (cannot delete self) | `manage_users` |

## Role Assignment
Roles are stored in the `roles` table:
- 1: Super Admin, 2: Admin, 3: Chairperson, 4: Secretary
- 5: Treasurer, 6: Loan Officer, 7: Member, 8: Auditor, 9: Guest

## Auto-Creation
During member approval (`members.php`), `createUserFromMember()`:
1. Generates username from first name + random suffix
2. Hashes the provided password
3. Sets `member_id` FK
4. Assigns `role_id = 7` (Member)
5. Sets `group_code` from member's group

## Related Modules
- [Members](members.md) — Linked member records

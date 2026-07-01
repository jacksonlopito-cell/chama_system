# Members Module

**File:** `members.php`  
**Access:** Super Admin, Admin, Chairperson, Secretary

## Description
Manages member records including personal details, contact info, photos, and approval workflow. One-to-one relationship with user accounts.

## DataTable Columns

| # | Column | Type | Description |
|---|--------|------|-------------|
| 1 | Member No | Text | Auto-generated unique identifier |
| 2 | Photo | Image | Member profile photo |
| 3 | Full Name | Text | First + Last name |
| 4 | Phone | Text | Primary phone number |
| 5 | Location | Text | County + Constituency |
| 6 | Status | Badge | draft/pending_verification/email_verified/pending_approval/active/suspended/terminated |
| 7 | Joined | Date | Registration date |
| 8 | Actions | Buttons | View, Edit, Approve, Suspend, Delete |

## Form Fields — Add/Edit Member

### Personal Information Tab
| Field | Type | Required | Validation |
|-------|------|----------|------------|
| First Name | Text | Yes | 2-100 chars, letters only |
| Last Name | Text | Yes | 2-100 chars, letters only |
| Email | Email | No | Valid email format, unique |
| Phone | Tel | No | Valid phone format |
| ID Number | Text | No | Alphanumeric |
| Date of Birth | Date | No | Must be past date |
| Gender | Select | No | Male/Female/Other |
| Photo | File | No | jpg/png, max 2MB |

### Contact Information Tab
| Field | Type | Required | Validation |
|-------|------|----------|------------|
| County | Select | Yes | From counties table |
| Sub-County | Select | Yes | Filters by county |
| Ward | Select | Yes | Filters by sub-county |
| Constituency | Text | No | Free text |
| Postal Address | Text | No | Free text |
| Emergency Contact | Text | No | Name + phone |

### Membership Tab
| Field | Type | Required | Validation |
|-------|------|----------|------------|
| Member No | Text | Auto | Auto-generated |
| Group Code | Text | Yes | Defaults to CHAMA001 |
| Join Date | Date | Yes | Defaults to today |
| Status | Select | Yes | draft/active/suspended/terminated |

## Actions

| Button | Action | Permission |
|--------|--------|------------|
| **Add Member** | Opens empty form modal | `manage_members` |
| **Edit** (pencil) | Opens form pre-filled with member data | `manage_members` |
| **View** (eye) | Read-only detail view | `manage_members` |
| **Approve** (check) | Approval card with password fields for auto-creating user | `manage_members` |
| **Suspend/Activate** | Toggle member status, syncs linked user status | `manage_members` |
| **Delete** (trash) | Only if no linked transactions exist | `manage_members` |

## Approval Workflow
1. Member registers via public form (`register.php`)
2. Admin opens member record → clicks **Approve**
3. Enters admin password + confirm password for the new user
4. System calls `createUserFromMember()` — generates unique username, hashes password, inserts user with FK
5. System calls `sendCredentialsEmail()` — HTML email with login credentials + login button link
6. Member status → `active`; linked user status → `active`

## Related Modules
- [Users](users.md) — Linked user accounts
- [Profile](profile.md) — Member self-service view

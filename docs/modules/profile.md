# Profile Module

**File:** `profile.php`  
**Access:** All authenticated users

## Description
User profile page showing personal information, linked member details, and account settings.

## Sections

### 1. Personal Information
| Field | Source | Editable |
|-------|--------|----------|
| Username | `users.username` | No |
| Email | `users.email` | Yes |
| Phone | `users.phone` | Yes |
| Photo | `users.photo` | Yes |
| Password | - | Yes (change password) |

### 2. Member Information (if linked)
| Field | Source |
|-------|--------|
| Member No | `members.member_no` |
| Full Name | `members.first_name` + `members.last_name` |
| Photo | `members.photo` |
| Status | `members.status` |
| Joined | `members.created_at` |

### 3. Account Security
| Action | Description |
|--------|-------------|
| Change Password | Current + new password form |
| Two-Factor Auth | Enable/disable 2FA (if configured) |
| Session Management | View active sessions |

### 4. Activity Summary
- Recent contributions
- Active loans
- Share holdings (if any)
- Dividend payments (if any)

## Actions

| Button | Action |
|--------|--------|
| **Update Profile** | Save personal info changes |
| **Change Password** | Update password |
| **Upload Photo** | Change profile picture |
| **View Member Card** | Printable member card |
| **View Full Member Profile** | Link to member record (admin only) |

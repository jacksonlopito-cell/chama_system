# Settings Module

**File:** `settings.php`  
**Access:** Super Admin only

## Description
Central configuration for the entire system. Organized into tabbed sections.

## Tabs

### General
| Setting | Type | Description |
|---------|------|-------------|
| Group Name | Text | Chama group name |
| Group Code | Text | Unique group identifier (e.g., CHAMA001) |
| Default Currency | Select | KES, USD, etc. |
| Default Interest Rate | Number | Default loan interest rate |
| Fiscal Year Start | Date | Start of financial year |
| Fiscal Year End | Date | End of financial year |

### Email (SMTP)
| Setting | Type | Description |
|---------|------|-------------|
| SMTP Host | Text | Mail server hostname |
| SMTP Port | Number | 25, 465, 587 |
| SMTP Username | Text | Authentication username |
| SMTP Password | Password | Authentication password |
| SMTP Encryption | Select | None/SSL/TLS |
| From Email | Email | Sender email address |
| From Name | Text | Sender display name |

### Homepage CMS
| Section | Items Managed | Description |
|---------|--------------|-------------|
| Hero | Background, title, subtitle, CTA | Main banner |
| About | Image, text, stats | About section |
| Services | Cards (icon, title, desc) | Service offerings |
| Stats | Numbers with labels | Statistics bar |
| Footer Links | Quick links, service links | Footer navigation |
| Newsletter | Enable/disable, text | Newsletter signup |

### Security
| Setting | Type | Description |
|---------|------|-------------|
| Min Password Length | Number | Minimum password characters |
| Max Login Attempts | Number | Before lockout |
| Lockout Time | Number | Minutes locked |
| Session Timeout | Number | Minutes of inactivity |
| Enable 2FA | Toggle | Two-factor authentication |

### Notifications
| Setting | Type | Description |
|---------|------|-------------|
| New Member Notification | Toggle | Email on registration |
| Loan Approval Notification | Toggle | Email on loan approval |
| Meeting Reminder | Toggle | Email before meetings |
| Dividend Notification | Toggle | Email when dividends declared |

## Actions

| Button | Action |
|--------|--------|
| **Save** (per tab) | Saves settings for current tab |
| **Restore Defaults** | Reset homepage CMS to defaults |
| **Test Email** | Send test email to verify SMTP |
| **Clear Cache** | Clear system cache |

## Notes
- Each tab has its own Save button to prevent cross-tab form conflicts
- CSRF token is refreshed after every AJAX save
- Homepage CMS has preview functionality

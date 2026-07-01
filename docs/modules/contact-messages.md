# Contact Messages Module

**File:** `contact-messages.php`  
**Access:** Super Admin, Admin

## Description
Manages messages submitted through the public contact form on the homepage.

## DataTable Columns

| # | Column | Type | Description |
|---|--------|------|-------------|
| 1 | Date | DateTime | When submitted |
| 2 | Name | Text | Sender's name |
| 3 | Email | Text | Sender's email |
| 4 | Subject | Text | Message subject |
| 5 | Status | Badge | unread/read/replied |
| 6 | Actions | Buttons | View, Mark Read, Reply, Delete |

## Actions

| Button | Action |
|--------|--------|
| **View** | Opens message detail |
| **Mark Read/Unread** | Toggle read status |
| **Reply** | Opens email client to reply |
| **Delete** | Remove message |

## Public Contact Form
Located on homepage (`index.php`) — submits to `ajax/contact.php` (POST only)

## Related Modules
- Settings (SMTP) — Email configuration for reply functionality

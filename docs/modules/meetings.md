# Meetings Module

**File:** `meetings.php`  
**Access:** Super Admin, Admin, Chairperson, Secretary

## Description
Schedule and manage group meetings. Tracks attendance, minutes, and meeting outcomes.

## DataTable Columns

| # | Column | Type | Description |
|---|--------|------|-------------|
| 1 | Title | Text | Meeting title |
| 2 | Date | Date | Scheduled date |
| 3 | Time | Time | Start time |
| 4 | Venue | Text | Location |
| 5 | Agenda | Text | Key agenda items |
| 6 | Status | Badge | scheduled/ongoing/completed/cancelled |
| 7 | Actions | Buttons | View, Edit, Attendance, Delete |

## Form Fields — Add/Edit Meeting

| Field | Type | Required | Validation |
|-------|------|----------|------------|
| Title | Text | Yes | 3-200 chars |
| Date | Date | Yes | Must be valid date |
| Time | Time | Yes | Valid time format |
| Venue | Text | Yes | 3-255 chars |
| Agenda | Textarea | No | Max 2000 chars |
| Minutes | Textarea | No | Meeting notes (after meeting) |
| Status | Select | Yes | scheduled/ongoing/completed/cancelled |

## Actions

| Button | Action |
|--------|--------|
| **Schedule Meeting** | Creates new meeting |
| **Edit** | Modify meeting details |
| **Attendance** | Mark member attendance |
| **View Minutes** | Read meeting minutes |
| **Cancel** | Change status to cancelled |
| **Delete** | Remove meeting (only if no attendance records) |

## Related Modules
- [Dashboard](dashboard.md) — Upcoming meetings shown
- Reports — Meeting attendance reports

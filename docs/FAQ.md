# Frequently Asked Questions

**Advanced Chama Management System**

---

## General

**1. What is a chama?**
A chama is an informal savings group, common in Kenya, where members pool money for savings, loans, and investments.

**2. What does this system do?**
It helps chamas manage members, contributions, loans, shares, meetings, announcements, messaging, accounting, and reports digitally.

**3. Is the system free?**
Yes, it is self-hosted open-source software. There are no subscription fees.

**4. Do I need an internet connection?**
Yes, the system runs on a web server and requires a browser with internet access.

**5. Can I access it on my phone?**
Yes, the responsive layout works on smartphones, tablets, and computers.

---

## Accounts and Login

**6. How do I get an account?**
Your group administrator creates your account. You will receive your email, password, and group code.

**7. What is a group code?**
A unique code assigned to your chama group. All members share the same group code.

**8. What are the demo credentials?**
Email: `admin@chama.com`, Password: `Admin@123`, Group Code: `CHAMA001`.

**9. Why am I seeing "Invalid email, password, or group code"?**
One or more of the three fields is incorrect. Double-check spelling, including capital letters.

**10. My account is locked. What do I do?**
Too many failed login attempts lock your account for 15 minutes. Wait and try again.

**11. I forgot my password. What can I do?**
Click "Forgot password?" on the login page. The system creates a reset token, but does not email it. Contact your administrator for the reset link.

**12. How do I change my password?**
Go to Profile > Change Password. Enter your current and new password.

**13. Can I stay logged in?**
Check the "Remember me" box on login. This keeps you logged in for 30 days.

**14. What if my account is suspended?**
Contact your administrator to reactivate it.

---

## Members

**15. How do I add a new member?**
Go to Members > Add New Member. Fill in the required fields (name, phone, join date) and save.

**16. How are member numbers generated?**
Automatically in the format `CHM-YYYY-NNNN` (e.g., `CHM-2024-0001`).

**17. Can I delete a member?**
Members are soft-deleted — their status becomes "Inactive" but the record stays in the database.

**18. How do I edit a member's details?**
Click the edit icon (pencil) next to the member in the list, update the fields, and save.

**19. Can I upload a member's photo?**
Yes. Edit the member and upload a photo (JPG/PNG/GIF, max 5 MB).

---

## Contributions

**20. How do I record a contribution?**
Go to Contributions > Record Contribution, select the member and type, enter the amount, and save.

**21. What is a contribution type?**
A category (e.g., Monthly Savings, Welfare Fund) with its own amount and frequency.

**22. Can I edit a contribution?**
The current version does not support editing contributions.

**23. What is a receipt number?**
An auto-generated unique number for each contribution transaction (e.g., `RCP-2024-0001`).

**24. How do I print a receipt?**
Use your browser's Print function on the contributions list page.

---

## Shares

**25. What are shares?**
Units of ownership in the group's investment pool. Members buy shares to invest.

**26. How do I buy shares?**
Go to Shares > Purchase Shares, select the member, product, and number of shares, then save.

**27. What is a certificate number?**
A unique number generated for each share purchase (e.g., `CERT-2024-0001`).

**28. What share products are available?**
Default products include Ordinary Shares (KSh 100 each) and Preference Shares (KSh 200 each).

---

## Loans

**29. How do I apply for a loan?**
Go to Loans > Applications > New Application. Select the member, product, enter the amount and purpose, then submit.

**30. What are the loan requirements?**
The member must be active, the amount must be within the product limits, and the tenure must be within the product's range.

**31. What is the difference between flat and reducing interest?**
Flat interest is calculated on the full loan amount. Reducing balance interest is calculated on the outstanding amount, which decreases over time.

**32. How do I add a guarantor?**
During loan application, click "Add Guarantor" and select a member.

**33. What is collateral?**
An asset pledged as security for the loan (e.g., title deed, logbook).

**34. How do I repay a loan?**
Go to Loans > Repayments > Record Payment. Select the loan, enter the amounts, and save.

**35. How is my loan balance calculated?**
Initial amount minus principal payments made. Interest payments do not reduce the balance.

**36. Can I see my repayment schedule?**
Yes, open the loan detail page and click the "Schedule" tab.

**37. What happens if I miss a payment?**
A late penalty percentage may be applied based on the loan product settings.

**38. How many loans can I have at once?**
There is no system limit on the number of active loans per member.

---

## Meetings

**39. How do I schedule a meeting?**
Go to Meetings > Schedule Meeting. Enter the title, date, time, venue, and agenda items.

**40. How do I record attendance?**
Open the meeting, click "Record Attendance", mark each member as present/absent/excused/late.

**41. How do I record minutes?**
Open the meeting, go to the Minutes tab, click "Record Minutes", and enter the content.

**42. What meeting types are available?**
Regular, Special, Annual, and Emergency.

**43. Can I cancel a meeting?**
Yes, change the meeting status to "Cancelled".

---

## Messaging

**44. How do I send a message?**
Go to Messaging > Compose. Select a recipient, enter subject and body, then send.

**45. Can I send a message to multiple people?**
The current form allows only one recipient per message.

**46. How do I know if someone read my message?**
Check the Sent tab; read status is shown for recipients.

**47. Can I delete a message?**
The current version does not support deleting individual messages.

---

## Announcements

**48. How do I create an announcement?**
Go to Announcements > New Announcement. Enter title, body, priority, and click Publish.

**49. Can I edit an announcement?**
No, delete it and create a new one.

**50. How do I pin an announcement?**
Check the "Pinned" box when creating it. Pinned announcements appear first.

---

## Reports

**51. What reports are available?**
Contribution, Loan, Member, Meeting, and Financial reports.

**52. How do I export a report?**
Go to the report page, set filters, click "Export PDF".

**53. Can I export to Excel?**
No, only PDF export is available.

---

## Accounting

**54. What is a journal entry?**
A double-entry record that debits one or more accounts and credits others by the same total.

**55. What is a trial balance?**
A report showing all accounts with their debit and credit totals to verify they balance.

**56. How do I record an expense?**
Go to Accounting > Expenses, click Add Expense, fill in the details, and save.

**57. How do I record income?**
Go to Accounting > Income, click Add Income, fill in the details, and save.

**58. Can I manage bank accounts?**
Yes, go to Accounting > Bank Accounts to add, edit, or delete accounts.

---

## Settings and Administration

**59. Who can change system settings?**
Only Super Admin and Admin roles can access the Settings page.

**60. How do I back up the database?**
Go to Settings > Backup, click "Run Manual Backup".

**61. How do I restore from a backup?**
Use phpMyAdmin to import the backup SQL file.

**62. What are audit logs?**
A record of every change made in the system, including who made it, what was changed, and when.

---

## Technical

**63. What browsers are supported?**
Chrome 90+, Firefox 88+, Edge 90+, Safari 14+, Opera 76+.

**64. Why is the page slow?**
Large datasets without filters can slow down queries. Use pagination and filters.

**65. I see a blank white page. What happened?**
A PHP error occurred. Check `logs/error.log` for details.

**66. How do I clear the system cache?**
The system does not cache data. Clear your browser cache (Ctrl+F5) if you see old data.

**67. Can I run this on a live server?**
Yes, upload the files to a PHP/MySQL web host and import the database.

**68. What PHP version is required?**
PHP 8.0 or higher.

**69. What database is required?**
MySQL 5.7+ or MariaDB 10.3+.

**70. Is the system secure?**
It uses prepared statements (PDO) for SQL queries, CSRF protection, password hashing (bcrypt), and session security.

<?php
/**
 * Application Constants
 */

// App version
define('APP_VERSION', '1.0.0');
define('APP_NAME', 'Advanced Chama Management System');

// Date/Time
define('DATE_FORMAT', 'd/m/Y');
define('DATETIME_FORMAT', 'd/m/Y H:i:s');
define('TIME_FORMAT', 'H:i:s');

// Pagination
define('PAGE_LIMIT', 20);
define('PAGE_LIMIT_SMALL', 10);

// Status constants
define('STATUS_ACTIVE', 'active');
define('STATUS_INACTIVE', 'inactive');
define('STATUS_PENDING', 'pending');
define('STATUS_SUSPENDED', 'suspended');

// Member statuses (registration workflow)
define('MEMBER_STATUS_DRAFT', 'draft');
define('MEMBER_STATUS_PENDING_VERIFICATION', 'pending_verification');
define('MEMBER_STATUS_EMAIL_VERIFIED', 'email_verified');
define('MEMBER_STATUS_PENDING_APPROVAL', 'pending_approval');
define('MEMBER_STATUS_ACTIVE', 'active');
define('MEMBER_STATUS_SUSPENDED', 'suspended');
define('MEMBER_STATUS_TERMINATED', 'terminated');

// Loan statuses
define('LOAN_PENDING', 'pending');
define('LOAN_APPROVED', 'approved');
define('LOAN_DISBURSED', 'disbursed');
define('LOAN_ACTIVE', 'active');
define('LOAN_PAID', 'paid');
define('LOAN_DEFAULTED', 'defaulted');
define('LOAN_REJECTED', 'rejected');

// Payment methods
define('PAYMENT_CASH', 'cash');
define('PAYMENT_MPESA', 'mpesa');
define('PAYMENT_BANK', 'bank');
define('PAYMENT_CHEQUE', 'cheque');
define('PAYMENT_OTHER', 'other');

// File upload
define('MAX_FILE_SIZE', 5242880); // 5MB
define('ALLOWED_EXTENSIONS', 'jpg,jpeg,png,gif,pdf,doc,docx,xls,xlsx');
define('ALLOWED_IMAGE_EXTENSIONS', 'jpg,jpeg,png,gif');

// Session
define('SESSION_TIMEOUT', 3600); // 1 hour
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 900); // 15 minutes

// Roles slugs
define('ROLE_SUPER_ADMIN', 'super_admin');
define('ROLE_ADMIN', 'admin');
define('ROLE_CHAIRPERSON', 'chairperson');
define('ROLE_SECRETARY', 'secretary');
define('ROLE_TREASURER', 'treasurer');
define('ROLE_LOAN_OFFICER', 'loan_officer');
define('ROLE_MEMBER', 'member');
define('ROLE_AUDITOR', 'auditor');
define('ROLE_GUEST', 'guest');

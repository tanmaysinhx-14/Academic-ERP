# Career Institute — Accounts Portal

A full-featured **Academic ERP web application** built with PHP for Career Institute, Patna. It handles student and faculty lifecycle management, attendance tracking, notifications, enquiry management, and secure multi-role authentication — all under one unified portal.

---

## Table of Contents

- [Overview](#overview)
- [Features](#features)
- [Tech Stack](#tech-stack)
- [Project Structure](#project-structure)
- [Roles & Access Control](#roles--access-control)
- [Modules](#modules)
- [Automated Tasks (Cron Jobs)](#automated-tasks-cron-jobs)
- [Security Highlights](#security-highlights)
- [Setup & Requirements](#setup--requirements)
- [Environment Configuration](#environment-configuration)
- [License](#license)

---

## Overview

The **Career Institute Accounts Portal** is a multi-role academic management system designed to streamline day-to-day operations for students, faculty, and administrators. It provides a centralized interface for account management, attendance, notifications, course materials, and more.

> Live deployment: [careerinstitute.co.in](https://careerinstitute.co.in)

---

## Features

### Student
- Self-registration with admin approval workflow
- Profile management (username, batch, school, bio)
- Monthly and yearly attendance calendar
- Class routine viewer (embedded Excel sheets)
- Account deactivation with a 30-day grace period for reactivation
- Password reset via OTP email
- Email communication preferences
- Remember Me persistent login

### Faculty
- Admin-created accounts with auto-generated credentials
- Profile management
- Attendance viewer
- Class routine access
- Daily class records (in development)

### Admin
- Full student and faculty CRUD with composite record views
- Student registration approval queue
- Attendance creation, editing, and deletion per batch
- Notification management (targeted by role and batch)
- Batchlist manager (CBSE / BSEB / ICSE)
- Enquiry management (submit, view, archive)
- Test marksheets (in development)
- Course materials (in development)

---

## Tech Stack

| Layer | Technology |
|---|---|
| Backend | PHP 8+ |
| Database | MariaDB / MySQL (dual-database architecture) |
| Frontend | Bootstrap 5, vanilla JS, Material Symbols |
| Email | PHPMailer (SMTP) with queue system |
| Auth | Custom session + AES-256-CBC encrypted Remember Me cookies |
| Hosting | Hostinger shared hosting |

---

## Project Structure

```
/
├── approvalManager/        # Admin: student registration approvals
├── attendance/             # Attendance management (admin) and viewer (student/faculty)
├── batchlistManager/       # Admin: configure active batches
├── changePassword/         # OTP-based password reset for all roles
├── components/             # Shared header, footer, breadcrumb partials
├── configurations/         # App-level configuration (maintenance mode, logo)
├── courseMaterials/        # Course materials (in development)
├── cronJobs/               # Automated background tasks
├── dailyClassRecords/      # Daily class logs (in development)
├── dashboard/              # Role-specific dashboards
├── deactivation/           # Student account deactivation
├── enquiryManager/         # Admin: student/faculty enquiry CRUD
├── facultyManager/         # Admin: faculty account management
├── functions/              # Core function library (gitignored)
│   ├── database/           # PDO connection, role maps, data retrievers
│   ├── mail/               # PHPMailer + custom mailer helpers
│   ├── security/           # Encryption, CSRF, environment loader, keys
│   ├── utility/            # Session, cookies, device detection, error logger
│   └── validation/         # Email, password, username, field validators
├── library/                # Frontend assets (CSS/JS bundles, favicon)
├── login/                  # Multi-role login with Remember Me
├── logout/                 # Session and cookie teardown
├── maintenance/            # Maintenance mode page
├── notifications/          # Admin: create and manage notifications
├── profile/                # Student profile editor
├── register/               # Student self-registration
├── routines/               # Class routine iframe viewer
├── testMarksheets/         # Test marksheets (in development)
├── userManager/            # Admin: composite user record viewer and editor
├── bootstrap.php           # Application bootstrap and session initializer
└── index.php               # Root redirect to login
```

> The `functions/` directory is excluded from version control and must be provisioned separately on the server.

---

## Roles & Access Control

| Role | Identifier Prefix | Access |
|---|---|---|
| Student | `CI-ST` | Dashboard, profile, attendance, routines, deactivation |
| Faculty | `CI-FC` | Dashboard, profile, attendance viewer, routines |
| Admin | `CI-AD` | All modules including user management and configuration |

Role is derived at runtime from the usercode prefix — no role column is stored in session or the database.

---

## Modules

### Authentication
Multi-role login with rate limiting (5 attempts / 15 minutes), CSRF protection on every form, and an optional encrypted Remember Me cookie (7-day session). Session IDs are rotated on every login and Remember Me renewal.

### Student Registration
Students submit a registration request that enters an approval queue. The admin reviews the queue, approves or rejects, and a confirmed student account is created with an automatically generated usercode. A confirmation email with a reference code is dispatched on submission.

### Attendance
Admins manage attendance per batch on a monthly calendar. Clicking a date opens a dialog to mark present/absent per student. A tabular view is also available for cross-month review. Students see their own attendance broken down by month and academic year.

### Notifications
Admins publish notifications targeted at either all faculty or specific student batches. Notifications are filtered by expiry timestamp on the student dashboard.

### Enquiry Manager
Admins can submit, view, and archive student or faculty enquiries (institute or home-tuition categories) with metadata stored as structured JSON.

### Account Deactivation
Students can self-deactivate. The account enters a 30-day grace period during which login automatically reactivates it. After 30 days, the account is scheduled for permanent deletion by an automated cron job.

### Cron Jobs
See [Automated Tasks](#automated-tasks-cron-jobs) below.

---

## Automated Tasks (Cron Jobs)

Three cron jobs run independently and should be scheduled via Hostinger's cron manager or server crontab.

### `cronJobs/clearDeviceDetails.php`
Scans all device session records across student, faculty, and admin roles and purges entries whose session expiry timestamp has passed. Also nullifies the corresponding current active session column.

**Recommended schedule:** Every hour
```
0 * * * * php /path/to/cronJobs/clearDeviceDetails.php
```

---

### `cronJobs/sendQueuedEmails.php`
Processes the outgoing email queue stored in the metadata database. Sends up to 25 emails per run (configurable via CLI argument), removes successfully sent records, and logs failures. Uses a file lock to prevent concurrent execution.

**Recommended schedule:** Every 5 minutes
```
*/5 * * * * php /path/to/cronJobs/sendQueuedEmails.php
```

Can also be triggered via HTTP with a secret key for environments that do not support CLI cron.

---

### `cronJobs/deleteDeactivatedStudents.php`
Finds deactivated student accounts whose deactivation timestamp has exceeded the 30-day grace period and permanently deletes all associated records (`student_devicedetails`, `student_timestamps`, `student_configurations`, `student_details`) in a single transaction per student.

**Recommended schedule:** Daily
```
0 2 * * * php /path/to/cronJobs/deleteDeactivatedStudents.php
```

---

## Security Highlights

- All database interactions use PDO prepared statements — no raw query interpolation
- Passwords hashed with `password_hash()` (bcrypt)
- CSRF tokens on every state-changing form, rotated after each use
- AES-256-CBC encryption with HMAC-SHA256 authentication for all token payloads (Remember Me cookies, activation links)
- Rate limiting on login, OTP requests, and student registration — implemented server-side via session buckets
- Session cookies use `HttpOnly`, `Secure`, and `SameSite=Lax` flags
- Security headers issued on every response: `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, `CSP`, `Referrer-Policy`, `Permissions-Policy`
- HSTS header issued on HTTPS connections
- Role-based access enforced at bootstrap — every page verifies login status and role on load
- Retryable PDO exception detection prevents silent data loss on transient DB failures
- Cloudflare and reverse proxy IP headers are respected for accurate client IP detection

---

## Setup & Requirements

| Requirement | Version |
|---|---|
| PHP | 8.0 or higher |
| MariaDB / MySQL | 10.4 or higher |
| Composer | Required for vendor autoload (`donatj/user-agent-parser`) |
| SMTP credentials | Required for PHPMailer email delivery |

### Installation Steps

1. Clone the repository into your web root.
2. Run `composer install` to install vendor dependencies.
3. Create two databases: one for primary user data (`DB1`) and one for metadata/logs (`DB2`).
4. Import your database schema.
5. Create `functions/security/credentials.env` — see [Environment Configuration](#environment-configuration).
6. Provision the `functions/` directory with all helper files (not included in this repository).
7. Configure cron jobs as described above.
8. Ensure the web server rewrites all requests through `index.php` (or configure direct directory access).

---

## Environment Configuration

The application reads all sensitive configuration from `functions/security/credentials.env`. This file is never committed to version control.

The following keys are required:

```env
# Database — Primary (user data)
DB1_HOST=
DB1_USERNAME=
DB1_PASSWORD=
DB1_NAME=

# Database — Metadata (logs, email queue, notifications, attendance)
DB2_HOST=
DB2_USERNAME=
DB2_PASSWORD=
DB2_NAME=

# SMTP
MAIL_HOST=
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_PORT=
MAIL_FROM_ADDRESS=
MAIL_FROM_NAME=

# Encryption keys (minimum 32 characters or 64-character hex)
ACTIVATION_EMAIL_ENCRYPTION_KEY=
QR_ENCRYPTION_KEY=
ATTENDANCE_ENCRYPTION_KEY=
REMEMBER_ME_ENCRYPTION_KEY=
```

> All encryption keys must be unique and cryptographically strong. Do not reuse keys across environments.

---

## License

This project is proprietary software developed and maintained by [Tanmay Sinha](https://developer.careerinstitute.co.in/) for Career Institute, Patna.

Unauthorized copying, redistribution, or deployment of this codebase in whole or in part is prohibited without explicit written permission.

&copy; 2026–2031 Career Institute. All Rights Reserved.
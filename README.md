# Document Monitoring System (DMS)

A LAN-based document monitoring system for routing, receiving, and tracking communications between **Branch Offices**, the **Secretary Deputy**, the **Secretary of CO**, and the **Administrator**. Built with PHP 8 + MariaDB/MySQL + Bootstrap 5. Metadata only — **no file uploads or storage**.

## Features

- Log documents (tracking numbers `DOC-YYYY-######`) and forward them between offices
- Receive confirmation, status updates (NEW → RECEIVED → PENDING / SIGNED / APPROVED / RTS)
- **RTS (Return To Sender)** with mandatory reason — document returns to previous sender, history preserved
- Immutable, full routing timeline on every document (`view_document.php`)
- Real-time notification bell + JSON polling API (`api/notifications.php`)
- Role-based access control + CSRF protection + login throttling + audit log
- Admin: users, branches, live monitoring, audit logs, reports, settings
- Aging badges (Normal / Attention / Overdue) based on configurable thresholds

## Requirements

- XAMPP (or Laragon / WAMP) with PHP 8.0+, MariaDB/MySQL 5.7+, Apache
- Web server root must serve the project (e.g. `C:\xampp\htdocs\document-monitoring`)

## Installation

1. Copy the project folder into your web root:
   - XAMPP: `C:\xampp\htdocs\document-monitoring`
   - URL: `http://localhost/document-monitoring/`

2. Start Apache and MySQL in the XAMPP Control Panel.

3. Import the database schema + seed data:
   ```
   C:\xampp\mysql\bin\mysql.exe -u root --default-character-set=utf8mb4 --execute="source C:/xampp/htdocs/document-monitoring/database/database.sql"
   ```
   (PowerShell cannot use `<` redirection; use the `source` form above.)

4. Configure DB credentials if needed in `config/database.php`:
   ```php
   const DB_HOST = '127.0.0.1';
   const DB_PORT = 3306;
   const DB_NAME = 'document_monitoring';
   const DB_USER = 'root';
   const DB_PASS = '';
   ```

5. Open `http://localhost/document-monitoring/login.php` and log in.

### Demo accounts (change passwords immediately in production)

| Username | Password       | Role             | Branch / Office         |
|----------|----------------|------------------|-------------------------|
| admin    | admin123       | Administrator    | —                       |
| juan     | branch123      | Branch           | Branch A (Branch Head)  |
| maria    | branch123      | Branch           | Branch A (Staff)        |
| pedro    | branch123      | Branch           | Branch B (Staff)        |
| depsec   | secretary123   | Secretary Deputy | Secretary Deputy Office |
| sec      | secretary123   | Secretary of CO  | Secretary of CO Office  |

## LAN Access

The system runs over your office LAN via Apache:

- Find the server's IP: `ipconfig` (e.g. `192.168.1.50`).
- Other PCs open `http://192.168.1.50/document-monitoring/`.
- Allow port 80 through Windows Firewall (inbound):
  ```
  netsh advfirewall firewall add rule name="Apache Web Port" dir=in action=allow protocol=TCP localport=80
  ```
- If XAMPP uses port 8080, allow that port instead and use `http://192.168.1.50:8080/document-monitoring/`.

## Workflow

```
Branch (logs&forwards)  →  Secretary Deputy  →  Secretary of CO  →  Branch (receives)
          ↑                                                               │
          └──────────────────────────  RTS (return to sender)  ───────────┘
```

- Docs forwarded to you appear under **Incoming** — click **Receive** to acknowledge.
- Use a document's **Process** page to update status (PENDING/SIGNED/APPROVED) or RTS.
- **RTS** is always returned to the previous sender and requires a reason.

## Project Structure

```
document-monitoring/
├── index.php, login.php, logout.php, view_document.php
├── config/        constants (roles, statuses, routing), database.php, auth.php
├── includes/      bootstrap, functions (business logic), layout, navbar, sidebar
├── branch/        dashboard, incoming, documents, create_document, forward, returned
├── secretary_deputy/  dashboard, incoming, documents, process
├── secretary_co/      dashboard, incoming, documents, process
├── admin/         dashboard, users, branches, monitoring, audit_logs, settings
├── api/           notifications, dashboard_stats, document_search, mark_read
├── reports/       live reports & aging
├── database/      database.sql (schema + seed)
└── public/assets/ app.css, app.js (notification polling, charts, DataTables)
```

All vendor assets (Bootstrap, Icons, Chart.js, DataTables, jQuery) are local — no internet needed at runtime.

## Backup & Restore

Backup (on any PC with the database running):
```
C:\xampp\mysql\bin\mysqldump.exe -u root document_monitoring > dms-backup.sql
```

Restore:
```
C:\xampp\mysql\bin\mysql.exe -u root --execute="CREATE DATABASE IF NOT EXISTS document_monitoring"
C:\xampp\mysql\bin\mysql.exe -u root document_monitoring --execute="source dms-backup.sql"
```

## Security

- `require_login()` / `require_role()` gate every page; role redirects on failure.
- CSRF tokens on all state-changing forms (`verify_csrf()`), enforced on API too.
- Login throttling: repeated failures are tracked in `login_attempts` and lock the account.
- Passwords are bcrypt-hashed; seeded demo hashes must be replaced in production.
- All tracking writes are atomic (PDO transactions).

## Troubleshooting

- **"Cannot connect to API / socket closed" in the browser notification bell** — the app computes its base URL from the current page. If the site is not at `http://localhost/document-monitoring/`, adjust `BASE_URL` logic in `config/constants.php` (`BASE_PATH` vs `DOCUMENT_ROOT`). Then hard-refresh (Ctrl+F5) so the re-rendered `DMS_BASE_URL` in the footer is loaded.
- **Blank pages** — check `logs/app.log` and the PHP error log (XAMPP: `C:\xampp\php\logs\php_error_log`).
- **Database connection error** — verify credentials in `config/database.php` and that MySQL is running.
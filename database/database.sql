-- =====================================================================
-- DOCUMENT MONITORING SYSTEM - DATABASE SCHEMA & SEED DATA
-- Compatible with MySQL 5.7+ / MariaDB 10.4+
-- This system stores ONLY document metadata and tracking history.
-- It does NOT store, upload, or manage physical document files.
-- =====================================================================

DROP DATABASE IF EXISTS document_monitoring;
CREATE DATABASE document_monitoring
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE document_monitoring;

-- =====================================================================
-- BRANCHES
-- =====================================================================
CREATE TABLE branches (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  branch_code VARCHAR(20) NOT NULL,
  branch_name VARCHAR(150) NOT NULL,
  description VARCHAR(255) DEFAULT NULL,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_branch_code (branch_code),
  KEY idx_branches_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- USERS
-- =====================================================================
CREATE TABLE users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  branch_id INT UNSIGNED DEFAULT NULL,
  full_name VARCHAR(150) NOT NULL,
  username VARCHAR(50) NOT NULL,
  password VARCHAR(255) NOT NULL,
  role ENUM('BRANCH','SECRETARY_DEPUTY','SECRETARY_CO','ADMIN') NOT NULL,
  position VARCHAR(100) DEFAULT NULL,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  last_login DATETIME DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_username (username),
  KEY idx_users_role (role),
  KEY idx_users_status (status),
  KEY idx_users_branch (branch_id),
  CONSTRAINT fk_users_branch FOREIGN KEY (branch_id)
    REFERENCES branches (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- LOGIN ATTEMPT LOG (for brute-force / login attempt protection)
-- =====================================================================
CREATE TABLE login_attempts (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  username VARCHAR(50) NOT NULL,
  ip_address VARCHAR(45) NOT NULL,
  attempted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  success TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY idx_login_attempts (username, ip_address, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- DOCUMENTS
-- =====================================================================
CREATE TABLE documents (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tracking_number VARCHAR(30) NOT NULL,
  doc_control_number VARCHAR(50) DEFAULT NULL,
  subject VARCHAR(255) NOT NULL,
  document_type VARCHAR(100) DEFAULT NULL,
  sender VARCHAR(150) DEFAULT NULL,
  originating_branch_id INT UNSIGNED DEFAULT NULL,
  created_by INT UNSIGNED NOT NULL,
  current_holder_id INT UNSIGNED DEFAULT NULL,
  current_branch_id INT UNSIGNED DEFAULT NULL,
  current_status ENUM('NEW','RECEIVED','PENDING','SIGNED','APPROVED','RTS') NOT NULL DEFAULT 'NEW',
  priority ENUM('Low','Normal','High','Urgent') NOT NULL DEFAULT 'Normal',
  date_received DATE DEFAULT NULL,
  remarks TEXT,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_tracking_number (tracking_number),
  KEY idx_docs_status (current_status),
  KEY idx_docs_created (created_at),
  KEY idx_docs_holder (current_holder_id),
  KEY idx_docs_subject (subject),
  KEY idx_docs_type (document_type),
  KEY idx_docs_orig_branch (originating_branch_id),
  KEY idx_docs_updated (updated_at),
  CONSTRAINT fk_docs_orig_branch FOREIGN KEY (originating_branch_id)
    REFERENCES branches (id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_docs_created_by FOREIGN KEY (created_by)
    REFERENCES users (id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_docs_current_holder FOREIGN KEY (current_holder_id)
    REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_docs_current_branch FOREIGN KEY (current_branch_id)
    REFERENCES branches (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- DOCUMENT TRACKING (IMMUTABLE ROUTING HISTORY)
-- Tracking records are never updated or deleted at the application level.
-- =====================================================================
CREATE TABLE document_tracking (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  document_id INT UNSIGNED NOT NULL,
  from_user_id INT UNSIGNED DEFAULT NULL,
  from_branch_id INT UNSIGNED DEFAULT NULL,
  to_user_id INT UNSIGNED DEFAULT NULL,
  to_branch_id INT UNSIGNED DEFAULT NULL,
  action ENUM('CREATED','RECEIVED','FORWARDED','STATUS_CHANGED','RETURNED_TO_SENDER','SIGNED','APPROVED') NOT NULL,
  status VARCHAR(20) DEFAULT NULL,
  remarks TEXT,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_trk_doc (document_id, created_at),
  KEY idx_trk_from_user (from_user_id),
  KEY idx_trk_to_user (to_user_id),
  KEY idx_trk_action (action),
  KEY idx_trk_created (created_at),
  CONSTRAINT fk_trk_document FOREIGN KEY (document_id)
    REFERENCES documents (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_trk_from_user FOREIGN KEY (from_user_id)
    REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_trk_to_user FOREIGN KEY (to_user_id)
    REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_trk_from_branch FOREIGN KEY (from_branch_id)
    REFERENCES branches (id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_trk_to_branch FOREIGN KEY (to_branch_id)
    REFERENCES branches (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- DOCUMENT STATUS HISTORY (immutable)
-- =====================================================================
CREATE TABLE document_status_history (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  document_id INT UNSIGNED NOT NULL,
  previous_status VARCHAR(20) DEFAULT NULL,
  new_status VARCHAR(20) NOT NULL,
  changed_by INT UNSIGNED NOT NULL,
  remarks TEXT,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_dsh_doc (document_id, created_at),
  KEY idx_dsh_changed_by (changed_by),
  CONSTRAINT fk_dsh_document FOREIGN KEY (document_id)
    REFERENCES documents (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_dsh_changed_by FOREIGN KEY (changed_by)
    REFERENCES users (id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- DOCUMENT PROCESSING RECORDS (permanent per-holder exercise snapshot)
-- One row = one holder stage (creation or receipt). Completed the moment
-- the holder forwards, returns (RTS), or finalizes the document's status.
-- These records are immutable once the user loses possession; they are the
-- "processing history" that proves who received/processed the document.
-- =====================================================================
CREATE TABLE document_processing_records (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  document_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  branch_id INT UNSIGNED DEFAULT NULL,
  role VARCHAR(30) DEFAULT NULL,
  received_at DATETIME DEFAULT NULL,
  processed_at DATETIME DEFAULT NULL,
  status VARCHAR(20) DEFAULT NULL,
  action VARCHAR(30) NOT NULL,
  from_user_id INT UNSIGNED DEFAULT NULL,
  from_branch_id INT UNSIGNED DEFAULT NULL,
  to_user_id INT UNSIGNED DEFAULT NULL,
  to_branch_id INT UNSIGNED DEFAULT NULL,
  remarks TEXT,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_pr_doc (document_id, created_at),
  KEY idx_pr_user (user_id),
  KEY idx_pr_doc_user (document_id, user_id),
  KEY idx_pr_status (status),
  CONSTRAINT fk_pr_document FOREIGN KEY (document_id)
    REFERENCES documents (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_pr_user FOREIGN KEY (user_id)
    REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_pr_branch FOREIGN KEY (branch_id)
    REFERENCES branches (id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_pr_from_user FOREIGN KEY (from_user_id)
    REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_pr_to_user FOREIGN KEY (to_user_id)
    REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_pr_from_branch FOREIGN KEY (from_branch_id)
    REFERENCES branches (id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_pr_to_branch FOREIGN KEY (to_branch_id)
    REFERENCES branches (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- NOTIFICATIONS (internal, LAN-only)
-- =====================================================================
CREATE TABLE notifications (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  document_id INT UNSIGNED DEFAULT NULL,
  message VARCHAR(255) NOT NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_notif_user (user_id, is_read, created_at),
  KEY idx_notif_doc (document_id),
  CONSTRAINT fk_notif_user FOREIGN KEY (user_id)
    REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_notif_doc FOREIGN KEY (document_id)
    REFERENCES documents (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- DOCUMENT COPIES (personal copies saved by secretaries)
-- =====================================================================
CREATE TABLE document_copies (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  document_id INT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_copy_user_doc (user_id, document_id),
  KEY idx_copy_user (user_id, created_at),
  KEY idx_copy_doc (document_id),
  CONSTRAINT fk_copy_user FOREIGN KEY (user_id)
    REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_copy_doc FOREIGN KEY (document_id)
    REFERENCES documents (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- LOGIN LOGS
-- =====================================================================
CREATE TABLE login_logs (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED DEFAULT NULL,
  username VARCHAR(50) DEFAULT NULL,
  ip_address VARCHAR(45) DEFAULT NULL,
  user_agent VARCHAR(255) DEFAULT NULL,
  success TINYINT(1) NOT NULL DEFAULT 0,
  logged_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_login_user (user_id),
  KEY idx_login_time (logged_at),
  CONSTRAINT fk_login_user FOREIGN KEY (user_id)
    REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- AUDIT LOGS
-- =====================================================================
CREATE TABLE audit_logs (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED DEFAULT NULL,
  action VARCHAR(100) NOT NULL,
  module VARCHAR(50) DEFAULT NULL,
  record_id INT UNSIGNED DEFAULT NULL,
  details TEXT,
  ip_address VARCHAR(45) DEFAULT NULL,
  user_agent VARCHAR(255) DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_audit_user (user_id),
  KEY idx_audit_action (action),
  KEY idx_audit_module (module),
  KEY idx_audit_created (created_at),
  KEY idx_audit_record (module, record_id),
  CONSTRAINT fk_audit_user FOREIGN KEY (user_id)
    REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- SYSTEM SETTINGS
-- =====================================================================
CREATE TABLE system_settings (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  setting_key VARCHAR(50) NOT NULL,
  setting_value VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_setting_key (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO system_settings (setting_key, setting_value) VALUES
('aging_normal','2'),
('aging_attention','4'),
('session_timeout_minutes','30'),
('max_login_attempts','5'),
('lockout_minutes','15'),
('org_name','Document Monitoring System'),
('org_short','DMS');

-- =====================================================================
-- SEED DATA
-- =====================================================================

-- Branches
INSERT INTO branches (branch_code, branch_name, description, status) VALUES
('BR-A', 'Branch A', 'Main office branch', 'active'),
('BR-B', 'Branch B', 'Secondary branch', 'active'),
('BR-C', 'Branch C', 'Support branch', 'active'),
('SEC-OFF', 'Secretary Deputy Office', 'Office of the Secretary Deputy', 'active'),
('SEC-CO', 'Secretary of CO Office', 'Office of the Secretary of CO', 'active');

-- =====================================================================
-- USERS (SEED)
-- Passwords are hashed with PHP password_hash(). The placeholders below are
-- replaced at install time by config/install_admin.php. For a database-only
-- import, run the installer ONCE, which regenerates the correct bcrypt hashes
-- for the demo passwords below.
--
-- DEMO CREDENTIALS (for testing only - change on production):
--   admin            /  admin123        (ADMIN - system administrator)
--   juan             /  branch123       (BRANCH - Branch A, Branch Head)
--   maria            /  branch123       (BRANCH - Branch A, Staff)
--   pedro            /  branch123       (BRANCH - Branch B, Staff)
--   depsec           /  secretary123    (SECRETARY_DEPUTY)
--   sec              /  secretary123    (SECRETARY_CO)
-- =====================================================================
INSERT INTO users (branch_id, full_name, username, password, role, position, status, last_login) VALUES
(4, 'System Administrator', 'admin', '$2y$10$cVDpDh30dviwmN0TGXSBRODGFDRm9n6l813QBfBGL.j4VK5lAvHsK', 'ADMIN', 'System Administrator', 'active', NULL),
(1, 'Juan Dela Cruz', 'juan', '$2y$10$s4lT0w6rzNAVGGiKdWNEVOzMkte2d9adcWZ918i84Xt8PruFGproe', 'BRANCH', 'Branch Head', 'active', NULL),
(1, 'Maria Santos', 'maria', '$2y$10$NYCfIxVUKNxfRF0Yin4u/uhwB4XEpj9e0UBPZPOmfLPJktdG.Krn.', 'BRANCH', 'Staff', 'active', NULL),
(2, 'Pedro Reyes', 'pedro', '$2y$10$L0uGaPqrzPoQFkDqbFyjkenTE2UPBILefazzjB5IWcC00R/whXmIS', 'BRANCH', 'Staff', 'active', NULL),
(4, 'Deputy Secretary', 'depsec', '$2y$10$ZUE95gkjY1pKAR300k4B../bMn/ct5K8tORwz0gQvl1GPsX6N3lIO', 'SECRETARY_DEPUTY', 'Secretary Deputy', 'active', NULL),
(5, 'Secretary of CO', 'sec', '$2y$10$E3ytq7bvA8dYoeFDhOyzQeeQX21BhMMgFXGNx5XTM9GV4Rh0CGHzW', 'SECRETARY_CO', 'Secretary of CO', 'active', NULL);

-- =====================================================================
-- SAMPLE DOCUMENTS & TRACKING HISTORY (SEED)
-- Document IDs: 1..4
-- =====================================================================
INSERT INTO documents
  (id, tracking_number, doc_control_number, subject, document_type, sender, originating_branch_id, created_by, current_holder_id, current_branch_id, current_status, priority, date_received, remarks, created_at) VALUES
(1, 'DOC-2026-000001', NULL, 'Request for Approval of Quarterly Budget', 'Request', 'Juan Dela Cruz', 1, 2, 6, 5, 'SIGNED', 'Normal', '2026-08-28', 'Initial submission for budget approval.', '2026-08-28 09:00:00'),
(2, 'DOC-2026-000002', NULL, 'Procurement of Office Supplies', 'Memo', 'Maria Santos', 1, 3, 5, 4, 'PENDING', 'High', '2026-09-05', 'To be reviewed by the secretary.', '2026-09-05 10:15:00'),
(3, 'DOC-2026-000003', NULL, 'Annual Report Submission', 'Report', 'Pedro Reyes', 2, 4, 5, 4, 'APPROVED', 'Normal', '2026-09-01', 'Annual report for review.', '2026-09-01 08:45:00'),
(4, 'DOC-2026-000004', NULL, 'Personnel Leave Request', 'Request', 'Juan Dela Cruz', 1, 2, 2, 1, 'RTS', 'Normal', '2026-09-07', 'Returned due to incomplete details.', '2026-09-07 14:20:00');

-- Tracking history for the seed documents
INSERT INTO document_tracking (document_id, from_user_id, from_branch_id, to_user_id, to_branch_id, action, status, remarks, created_at) VALUES
(1, 2, 1, NULL, NULL, 'CREATED', 'NEW', 'Document created by Branch A.', '2026-08-28 09:00:00'),
(1, 2, 1, 6, 5, 'FORWARDED', 'NEW', 'Forwarded to Secretary of CO.', '2026-08-28 09:05:00'),
(1, 6, 5, NULL, NULL, 'RECEIVED', 'RECEIVED', 'Received by Secretary of CO.', '2026-08-28 09:40:00'),
(1, 6, 5, NULL, NULL, 'STATUS_CHANGED', 'SIGNED', 'Document signed and approved.', '2026-08-28 10:10:00'),
(1, 6, 5, NULL, NULL, 'SIGNED', 'SIGNED', 'Signed by the Secretary of CO.', '2026-08-28 10:11:00'),

(2, 3, 1, NULL, NULL, 'CREATED', 'NEW', 'Document created by Branch A.', '2026-09-05 10:15:00'),
(2, 3, 1, 5, 4, 'FORWARDED', 'NEW', 'Forwarded to Secretary Deputy.', '2026-09-05 10:20:00'),
(2, 5, 4, NULL, NULL, 'RECEIVED', 'RECEIVED', 'Received by Secretary Deputy.', '2026-09-05 11:00:00'),
(2, 5, 4, NULL, NULL, 'STATUS_CHANGED', 'PENDING', 'Status set to PENDING for review.', '2026-09-05 11:15:00'),

(3, 4, 2, NULL, NULL, 'CREATED', 'NEW', 'Document created by Branch B.', '2026-09-01 08:45:00'),
(3, 4, 2, 5, 4, 'FORWARDED', 'NEW', 'Forwarded to Secretary Deputy.', '2026-09-01 08:50:00'),
(3, 5, 4, NULL, NULL, 'RECEIVED', 'RECEIVED', 'Received by Secretary Deputy.', '2026-09-01 09:30:00'),
(3, 5, 4, NULL, NULL, 'STATUS_CHANGED', 'APPROVED', 'Approved by the Secretary Deputy.', '2026-09-01 10:00:00'),
(3, 5, 4, NULL, NULL, 'APPROVED', 'APPROVED', 'Annual report approved.', '2026-09-01 10:01:00'),

(4, 2, 1, NULL, NULL, 'CREATED', 'NEW', 'Document created by Branch A.', '2026-09-07 14:20:00'),
(4, 2, 1, 5, 4, 'FORWARDED', 'NEW', 'Forwarded to Secretary Deputy.', '2026-09-07 14:25:00'),
(4, 5, 4, NULL, NULL, 'RECEIVED', 'RECEIVED', 'Received by Secretary Deputy.', '2026-09-07 15:00:00'),
(4, 5, 4, NULL, NULL, 'RETURNED_TO_SENDER', 'RTS', 'Returned: incomplete personnel details.', '2026-09-07 15:30:00');

-- Status history for the seed documents
INSERT INTO document_status_history (document_id, previous_status, new_status, changed_by, remarks, created_at) VALUES
(1, 'NEW', 'RECEIVED', 6, 'Received by Secretary of CO.', '2026-08-28 09:40:00'),
(1, 'RECEIVED', 'SIGNED', 6, 'Document signed and approved.', '2026-08-28 10:10:00'),
(2, 'NEW', 'RECEIVED', 5, 'Received by Secretary Deputy.', '2026-09-05 11:00:00'),
(2, 'RECEIVED', 'PENDING', 5, 'Status set to PENDING for review.', '2026-09-05 11:15:00'),
(3, 'NEW', 'RECEIVED', 5, 'Received by Secretary Deputy.', '2026-09-01 09:30:00'),
(3, 'RECEIVED', 'APPROVED', 5, 'Approved by the Secretary Deputy.', '2026-09-01 10:00:00'),
(4, 'NEW', 'RECEIVED', 5, 'Received by Secretary Deputy.', '2026-09-07 15:00:00'),
(4, 'RECEIVED', 'RTS', 5, 'Returned: incomplete personnel details.', '2026-09-07 15:30:00');

-- Processing records for the seed documents (permanent per-holder snapshots)
-- Doc 1: Branch A -> Secretary of CO (signed)
INSERT INTO document_processing_records
  (document_id, user_id, branch_id, role, action, status, received_at, processed_at, to_user_id, to_branch_id, remarks, created_at) VALUES
(1, 2, 1, 'BRANCH', 'FORWARDED', 'NEW',        NULL,                 '2026-08-28 09:05:00', 6, 5, 'Forwarded to Secretary of CO.',          '2026-08-28 09:00:00'),
(1, 6, 5, 'SECRETARY_CO', 'SIGNED', 'SIGNED',  '2026-08-28 09:40:00', '2026-08-28 10:10:00', NULL, NULL, 'Signed by the Secretary of CO.', '2026-08-28 09:40:00');
-- Doc 2: Branch A -> Secretary Deputy (pending)
INSERT INTO document_processing_records
  (document_id, user_id, branch_id, role, action, status, received_at, processed_at, to_user_id, to_branch_id, remarks, created_at) VALUES
(2, 3, 1, 'BRANCH', 'FORWARDED', 'NEW',     NULL,                 '2026-09-05 10:20:00', 5, 4, 'Forwarded to Secretary Deputy.',          '2026-09-05 10:15:00'),
(2, 5, 4, 'SECRETARY_DEPUTY', 'STATUS_CHANGED', 'PENDING', '2026-09-05 11:00:00', '2026-09-05 11:15:00', NULL, NULL, 'Status set to PENDING for review.', '2026-09-05 11:00:00');
-- Doc 3: Branch B -> Secretary Deputy (approved)
INSERT INTO document_processing_records
  (document_id, user_id, branch_id, role, action, status, received_at, processed_at, to_user_id, to_branch_id, remarks, created_at) VALUES
(3, 4, 2, 'BRANCH', 'FORWARDED', 'NEW',      NULL,                 '2026-09-01 08:50:00', 5, 4, 'Forwarded to Secretary Deputy.',            '2026-09-01 08:45:00'),
(3, 5, 4, 'SECRETARY_DEPUTY', 'APPROVED', 'APPROVED', '2026-09-01 09:30:00', '2026-09-01 10:00:00', NULL, NULL, 'Annual report approved.', '2026-09-01 09:30:00');
-- Doc 4: Branch A -> Secretary Deputy -> RTS back to Branch A
INSERT INTO document_processing_records
  (document_id, user_id, branch_id, role, action, status, received_at, processed_at, to_user_id, to_branch_id, remarks, created_at) VALUES
(4, 2, 1, 'BRANCH', 'FORWARDED', 'NEW', NULL, '2026-09-07 14:25:00', 5, 4, 'Forwarded to Secretary Deputy.',          '2026-09-07 14:20:00'),
(4, 5, 4, 'SECRETARY_DEPUTY', 'RETURNED_TO_SENDER', 'RTS', '2026-09-07 15:00:00', '2026-09-07 15:30:00', 2, 1, 'Returned: incomplete personnel details.', '2026-09-07 15:00:00');

-- Sample notifications
INSERT INTO notifications (user_id, document_id, message, is_read, created_at) VALUES
(2, 4, 'DOC-2026-000004 has been returned to you.', 1, '2026-09-07 15:30:00'),
(5, 2, 'You have received a new document: DOC-2026-000002.', 1, '2026-09-05 10:20:00'),
(6, 1, 'You have received a new document: DOC-2026-000001.', 1, '2026-08-28 09:05:00');

-- Sample audit log
INSERT INTO audit_logs (user_id, action, module, record_id, details, ip_address, user_agent, created_at) VALUES
(1, 'SEED_DATA_LOADED', 'system', NULL, 'Seed data loaded during installation.', '127.0.0.1', 'installer', NOW());

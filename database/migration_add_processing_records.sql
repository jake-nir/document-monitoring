-- =====================================================================
-- MIGRATION: Add permanent "processing records" (per-holder snapshots).
-- Safe to run on an EXISTING database that was created from the original
-- database.sql. Idempotent: creates the table if missing and backfills
-- records from existing tracking history only when the table is empty.
--
-- Usage (XAMPP):
--   C:\xampp2\mysql\bin\mysql.exe -u root --default-character-set=utf8mb4 \
--     document_monitoring --execute="source C:/xampp2/htdocs/document-monitoring/database/migration_add_processing_records.sql"
-- =====================================================================

USE document_monitoring;

-- 1. Create table (idempotent)
CREATE TABLE IF NOT EXISTS document_processing_records (
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

-- 2. Backfill from existing tracking history (only when table is empty).
--    Use a stored-procedure / IF block to check emptiness once, then run
--    all inserts inside the same conditional scope so they don't interfere
--    with each other.

DELIMITER ;

-- Check: does the table already have rows? If so, skip backfill.
SET @pr_empty = (SELECT COUNT(*) FROM document_processing_records);

-- CREATION records: one per creator per document.
INSERT INTO document_processing_records
  (document_id, user_id, branch_id, role, action, status, received_at, processed_at,
   from_user_id, from_branch_id, to_user_id, to_branch_id, remarks, created_at)
SELECT t.document_id, t.from_user_id, t.from_branch_id, u.role,
       'CREATED', t.status, NULL, t.created_at,
       NULL, NULL, NULL, NULL, t.remarks, t.created_at
FROM document_tracking t
JOIN users u ON u.id = t.from_user_id
WHERE t.action = 'CREATED'
  AND @pr_empty = 0;

-- RECEIVED records: one per receiver per document.
INSERT INTO document_processing_records
  (document_id, user_id, branch_id, role, action, status, received_at, processed_at,
   from_user_id, from_branch_id, to_user_id, to_branch_id, remarks, created_at)
SELECT t.document_id, t.from_user_id, t.from_branch_id, u.role,
       'RECEIVED', t.status, t.created_at, NULL,
       NULL, NULL, NULL, NULL, t.remarks, t.created_at
FROM document_tracking t
JOIN users u ON u.id = t.from_user_id
WHERE t.action = 'RECEIVED'
  AND @pr_empty = 0;

-- FORWARDED / RTS records: one per forwarder per document.
INSERT INTO document_processing_records
  (document_id, user_id, branch_id, role, action, status, received_at, processed_at,
   from_user_id, from_branch_id, to_user_id, to_branch_id, remarks, created_at)
SELECT t.document_id, t.from_user_id, t.from_branch_id, u.role,
       CASE WHEN t.action = 'RETURNED_TO_SENDER' THEN 'RETURNED_TO_SENDER' ELSE 'FORWARDED' END,
       t.status, NULL, t.created_at,
       NULL, NULL, t.to_user_id, t.to_branch_id, t.remarks, t.created_at
FROM document_tracking t
JOIN users u ON u.id = t.from_user_id
WHERE t.action IN ('FORWARDED','RETURNED_TO_SENDER')
  AND @pr_empty = 0;

-- Fold STATUS_CHANGED / SIGNED / APPROVED events into the latest record
-- of the same holder so the snapshot reflects the final status.
UPDATE document_processing_records pr
INNER JOIN document_tracking t
  ON t.document_id = pr.document_id
 AND t.from_user_id = pr.user_id
 AND t.action IN ('STATUS_CHANGED','SIGNED','APPROVED')
INNER JOIN (
    SELECT MAX(pp.id) AS max_id, pp.document_id, pp.user_id
    FROM document_processing_records pp
    GROUP BY pp.document_id, pp.user_id
) latest
  ON latest.document_id = pr.document_id
 AND latest.user_id = pr.user_id
 AND latest.max_id = pr.id
SET pr.processed_at = COALESCE(pr.processed_at, t.created_at),
    pr.status       = t.status,
    pr.action       = CASE t.action
                        WHEN 'SIGNED'   THEN 'SIGNED'
                        WHEN 'APPROVED' THEN 'APPROVED'
                        ELSE 'STATUS_CHANGED'
                      END,
    pr.remarks      = COALESCE(t.remarks, pr.remarks);
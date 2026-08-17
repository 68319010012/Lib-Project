-- One-time correction for rows written before src/db.php started running
-- `SET time_zone = '+07:00'` on every connection.
--
-- Root cause: checkin_logs.timestamp / planned_checkout_at, users.created_at,
-- and login_attempts.attempted_at all use DEFAULT CURRENT_TIMESTAMP, which
-- follows MySQL's OWN session timezone, not PHP's. On the original XAMPP dev
-- box that happened to already be Asia/Bangkok, so nobody noticed. On the
-- live host (ntclibrary.com) MySQL's clock is UTC, so every one of those
-- columns was stamped 7 hours early — this is the "check-in shows 7 hours
-- elapsed immediately" / "history starts at 02:00" bug.
--
-- ---------------------------------------------------------------------------
-- SAFETY
--
-- Shifting every row by +7 hours is destructive and NOT reversible by re-
-- running anything: a second pass moves the data 14 hours and there is no way
-- to tell corrected rows from uncorrected ones afterwards. Two guards below:
--
--   1. schema_migrations. Each UPDATE is gated on this migration's name not
--      already being recorded. Run the file twice and the second run changes
--      zero rows instead of corrupting every timestamp. This is what makes it
--      safe to paste into phpMyAdmin when you are unsure whether it ran.
--
--   2. A cutoff. Only rows older than @cutoff are shifted, so anything written
--      after db.php was deployed — which is already correct — is left alone.
--      @cutoff is set to the moment this script starts.
--
-- ---------------------------------------------------------------------------
-- HOW TO USE
--
--   1. Back up the database first (hPanel -> Databases -> Backups, or
--      mysqldump). The guards make a double-run harmless; they do not undo a
--      wrong cutoff.
--   2. Deploy the src/db.php change (the one adding `SET time_zone`) FIRST.
--   3. Run this whole file ONCE, in phpMyAdmin's SQL tab, as soon after step 2
--      as you can.
--
--      Best done after the library has closed for the day. Between step 2 and
--      step 3 any student who checks in gets a correctly-stamped row that is
--      still older than @cutoff, so it would be shifted +7 hours by mistake.
--      With nobody using the site that window is empty.
--
--   4. Check the reported row counts, then spot-check a few recent rows:
--        SELECT log_id, timestamp, planned_checkout_at
--        FROM checkin_logs ORDER BY log_id DESC LIMIT 20;
--      Times should read as Thailand wall-clock (a visit at 2pm says 14:xx).
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS schema_migrations (
    name VARCHAR(191) NOT NULL PRIMARY KEY,
    applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

SET @migration = '2026-08-17-fix-utc-timestamp-offset';
SET @cutoff = NOW();

-- 0 = not yet applied (so the UPDATEs run), 1 = already applied (they no-op).
SET @done = (SELECT COUNT(*) FROM schema_migrations WHERE name = @migration);

UPDATE checkin_logs
SET timestamp = DATE_ADD(timestamp, INTERVAL 7 HOUR)
WHERE @done = 0 AND timestamp < @cutoff;

UPDATE checkin_logs
SET planned_checkout_at = DATE_ADD(planned_checkout_at, INTERVAL 7 HOUR)
WHERE @done = 0 AND planned_checkout_at IS NOT NULL AND planned_checkout_at < @cutoff;

UPDATE users
SET created_at = DATE_ADD(created_at, INTERVAL 7 HOUR)
WHERE @done = 0 AND created_at < @cutoff;

UPDATE login_attempts
SET attempted_at = DATE_ADD(attempted_at, INTERVAL 7 HOUR)
WHERE @done = 0 AND attempted_at < @cutoff;

-- Records the run. INSERT IGNORE so a second pass doesn't error out on the
-- duplicate key — it has already changed nothing by this point anyway.
INSERT IGNORE INTO schema_migrations (name) VALUES (@migration);

-- Should print 1 row, with applied_at set to the FIRST run.
SELECT name, applied_at FROM schema_migrations WHERE name = @migration;

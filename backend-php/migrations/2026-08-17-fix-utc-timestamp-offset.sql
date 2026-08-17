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
-- HOW TO USE
--   1. Back up the database first (mysqldump or your host's backup tool).
--   2. Deploy the src/db.php change (adds `SET time_zone = '+07:00'`) FIRST.
--   3. Run the SELECT below and sanity-check the numbers look plausible
--      (e.g. compare a recent checkin_logs.timestamp against when that
--      student actually checked in) before running the UPDATEs.
--   4. Run this script's UPDATEs ONCE, right after deploying step 2.
--      Do NOT run it again later — rows written after the db.php fix are
--      already correct, and re-running this would shift them wrong.

-- Sanity check — run first, eyeball the values:
-- SELECT log_id, timestamp, planned_checkout_at FROM checkin_logs ORDER BY log_id DESC LIMIT 20;

UPDATE checkin_logs
SET timestamp = DATE_ADD(timestamp, INTERVAL 7 HOUR);

UPDATE checkin_logs
SET planned_checkout_at = DATE_ADD(planned_checkout_at, INTERVAL 7 HOUR)
WHERE planned_checkout_at IS NOT NULL;

UPDATE users
SET created_at = DATE_ADD(created_at, INTERVAL 7 HOUR);

UPDATE login_attempts
SET attempted_at = DATE_ADD(attempted_at, INTERVAL 7 HOUR);

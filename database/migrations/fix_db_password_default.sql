-- Migration: Fix db_password NOT NULL constraint
-- Adds DEFAULT '' to db_password so partial inserts (status='creating') don't fail.
-- The ServiceController now always provides a temp password before save(), so this
-- is an extra safety net for any other code paths.

ALTER TABLE `databases`
    MODIFY COLUMN `db_password` TEXT NOT NULL DEFAULT '';

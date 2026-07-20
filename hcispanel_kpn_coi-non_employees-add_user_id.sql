-- --------------------------------------------------------
-- Database:  hcispanel_kpn_coi  (local application database)
-- Table:     non_employees
-- Purpose:   Add the explicit link column to users.
-- --------------------------------------------------------
--
-- Why this exists
-- ---------------
-- non_employees and users were assumed to share the same primary key
-- (non_employees.id = users.id). Nothing ever enforced that: the id was
-- passed to Eloquent's create() but was not in $fillable, so it was silently
-- discarded and the column just auto-incremented on its own. Once the two
-- sequences drifted apart the relation resolved to a real but WRONG person
-- -- e.g. manager@kpn.com showing another user's KTP and home address.
--
-- user_id makes the link explicit, and the foreign key makes a bad link fail
-- loudly at insert time instead of silently returning a stranger.
--
-- Prerequisites
-- -------------
--   * users.id is `bigint unsigned` (Laravel $table->id())
--   * both tables are ENGINE=InnoDB
--   * run against the LOCAL database (hcispanel_kpn_coi), not kpncorp
--
-- NOTE: user_id is NULL for every existing row after this runs. The profile
-- columns (KTP, business unit, address) will read blank until the rows are
-- backfilled. That is intentional -- blank is safer than the wrong person's
-- data -- but the backfill is required to make the screen useful again.


-- --------------------------------------------------------
-- Apply
-- --------------------------------------------------------

ALTER TABLE `non_employees`
    ADD COLUMN `user_id` bigint unsigned DEFAULT NULL AFTER `id`,
    ADD UNIQUE KEY `non_employees_user_id_unique` (`user_id`),
    ADD CONSTRAINT `non_employees_user_id_foreign`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE CASCADE;


-- --------------------------------------------------------
-- Verify
-- --------------------------------------------------------

-- Column, unique index and foreign key should all be present.
-- SHOW CREATE TABLE `non_employees`;

-- How many rows still need backfilling (expect: all of them, for now).
-- SELECT COUNT(*) AS unlinked FROM `non_employees` WHERE `user_id` IS NULL;


-- --------------------------------------------------------
-- Rollback
-- --------------------------------------------------------
-- The foreign key must be dropped before the unique index it relies on.
--
-- ALTER TABLE `non_employees`
--     DROP FOREIGN KEY `non_employees_user_id_foreign`,
--     DROP INDEX `non_employees_user_id_unique`,
--     DROP COLUMN `user_id`;

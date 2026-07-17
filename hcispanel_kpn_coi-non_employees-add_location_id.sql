-- --------------------------------------------------------
-- Database:  hcispanel_kpn_coi  (local application database)
-- Table:     non_employees
-- Purpose:   Add the location reference, selected per business unit.
-- --------------------------------------------------------
--
-- Why there is no FOREIGN KEY here
-- -------------------------------
-- locations lives in the kpncorp database (hcispanel_hc_stage), not in this
-- one. Both happen to sit on the same MySQL instance in staging, so a
-- cross-schema FK would technically work today -- but the two databases are
-- expected to be separated on the real server, which would break it. So this
-- is a soft link, validated in application code, exactly like
-- users.employee_id -> kpncorp.employees.
--
-- Naming note
-- -----------
-- non_employees.group_company uses the application's business unit naming
-- ("Plantations"), while kpncorp.locations.company_name uses the HRIS naming
-- ("KPN Plantations"). The lookup maps between them -- see App\Models\Location.
--
-- Type matches kpncorp.locations.id, which is `bigint unsigned`.


-- --------------------------------------------------------
-- Apply
-- --------------------------------------------------------

ALTER TABLE `non_employees`
    ADD COLUMN `location_id` bigint unsigned DEFAULT NULL AFTER `group_company`,
    ADD KEY `non_employees_location_id_index` (`location_id`);


-- --------------------------------------------------------
-- Verify
-- --------------------------------------------------------

-- SHOW CREATE TABLE `non_employees`;
-- SELECT COUNT(*) AS without_location FROM `non_employees` WHERE `location_id` IS NULL;


-- --------------------------------------------------------
-- Rollback
-- --------------------------------------------------------
--
-- ALTER TABLE `non_employees`
--     DROP INDEX `non_employees_location_id_index`,
--     DROP COLUMN `location_id`;

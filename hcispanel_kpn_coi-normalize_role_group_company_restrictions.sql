-- Normalize the group company data restriction on roles.
--
-- Equivalent of the migration
--   2026_07_23_000000_normalize_group_company_restrictions_on_roles.php
-- for environments where `php artisan migrate` cannot be run.
--
-- Why: the restriction used to be saved as master_bisnisunits.kode_bisnis
-- ("BU03"), but the tables it is matched against -- kpncorp.employees and
-- non_employees -- store the unit name ("Cement"). A role holding codes could
-- never match a single row. The role form now offers names; this moves the
-- roles already saved onto the same value space.
--
-- Target database: the app database (hcispanel_kpn_coi), where `roles` lives.
-- The kode -> nama map is inlined rather than joined from
-- kpncorp.master_bisnisunits, so this runs even when the two databases sit on
-- different servers. Verify the map below still matches that table first.
--
-- Requires MySQL 8.0+ (JSON_TABLE). Confirmed on 8.4.3.
--
-- Safe to re-run: a value that is already a name is left untouched, and a code
-- with no entry in the map is left untouched (DataScopeService still accepts
-- either form, so nothing breaks either way).


-- ---------------------------------------------------------------------------
-- 1. Preview -- run this first and check the "after" column
-- ---------------------------------------------------------------------------

SELECT
    r.id,
    r.name,
    JSON_EXTRACT(r.restrictions, '$.group_company') AS before_group_company,
    (
        SELECT JSON_ARRAYAGG(unit)
        FROM (
            SELECT DISTINCT COALESCE(m.nama_bisnis, gc.val) AS unit
            FROM JSON_TABLE(
                     JSON_EXTRACT(r.restrictions, '$.group_company'),
                     '$[*]' COLUMNS (val VARCHAR(255) PATH '$')
                 ) AS gc
            LEFT JOIN (
                          SELECT 'BU01' AS kode_bisnis, 'Plantations'     AS nama_bisnis
                UNION ALL SELECT 'BU02',                'Property'
                UNION ALL SELECT 'BU03',                'Cement'
                UNION ALL SELECT 'BU04',                'Katingan'
                UNION ALL SELECT 'BU05',                'KPN Corporation'
                UNION ALL SELECT 'BU06',                'Downstream'
                UNION ALL SELECT 'BU07',                'Others'
            ) AS m ON m.kode_bisnis = gc.val
        ) AS d
    ) AS after_group_company
FROM roles AS r
WHERE JSON_VALID(r.restrictions)
  AND JSON_TYPE(JSON_EXTRACT(r.restrictions, '$.group_company')) = 'ARRAY'
  -- A role that restricts nothing has an empty array and is never touched;
  -- listing it here would only show a confusing NULL "after".
  AND JSON_LENGTH(JSON_EXTRACT(r.restrictions, '$.group_company')) > 0
ORDER BY r.id;


-- ---------------------------------------------------------------------------
-- 2. Back up the current values before changing anything
-- ---------------------------------------------------------------------------

CREATE TABLE roles_restrictions_backup_20260723 AS
SELECT id, name, restrictions, NOW() AS backed_up_at
FROM roles;


-- ---------------------------------------------------------------------------
-- 3. Apply
-- ---------------------------------------------------------------------------

UPDATE roles AS r
JOIN (
    SELECT role_id, JSON_ARRAYAGG(unit) AS group_company
    FROM (
        -- DISTINCT collapses a role that already holds both a code and its
        -- name ("BU03" + "Cement") into the single mapped value.
        SELECT DISTINCT
            r.id AS role_id,
            COALESCE(m.nama_bisnis, gc.val) AS unit
        FROM roles AS r
        -- An inner join here is deliberate: a role whose group_company array is
        -- empty produces no rows, so it is never touched by the UPDATE.
        JOIN JSON_TABLE(
                 JSON_EXTRACT(r.restrictions, '$.group_company'),
                 '$[*]' COLUMNS (val VARCHAR(255) PATH '$')
             ) AS gc
        LEFT JOIN (
                      SELECT 'BU01' AS kode_bisnis, 'Plantations'     AS nama_bisnis
            UNION ALL SELECT 'BU02',                'Property'
            UNION ALL SELECT 'BU03',                'Cement'
            UNION ALL SELECT 'BU04',                'Katingan'
            UNION ALL SELECT 'BU05',                'KPN Corporation'
            UNION ALL SELECT 'BU06',                'Downstream'
            UNION ALL SELECT 'BU07',                'Others'
        ) AS m ON m.kode_bisnis = gc.val
        -- Skips a row whose restrictions is an empty string or is missing the
        -- group_company key entirely.
        WHERE JSON_VALID(r.restrictions)
          AND JSON_TYPE(JSON_EXTRACT(r.restrictions, '$.group_company')) = 'ARRAY'
    ) AS d
    GROUP BY role_id
) AS mapped ON mapped.role_id = r.id
SET r.restrictions = JSON_SET(
        r.restrictions,
        '$.group_company',
        mapped.group_company
    );


-- ---------------------------------------------------------------------------
-- 4. Record the migration so `php artisan migrate` does not re-run it
--    Skip this if the environment applied the PHP migration instead.
-- ---------------------------------------------------------------------------

-- The guard has to be HAVING, not WHERE: an aggregate with no GROUP BY always
-- produces exactly one row, so a WHERE that matched nothing would still insert
-- a duplicate with batch 1.
INSERT INTO migrations (migration, batch)
SELECT
    '2026_07_23_000000_normalize_group_company_restrictions_on_roles',
    COALESCE(MAX(batch), 0) + 1
FROM migrations
HAVING SUM(
    migration = '2026_07_23_000000_normalize_group_company_restrictions_on_roles'
) = 0;


-- ---------------------------------------------------------------------------
-- 5. Verify
-- ---------------------------------------------------------------------------

SELECT id, name, restrictions FROM roles ORDER BY id;


-- ---------------------------------------------------------------------------
-- Rollback -- restores every role from the backup taken in step 2
-- ---------------------------------------------------------------------------
--
-- UPDATE roles AS r
-- JOIN roles_restrictions_backup_20260723 AS b ON b.id = r.id
-- SET r.restrictions = b.restrictions;
--
-- DROP TABLE roles_restrictions_backup_20260723;

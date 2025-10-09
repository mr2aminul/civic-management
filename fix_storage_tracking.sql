-- Fix storage tracking duplicate entry issues and recalculate storage
-- Run this script to fix the duplicate entry errors

-- First, make sure the table has the proper primary key
-- (This is already defined in the migration, but let's ensure it)

-- Remove any duplicate entries that might exist
DELETE t1 FROM fm_user_storage_tracking t1
INNER JOIN fm_user_storage_tracking t2
WHERE t1.user_id = t2.user_id AND t1.created_at > t2.created_at;

-- Create a temporary table with correct calculations
CREATE TEMPORARY TABLE IF NOT EXISTS temp_storage_calc AS
SELECT
    f.user_id,
    COUNT(CASE WHEN f.is_folder = 0 AND f.is_deleted = 0 THEN 1 END) as total_files,
    COUNT(CASE WHEN f.is_folder = 1 AND f.is_deleted = 0 THEN 1 END) as total_folders,
    COALESCE(SUM(CASE WHEN f.is_folder = 0 AND f.is_deleted = 0 THEN f.size ELSE 0 END), 0) as used_bytes,
    COALESCE(SUM(CASE WHEN f.is_folder = 0 AND f.is_deleted = 0 AND f.r2_uploaded = 1 THEN f.size ELSE 0 END), 0) as r2_uploaded_bytes,
    MAX(CASE WHEN f.is_folder = 0 THEN f.created_at END) as last_upload_at
FROM fm_files f
GROUP BY f.user_id;

-- Insert or update storage tracking with calculated values
INSERT INTO fm_user_storage_tracking
    (user_id, total_files, total_folders, used_bytes, quota_bytes,
     r2_uploaded_bytes, local_only_bytes, last_upload_at, last_calculated_at, created_at, updated_at)
SELECT
    tsc.user_id,
    tsc.total_files,
    tsc.total_folders,
    tsc.used_bytes,
    COALESCE(uq.quota_bytes, 1073741824) as quota_bytes,
    tsc.r2_uploaded_bytes,
    (tsc.used_bytes - tsc.r2_uploaded_bytes) as local_only_bytes,
    tsc.last_upload_at,
    NOW() as last_calculated_at,
    NOW() as created_at,
    NOW() as updated_at
FROM temp_storage_calc tsc
LEFT JOIN fm_user_quotas uq ON tsc.user_id = uq.user_id
ON DUPLICATE KEY UPDATE
    total_files = VALUES(total_files),
    total_folders = VALUES(total_folders),
    used_bytes = VALUES(used_bytes),
    quota_bytes = VALUES(quota_bytes),
    r2_uploaded_bytes = VALUES(r2_uploaded_bytes),
    local_only_bytes = VALUES(local_only_bytes),
    last_upload_at = VALUES(last_upload_at),
    last_calculated_at = NOW(),
    updated_at = NOW();

-- Also sync fm_user_quotas table
INSERT INTO fm_user_quotas (user_id, used_bytes, quota_bytes, updated_at)
SELECT
    user_id,
    used_bytes,
    quota_bytes,
    NOW()
FROM fm_user_storage_tracking
ON DUPLICATE KEY UPDATE
    used_bytes = VALUES(used_bytes),
    updated_at = NOW();

-- Clean up
DROP TEMPORARY TABLE IF EXISTS temp_storage_calc;

-- Show results
SELECT
    COUNT(DISTINCT user_id) as total_users_tracked,
    SUM(total_files) as total_files,
    SUM(used_bytes) as total_storage_bytes,
    SUM(r2_uploaded_bytes) as r2_storage_bytes,
    SUM(local_only_bytes) as local_storage_bytes
FROM fm_user_storage_tracking;

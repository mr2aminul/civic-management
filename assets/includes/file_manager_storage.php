<?php
/**
 * File Manager Storage Tracking Functions
 * Efficient and bug-free storage quota management
 */

// ============================================
// User Quota Management
// ============================================

/**
 * Get user quota information
 * @param int $userId
 * @return array ['quota' => int, 'used' => int, 'total_files' => int, 'total_folders' => int, ...]
 */
function fm_get_user_quota($userId) {
    global $db;

    $userId = (int)$userId;

    try {
        $db->where('user_id', $userId);
        $result = $db->getOne('fm_user_quotas', [
            'quota_bytes',
            'used_bytes',
            'total_files',
            'total_folders',
            'r2_uploaded_bytes',
            'local_only_bytes',
            'last_upload_at'
        ]);

        if ($result) {
            return [
                'quota' => (int)$result->quota_bytes,
                'used' => (int)$result->used_bytes,
                'total_files' => (int)$result->total_files,
                'total_folders' => (int)$result->total_folders,
                'r2_uploaded_bytes' => (int)$result->r2_uploaded_bytes,
                'local_only_bytes' => (int)$result->local_only_bytes,
                'available' => (int)$result->quota_bytes - (int)$result->used_bytes,
                'percentage' => $result->quota_bytes > 0
                    ? round(((int)$result->used_bytes / (int)$result->quota_bytes) * 100, 2)
                    : 0,
                'last_upload_at' => $result->last_upload_at
            ];
        }
    } catch (Exception $e) {
        error_log("fm_get_user_quota: Failed for user {$userId}. Error: " . $e->getMessage());
    }

    // Create default quota if not exists
    $cfg = fm_get_config();
    $defaultQuota = $cfg['default_quota'];

    $insertData = [
        'user_id' => $userId,
        'quota_bytes' => $defaultQuota,
        'used_bytes' => 0,
        'total_files' => 0,
        'total_folders' => 0,
        'r2_uploaded_bytes' => 0,
        'local_only_bytes' => 0,
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ];

    try {
        $db->insert('fm_user_quotas', $insertData);
    } catch (Exception $e) {
        error_log("fm_get_user_quota: Failed to create default quota for user {$userId}. Error: " . $e->getMessage());
    }

    return [
        'quota' => $defaultQuota,
        'used' => 0,
        'total_files' => 0,
        'total_folders' => 0,
        'r2_uploaded_bytes' => 0,
        'local_only_bytes' => 0,
        'available' => $defaultQuota,
        'percentage' => 0,
        'last_upload_at' => null
    ];
}

/**
 * Check if user has enough quota for upload
 * @param int $userId
 * @param int $requiredBytes
 * @return bool
 */
function fm_check_quota($userId, $requiredBytes) {
    $quota = fm_get_user_quota($userId);
    return ($quota['used'] + $requiredBytes) <= $quota['quota'];
}

/**
 * Set user quota limit
 * @param int $userId
 * @param int $quotaBytes
 * @return bool
 */
function fm_set_user_quota($userId, $quotaBytes) {
    global $db;

    $userId = (int)$userId;
    $quotaBytes = (int)$quotaBytes;

    try {
        $db->where('user_id', $userId);
        $exists = $db->getOne('fm_user_quotas', 'user_id');

        if ($exists) {
            $db->where('user_id', $userId);
            return $db->update('fm_user_quotas', [
                'quota_bytes' => $quotaBytes,
                'updated_at' => date('Y-m-d H:i:s')
            ]);
        } else {
            return $db->insert('fm_user_quotas', [
                'user_id' => $userId,
                'quota_bytes' => $quotaBytes,
                'used_bytes' => 0,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);
        }
    } catch (Exception $e) {
        error_log("fm_set_user_quota: Failed for user {$userId}. Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Recalculate user storage from database (source of truth)
 * This function recalculates storage from fm_files table
 * @param int $userId
 * @return bool
 */
function fm_recalculate_user_storage($userId) {
    global $db;

    $userId = (int)$userId;

    try {
        // Get current quota setting
        $db->where('user_id', $userId);
        $current = $db->getOne('fm_user_quotas', 'quota_bytes');
        $quotaBytes = $current ? (int)$current->quota_bytes : fm_get_config()['default_quota'];

        // Calculate from fm_files
        $db->where('user_id', $userId);
        $db->where('is_deleted', 0);
        $db->where('is_folder', 0);
        $files = $db->get('fm_files', null, ['size', 'r2_uploaded']);

        $totalSize = 0;
        $r2Size = 0;
        $totalFiles = 0;

        if ($files) {
            foreach ($files as $file) {
                $size = (int)$file->size;
                $totalSize += $size;
                if ($file->r2_uploaded == 1) {
                    $r2Size += $size;
                }
                $totalFiles++;
            }
        }

        // Count folders
        $db->where('user_id', $userId);
        $db->where('is_deleted', 0);
        $db->where('is_folder', 1);
        $totalFolders = $db->getValue('fm_files', 'COUNT(*)');

        $localSize = $totalSize - $r2Size;

        // Update or insert into fm_user_quotas
        $data = [
            'quota_bytes' => $quotaBytes,
            'used_bytes' => $totalSize,
            'total_files' => $totalFiles,
            'total_folders' => (int)$totalFolders,
            'r2_uploaded_bytes' => $r2Size,
            'local_only_bytes' => $localSize,
            'updated_at' => date('Y-m-d H:i:s')
        ];

        $db->where('user_id', $userId);
        $exists = $db->getOne('fm_user_quotas', 'user_id');

        if ($exists) {
            $db->where('user_id', $userId);
            $db->update('fm_user_quotas', $data);
        } else {
            $data['user_id'] = $userId;
            $data['created_at'] = date('Y-m-d H:i:s');
            $db->insert('fm_user_quotas', $data);
        }

        // Also update fm_user_storage_tracking if it exists
        $trackingData = [
            'total_files' => $totalFiles,
            'total_folders' => (int)$totalFolders,
            'used_bytes' => $totalSize,
            'quota_bytes' => $quotaBytes,
            'r2_uploaded_bytes' => $r2Size,
            'local_only_bytes' => $localSize,
            'last_calculated_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];

        $db->where('user_id', $userId);
        $trackingExists = $db->getOne('fm_user_storage_tracking', 'user_id');

        if ($trackingExists) {
            $db->where('user_id', $userId);
            $db->update('fm_user_storage_tracking', $trackingData);
        } else {
            $trackingData['user_id'] = $userId;
            $trackingData['created_at'] = date('Y-m-d H:i:s');
            $db->insert('fm_user_storage_tracking', $trackingData);
        }

        return true;
    } catch (Exception $e) {
        error_log("fm_recalculate_user_storage: Failed for user {$userId}. Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Recalculate storage for all users
 * @return array ['success' => bool, 'updated' => int, 'failed' => int]
 */
function fm_recalculate_all_storage() {
    global $db;

    try {
        // Get all unique user IDs from fm_files
        $db->where('is_deleted', 0);
        $users = $db->get('fm_files', null, 'DISTINCT user_id');

        $updated = 0;
        $failed = 0;

        if ($users) {
            foreach ($users as $user) {
                $userId = (int)$user->user_id;
                if ($userId > 0) {
                    if (fm_recalculate_user_storage($userId)) {
                        $updated++;
                    } else {
                        $failed++;
                    }
                }
            }
        }

        return [
            'success' => true,
            'updated' => $updated,
            'failed' => $failed,
            'message' => "Recalculated storage for {$updated} users. Failed: {$failed}"
        ];
    } catch (Exception $e) {
        error_log("fm_recalculate_all_storage: Failed. Error: " . $e->getMessage());
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}

/**
 * Get global storage statistics
 * @return array
 */
function fm_get_global_storage_stats() {
    global $db;

    try {
        // Try fm_user_storage_tracking first (newer table)
        $trackingExists = $db->tableExists('fm_user_storage_tracking');

        if ($trackingExists) {
            $result = $db->getOne('fm_user_storage_tracking', [
                'COUNT(DISTINCT user_id) as total_users',
                'SUM(used_bytes) as total_used',
                'SUM(quota_bytes) as total_quota',
                'SUM(total_files) as all_files',
                'SUM(total_folders) as all_folders',
                'SUM(r2_uploaded_bytes) as total_r2',
                'SUM(local_only_bytes) as total_local'
            ]);
        } else {
            // Fallback to fm_user_quotas
            $result = $db->getOne('fm_user_quotas', [
                'COUNT(DISTINCT user_id) as total_users',
                'SUM(used_bytes) as total_used',
                'SUM(quota_bytes) as total_quota',
                'SUM(total_files) as all_files',
                'SUM(total_folders) as all_folders',
                'SUM(r2_uploaded_bytes) as total_r2',
                'SUM(local_only_bytes) as total_local'
            ]);
        }

        if ($result) {
            $totalUsed = (int)($result->total_used ?? 0);
            $vpsTotal = 64424509440; // 60 GB

            return [
                'total_users' => (int)($result->total_users ?? 0),
                'total_used_bytes' => $totalUsed,
                'total_quota_bytes' => (int)($result->total_quota ?? 0),
                'total_files' => (int)($result->all_files ?? 0),
                'total_folders' => (int)($result->all_folders ?? 0),
                'r2_uploaded_bytes' => (int)($result->total_r2 ?? 0),
                'local_only_bytes' => (int)($result->total_local ?? 0),
                'vps_total_bytes' => $vpsTotal,
                'vps_available_bytes' => max(0, $vpsTotal - $totalUsed),
                'vps_usage_percent' => $vpsTotal > 0 ? round(($totalUsed / $vpsTotal) * 100, 2) : 0
            ];
        }
    } catch (Exception $e) {
        error_log("fm_get_global_storage_stats: Failed. Error: " . $e->getMessage());
    }

    return [
        'total_users' => 0,
        'total_used_bytes' => 0,
        'total_quota_bytes' => 0,
        'total_files' => 0,
        'total_folders' => 0,
        'r2_uploaded_bytes' => 0,
        'local_only_bytes' => 0,
        'vps_total_bytes' => 64424509440,
        'vps_available_bytes' => 64424509440,
        'vps_usage_percent' => 0
    ];
}

/**
 * Get user storage statistics (for admin dashboard)
 * @param int $limit
 * @param int $offset
 * @return array
 */
function fm_get_user_storage_list($limit = 50, $offset = 0) {
    global $db;

    try {
        $db->orderBy('used_bytes', 'DESC');
        $db->pageLimit = $limit;
        $results = $db->get('fm_user_quotas', [$offset, $limit], [
            'user_id',
            'quota_bytes',
            'used_bytes',
            'total_files',
            'total_folders',
            'r2_uploaded_bytes',
            'local_only_bytes',
            'last_upload_at',
            'updated_at'
        ]);

        $users = [];
        if ($results) {
            foreach ($results as $row) {
                $users[] = [
                    'user_id' => (int)$row->user_id,
                    'quota_bytes' => (int)$row->quota_bytes,
                    'used_bytes' => (int)$row->used_bytes,
                    'total_files' => (int)$row->total_files,
                    'total_folders' => (int)$row->total_folders,
                    'r2_uploaded_bytes' => (int)$row->r2_uploaded_bytes,
                    'local_only_bytes' => (int)$row->local_only_bytes,
                    'usage_percent' => $row->quota_bytes > 0
                        ? round(((int)$row->used_bytes / (int)$row->quota_bytes) * 100, 2)
                        : 0,
                    'last_upload_at' => $row->last_upload_at,
                    'updated_at' => $row->updated_at
                ];
            }
        }

        // Get total count
        $totalCount = $db->getValue('fm_user_quotas', 'COUNT(*)');

        return [
            'success' => true,
            'users' => $users,
            'total' => (int)$totalCount,
            'limit' => $limit,
            'offset' => $offset
        ];
    } catch (Exception $e) {
        error_log("fm_get_user_storage_list: Failed. Error: " . $e->getMessage());
        return [
            'success' => false,
            'users' => [],
            'total' => 0,
            'message' => $e->getMessage()
        ];
    }
}

/**
 * Format bytes to human readable format
 * @param int $bytes
 * @param int $precision
 * @return string
 */
function fm_format_bytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= (1 << (10 * $pow));
    return round($bytes, $precision) . ' ' . $units[$pow];
}

/**
 * Legacy compatibility: Update storage tracking
 * Note: With triggers in place, this is mostly for manual recalculation
 * @param int $userId
 * @return bool
 */
function fm_update_storage_tracking($userId) {
    return fm_recalculate_user_storage($userId);
}

/**
 * Legacy compatibility: Update user quota delta
 * Note: This function is deprecated - use fm_recalculate_user_storage() instead
 * Use only for manual adjustments
 * @param int $userId
 * @param int $deltaBytes (positive to add, negative to subtract)
 * @return bool
 * @deprecated Use fm_recalculate_user_storage() for accurate storage tracking
 */
function fm_update_user_quota($userId, $deltaBytes) {
    global $db;

    $userId = (int)$userId;
    $deltaBytes = (int)$deltaBytes;

    try {
        // Get current values
        $db->where('user_id', $userId);
        $current = $db->getOne('fm_user_quotas', ['used_bytes', 'quota_bytes']);

        if ($current) {
            $newUsed = max(0, (int)$current->used_bytes + $deltaBytes);

            $db->where('user_id', $userId);
            $db->update('fm_user_quotas', [
                'used_bytes' => $newUsed,
                'updated_at' => date('Y-m-d H:i:s')
            ]);

            // Also update tracking table
            $db->where('user_id', $userId);
            $trackingExists = $db->getOne('fm_user_storage_tracking', 'user_id');

            if ($trackingExists) {
                $db->where('user_id', $userId);
                $db->update('fm_user_storage_tracking', [
                    'used_bytes' => $newUsed,
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
            }

            return true;
        } else {
            // Create new record
            $cfg = fm_get_config();
            $newUsed = max(0, $deltaBytes);

            $db->insert('fm_user_quotas', [
                'user_id' => $userId,
                'quota_bytes' => $cfg['default_quota'],
                'used_bytes' => $newUsed,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);

            // Also create tracking record
            $db->insert('fm_user_storage_tracking', [
                'user_id' => $userId,
                'quota_bytes' => $cfg['default_quota'],
                'used_bytes' => $newUsed,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);

            return true;
        }
    } catch (Exception $e) {
        error_log("fm_update_user_quota: Failed for user {$userId}. Error: " . $e->getMessage());
        return false;
    }
}

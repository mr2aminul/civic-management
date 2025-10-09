<?php
/**
 * Fix File Manager Folders and Storage Issues
 * This script:
 * 1. Creates default common and special folders
 * 2. Fixes storage tracking calculations
 * 3. Recalculates all user storage
 * 4. Fixes global VPS storage tracking
 */

require_once 'assets/includes/app_start.php';
require_once 'assets/includes/file_manager_helper.php';
require_once 'assets/includes/file_manager_storage.php';

echo "=== File Manager Folders and Storage Fix ===\n\n";

// =====================================================
// 1. Create Default Common Folders
// =====================================================
echo "Step 1: Creating default common folders...\n";

$commonFolders = [
    [
        'folder_name' => 'Project Pictures',
        'folder_key' => 'project_pictures',
        'folder_path' => 'Common/Project Pictures',
        'folder_icon' => 'bi-image',
        'folder_color' => '#10b981',
        'description' => 'Shared project pictures accessible to all users',
        'sort_order' => 1
    ],
    [
        'folder_name' => 'Project Videos',
        'folder_key' => 'project_videos',
        'folder_path' => 'Common/Project Videos',
        'folder_icon' => 'bi-camera-video',
        'folder_color' => '#8b5cf6',
        'description' => 'Shared project videos accessible to all users',
        'sort_order' => 2
    ],
    [
        'folder_name' => 'Project Documents',
        'folder_key' => 'project_documents',
        'folder_path' => 'Common/Project Documents',
        'folder_icon' => 'bi-file-earmark-text',
        'folder_color' => '#3b82f6',
        'description' => 'Shared project documents accessible to all users',
        'sort_order' => 3
    ],
    [
        'folder_name' => 'Templates',
        'folder_key' => 'templates',
        'folder_path' => 'Common/Templates',
        'folder_icon' => 'bi-file-earmark-code',
        'folder_color' => '#f59e0b',
        'description' => 'Document templates accessible to all users',
        'sort_order' => 4
    ]
];

foreach ($commonFolders as $folder) {
    // Check if folder exists
    $existing = fm_query(
        "SELECT id FROM fm_common_folders WHERE folder_key = ? LIMIT 1",
        [$folder['folder_key']]
    );

    if (empty($existing)) {
        $folder['created_by'] = 0;
        $folder['created_at'] = date('Y-m-d H:i:s');
        $folder['is_active'] = 1;

        $folderId = fm_insert('fm_common_folders', $folder);
        echo "  ✓ Created common folder: {$folder['folder_name']} (ID: {$folderId})\n";
    } else {
        echo "  - Common folder already exists: {$folder['folder_name']}\n";
    }
}

// =====================================================
// 2. Create Default Special Folders
// =====================================================
echo "\nStep 2: Creating default special folders...\n";

$specialFolders = [
    [
        'folder_name' => 'HR Documents',
        'folder_key' => 'hr_documents',
        'folder_path' => 'Special/HR Documents',
        'folder_icon' => 'bi-folder-lock',
        'folder_color' => '#ef4444',
        'description' => 'Human Resources documents - Restricted access',
        'requires_permission' => 1,
        'sort_order' => 1
    ],
    [
        'folder_name' => 'Financial Records',
        'folder_key' => 'financial_records',
        'folder_path' => 'Special/Financial Records',
        'folder_icon' => 'bi-cash-stack',
        'folder_color' => '#dc2626',
        'description' => 'Financial records and reports - Restricted access',
        'requires_permission' => 1,
        'sort_order' => 2
    ],
    [
        'folder_name' => 'Legal Documents',
        'folder_key' => 'legal_documents',
        'folder_path' => 'Special/Legal Documents',
        'folder_icon' => 'bi-file-earmark-ruled',
        'folder_color' => '#7c3aed',
        'description' => 'Legal contracts and documents - Restricted access',
        'requires_permission' => 1,
        'sort_order' => 3
    ]
];

foreach ($specialFolders as $folder) {
    // Check if folder exists
    $existing = fm_query(
        "SELECT id FROM fm_special_folders WHERE folder_key = ? LIMIT 1",
        [$folder['folder_key']]
    );

    if (empty($existing)) {
        $folder['created_by'] = null;
        $folder['created_at'] = date('Y-m-d H:i:s');
        $folder['is_active'] = 1;

        $folderId = fm_insert('fm_special_folders', $folder);
        echo "  ✓ Created special folder: {$folder['folder_name']} (ID: {$folderId})\n";
    } else {
        echo "  - Special folder already exists: {$folder['folder_name']}\n";
    }
}

// =====================================================
// 3. Fix Storage Tracking for All Users
// =====================================================
echo "\nStep 3: Recalculating storage for all users...\n";

// Get all users with files
$usersWithFiles = fm_query("
    SELECT DISTINCT user_id
    FROM fm_files
    WHERE is_deleted = 0
    AND user_id > 0
");

$successCount = 0;
$failCount = 0;

if (!empty($usersWithFiles)) {
    foreach ($usersWithFiles as $user) {
        $userId = (int)$user['user_id'];

        if (fm_recalculate_user_storage($userId)) {
            $successCount++;
            echo "  ✓ Recalculated storage for user {$userId}\n";
        } else {
            $failCount++;
            echo "  ✗ Failed to recalculate storage for user {$userId}\n";
        }
    }
}

echo "  Total: {$successCount} succeeded, {$failCount} failed\n";

// =====================================================
// 4. Verify and Fix Global Storage Tracking
// =====================================================
echo "\nStep 4: Verifying global storage tracking...\n";

$globalStats = fm_get_global_storage_stats();

echo "  Total Users: {$globalStats['total_users']}\n";
echo "  Total Files: {$globalStats['total_files']}\n";
echo "  Total Folders: {$globalStats['total_folders']}\n";
echo "  Total Used: " . fm_format_bytes($globalStats['total_used_bytes']) . "\n";
echo "  VPS Total: " . fm_format_bytes($globalStats['vps_total_bytes']) . "\n";
echo "  Usage %: {$globalStats['vps_usage_percent']}%\n";
echo "  R2 Storage: " . fm_format_bytes($globalStats['r2_uploaded_bytes']) . "\n";
echo "  Local Storage: " . fm_format_bytes($globalStats['local_only_bytes']) . "\n";

// =====================================================
// 5. Fix File Deletion Storage Update Trigger
// =====================================================
echo "\nStep 5: Creating storage update triggers...\n";

// Drop existing triggers if they exist
$triggers = [
    'trg_fm_files_after_insert',
    'trg_fm_files_after_update',
    'trg_fm_files_after_delete'
];

foreach ($triggers as $trigger) {
    fm_query("DROP TRIGGER IF EXISTS {$trigger}");
}

// Create trigger for INSERT
$sql = "
CREATE TRIGGER trg_fm_files_after_insert
AFTER INSERT ON fm_files
FOR EACH ROW
BEGIN
    IF NEW.is_folder = 0 THEN
        UPDATE fm_user_quotas
        SET
            used_bytes = used_bytes + NEW.size,
            total_files = total_files + 1,
            r2_uploaded_bytes = CASE WHEN NEW.r2_uploaded = 1 THEN r2_uploaded_bytes + NEW.size ELSE r2_uploaded_bytes END,
            local_only_bytes = CASE WHEN NEW.r2_uploaded = 0 THEN local_only_bytes + NEW.size ELSE local_only_bytes END,
            last_upload_at = NOW(),
            updated_at = NOW()
        WHERE user_id = NEW.user_id;

        UPDATE fm_user_storage_tracking
        SET
            used_bytes = used_bytes + NEW.size,
            total_files = total_files + 1,
            r2_uploaded_bytes = CASE WHEN NEW.r2_uploaded = 1 THEN r2_uploaded_bytes + NEW.size ELSE r2_uploaded_bytes END,
            local_only_bytes = CASE WHEN NEW.r2_uploaded = 0 THEN local_only_bytes + NEW.size ELSE local_only_bytes END,
            last_upload_at = NOW(),
            updated_at = NOW()
        WHERE user_id = NEW.user_id;
    END IF;
END;
";

if (fm_query($sql)) {
    echo "  ✓ Created INSERT trigger\n";
} else {
    echo "  ✗ Failed to create INSERT trigger\n";
}

// Create trigger for UPDATE (when is_deleted changes)
$sql = "
CREATE TRIGGER trg_fm_files_after_update
AFTER UPDATE ON fm_files
FOR EACH ROW
BEGIN
    IF OLD.is_folder = 0 THEN
        IF OLD.is_deleted = 0 AND NEW.is_deleted = 1 THEN
            -- File was deleted, decrease storage
            UPDATE fm_user_quotas
            SET
                used_bytes = GREATEST(0, used_bytes - OLD.size),
                total_files = GREATEST(0, total_files - 1),
                r2_uploaded_bytes = CASE WHEN OLD.r2_uploaded = 1 THEN GREATEST(0, r2_uploaded_bytes - OLD.size) ELSE r2_uploaded_bytes END,
                local_only_bytes = CASE WHEN OLD.r2_uploaded = 0 THEN GREATEST(0, local_only_bytes - OLD.size) ELSE local_only_bytes END,
                updated_at = NOW()
            WHERE user_id = OLD.user_id;

            UPDATE fm_user_storage_tracking
            SET
                used_bytes = GREATEST(0, used_bytes - OLD.size),
                total_files = GREATEST(0, total_files - 1),
                r2_uploaded_bytes = CASE WHEN OLD.r2_uploaded = 1 THEN GREATEST(0, r2_uploaded_bytes - OLD.size) ELSE r2_uploaded_bytes END,
                local_only_bytes = CASE WHEN OLD.r2_uploaded = 0 THEN GREATEST(0, local_only_bytes - OLD.size) ELSE local_only_bytes END,
                updated_at = NOW()
            WHERE user_id = OLD.user_id;

        ELSEIF OLD.is_deleted = 1 AND NEW.is_deleted = 0 THEN
            -- File was restored, increase storage
            UPDATE fm_user_quotas
            SET
                used_bytes = used_bytes + NEW.size,
                total_files = total_files + 1,
                r2_uploaded_bytes = CASE WHEN NEW.r2_uploaded = 1 THEN r2_uploaded_bytes + NEW.size ELSE r2_uploaded_bytes END,
                local_only_bytes = CASE WHEN NEW.r2_uploaded = 0 THEN local_only_bytes + NEW.size ELSE local_only_bytes END,
                updated_at = NOW()
            WHERE user_id = NEW.user_id;

            UPDATE fm_user_storage_tracking
            SET
                used_bytes = used_bytes + NEW.size,
                total_files = total_files + 1,
                r2_uploaded_bytes = CASE WHEN NEW.r2_uploaded = 1 THEN r2_uploaded_bytes + NEW.size ELSE r2_uploaded_bytes END,
                local_only_bytes = CASE WHEN NEW.r2_uploaded = 0 THEN local_only_bytes + NEW.size ELSE local_only_bytes END,
                updated_at = NOW()
            WHERE user_id = NEW.user_id;
        END IF;
    END IF;
END;
";

if (fm_query($sql)) {
    echo "  ✓ Created UPDATE trigger\n";
} else {
    echo "  ✗ Failed to create UPDATE trigger\n";
}

// =====================================================
// 6. Verify Fixes
// =====================================================
echo "\nStep 6: Verifying fixes...\n";

// Check common folders
$commonCount = fm_query("SELECT COUNT(*) as cnt FROM fm_common_folders WHERE is_active = 1");
$commonCount = $commonCount[0]['cnt'] ?? 0;
echo "  Common folders (active): {$commonCount}\n";

// Check special folders
$specialCount = fm_query("SELECT COUNT(*) as cnt FROM fm_special_folders WHERE is_active = 1");
$specialCount = $specialCount[0]['cnt'] ?? 0;
echo "  Special folders (active): {$specialCount}\n";

// Check storage tracking
$trackingCount = fm_query("SELECT COUNT(*) as cnt FROM fm_user_storage_tracking WHERE used_bytes > 0");
$trackingCount = $trackingCount[0]['cnt'] ?? 0;
echo "  Users with storage tracking: {$trackingCount}\n";

// Check triggers
$triggers = fm_query("SHOW TRIGGERS WHERE `Table` = 'fm_files'");
$triggerCount = count($triggers ?? []);
echo "  Active triggers: {$triggerCount}\n";

echo "\n=== Fix Complete ===\n";
echo "\nNext steps:\n";
echo "1. Refresh the file manager page\n";
echo "2. Common and special folders should now appear in the sidebar\n";
echo "3. Storage tracking should update correctly on file upload/delete\n";
echo "4. Global VPS usage should show correct values for admins\n";

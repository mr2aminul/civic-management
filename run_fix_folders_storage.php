<!DOCTYPE html>
<html>
<head>
    <title>Fix Folders and Storage - File Manager</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 1200px;
            margin: 40px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            border-bottom: 3px solid #3b82f6;
            padding-bottom: 10px;
        }
        h2 {
            color: #555;
            margin-top: 30px;
        }
        .step {
            margin: 20px 0;
            padding: 15px;
            background: #f9fafb;
            border-left: 4px solid #3b82f6;
        }
        .success {
            color: #10b981;
            font-weight: bold;
        }
        .error {
            color: #ef4444;
            font-weight: bold;
        }
        .info {
            color: #3b82f6;
        }
        pre {
            background: #1e293b;
            color: #e2e8f0;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
        }
        .warning {
            background: #fef3c7;
            border: 1px solid #f59e0b;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
        .button {
            display: inline-block;
            padding: 12px 24px;
            background: #3b82f6;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 10px 10px 10px 0;
        }
        .button:hover {
            background: #2563eb;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        th, td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }
        th {
            background: #f3f4f6;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 File Manager Folders & Storage Fix</h1>

        <?php
        // Check if running in web context
        if (php_sapi_name() === 'cli') {
            die('This script must be run through a web browser.');
        }

        // Load dependencies
        require_once 'assets/includes/app_start.php';
        require_once 'assets/includes/file_manager_helper.php';
        require_once 'assets/includes/file_manager_storage.php';

        // Check admin access
        if (!function_exists('Wo_IsAdmin') || !Wo_IsAdmin()) {
            echo '<div class="warning"><strong>⚠️ Access Denied</strong><br>This tool requires administrator access.</div>';
            exit;
        }

        $action = $_GET['action'] ?? 'show';

        if ($action === 'show') {
            ?>
            <div class="warning">
                <strong>⚠️ Important</strong><br>
                This tool will fix the following issues:
                <ul>
                    <li>Create default common and special folders</li>
                    <li>Recalculate storage usage for all users</li>
                    <li>Fix storage tracking on file upload/delete</li>
                    <li>Fix global VPS storage calculations</li>
                    <li>Create database triggers for automatic storage updates</li>
                </ul>
            </div>

            <h2>Current Status</h2>
            <div class="step">
                <?php
                // Check common folders
                $commonCount = fm_query("SELECT COUNT(*) as cnt FROM fm_common_folders WHERE is_active = 1");
                $commonCount = $commonCount[0]['cnt'] ?? 0;
                echo "<p>Common folders (active): <strong>{$commonCount}</strong></p>";

                // Check special folders
                $specialCount = fm_query("SELECT COUNT(*) as cnt FROM fm_special_folders WHERE is_active = 1");
                $specialCount = $specialCount[0]['cnt'] ?? 0;
                echo "<p>Special folders (active): <strong>{$specialCount}</strong></p>";

                // Check storage tracking
                $trackingCount = fm_query("SELECT COUNT(*) as cnt FROM fm_user_storage_tracking WHERE used_bytes > 0");
                $trackingCount = $trackingCount[0]['cnt'] ?? 0;
                echo "<p>Users with storage tracking: <strong>{$trackingCount}</strong></p>";

                // Global storage
                $globalStats = fm_get_global_storage_stats();
                echo "<p>Global VPS Usage: <strong>" . fm_format_bytes($globalStats['total_used_bytes']) . " / " . fm_format_bytes($globalStats['vps_total_bytes']) . " ({$globalStats['vps_usage_percent']}%)</strong></p>";
                ?>
            </div>

            <a href="?action=run" class="button">▶️ Run Fix Now</a>
            <a href="manage/pages/file_manager" class="button" style="background: #6b7280;">Back to File Manager</a>

            <?php
        } elseif ($action === 'run') {
            ?>
            <h2>Step 1: Creating Default Common Folders</h2>
            <div class="step">
                <?php
                $commonFolders = [
                    ['folder_name' => 'Project Pictures', 'folder_key' => 'project_pictures', 'folder_path' => 'Common/Project Pictures', 'folder_icon' => 'bi-image', 'folder_color' => '#10b981', 'description' => 'Shared project pictures accessible to all users', 'sort_order' => 1],
                    ['folder_name' => 'Project Videos', 'folder_key' => 'project_videos', 'folder_path' => 'Common/Project Videos', 'folder_icon' => 'bi-camera-video', 'folder_color' => '#8b5cf6', 'description' => 'Shared project videos accessible to all users', 'sort_order' => 2],
                    ['folder_name' => 'Project Documents', 'folder_key' => 'project_documents', 'folder_path' => 'Common/Project Documents', 'folder_icon' => 'bi-file-earmark-text', 'folder_color' => '#3b82f6', 'description' => 'Shared project documents accessible to all users', 'sort_order' => 3],
                    ['folder_name' => 'Templates', 'folder_key' => 'templates', 'folder_path' => 'Common/Templates', 'folder_icon' => 'bi-file-earmark-code', 'folder_color' => '#f59e0b', 'description' => 'Document templates accessible to all users', 'sort_order' => 4]
                ];

                foreach ($commonFolders as $folder) {
                    $existing = fm_query("SELECT id FROM fm_common_folders WHERE folder_key = ? LIMIT 1", [$folder['folder_key']]);

                    if (empty($existing)) {
                        $folder['created_by'] = 0;
                        $folder['created_at'] = date('Y-m-d H:i:s');
                        $folder['is_active'] = 1;
                        $folderId = fm_insert('fm_common_folders', $folder);
                        echo "<p class='success'>✓ Created: {$folder['folder_name']} (ID: {$folderId})</p>";
                    } else {
                        echo "<p class='info'>- Already exists: {$folder['folder_name']}</p>";
                    }
                }
                ?>
            </div>

            <h2>Step 2: Creating Default Special Folders</h2>
            <div class="step">
                <?php
                $specialFolders = [
                    ['folder_name' => 'HR Documents', 'folder_key' => 'hr_documents', 'folder_path' => 'Special/HR Documents', 'folder_icon' => 'bi-folder-lock', 'folder_color' => '#ef4444', 'description' => 'Human Resources documents - Restricted access', 'requires_permission' => 1, 'sort_order' => 1],
                    ['folder_name' => 'Financial Records', 'folder_key' => 'financial_records', 'folder_path' => 'Special/Financial Records', 'folder_icon' => 'bi-cash-stack', 'folder_color' => '#dc2626', 'description' => 'Financial records and reports - Restricted access', 'requires_permission' => 1, 'sort_order' => 2],
                    ['folder_name' => 'Legal Documents', 'folder_key' => 'legal_documents', 'folder_path' => 'Special/Legal Documents', 'folder_icon' => 'bi-file-earmark-ruled', 'folder_color' => '#7c3aed', 'description' => 'Legal contracts and documents - Restricted access', 'requires_permission' => 1, 'sort_order' => 3]
                ];

                foreach ($specialFolders as $folder) {
                    $existing = fm_query("SELECT id FROM fm_special_folders WHERE folder_key = ? LIMIT 1", [$folder['folder_key']]);

                    if (empty($existing)) {
                        $folder['created_by'] = null;
                        $folder['created_at'] = date('Y-m-d H:i:s');
                        $folder['is_active'] = 1;
                        $folderId = fm_insert('fm_special_folders', $folder);
                        echo "<p class='success'>✓ Created: {$folder['folder_name']} (ID: {$folderId})</p>";
                    } else {
                        echo "<p class='info'>- Already exists: {$folder['folder_name']}</p>";
                    }
                }
                ?>
            </div>

            <h2>Step 3: Recalculating User Storage</h2>
            <div class="step">
                <?php
                $usersWithFiles = fm_query("SELECT DISTINCT user_id FROM fm_files WHERE is_deleted = 0 AND user_id > 0");
                $successCount = 0;
                $failCount = 0;

                if (!empty($usersWithFiles)) {
                    foreach ($usersWithFiles as $user) {
                        $userId = (int)$user['user_id'];
                        if (fm_recalculate_user_storage($userId)) {
                            $successCount++;
                            echo "<p class='success'>✓ User {$userId}</p>";
                        } else {
                            $failCount++;
                            echo "<p class='error'>✗ User {$userId}</p>";
                        }
                    }
                }

                echo "<p><strong>Total:</strong> {$successCount} succeeded, {$failCount} failed</p>";
                ?>
            </div>

            <h2>Step 4: Creating Storage Update Triggers</h2>
            <div class="step">
                <?php
                // Drop existing triggers
                $triggers = ['trg_fm_files_after_insert', 'trg_fm_files_after_update'];
                foreach ($triggers as $trigger) {
                    fm_query("DROP TRIGGER IF EXISTS {$trigger}");
                }

                // Create INSERT trigger
                $sql = "CREATE TRIGGER trg_fm_files_after_insert AFTER INSERT ON fm_files FOR EACH ROW BEGIN IF NEW.is_folder = 0 THEN UPDATE fm_user_quotas SET used_bytes = used_bytes + NEW.size, total_files = total_files + 1, r2_uploaded_bytes = CASE WHEN NEW.r2_uploaded = 1 THEN r2_uploaded_bytes + NEW.size ELSE r2_uploaded_bytes END, local_only_bytes = CASE WHEN NEW.r2_uploaded = 0 THEN local_only_bytes + NEW.size ELSE local_only_bytes END, last_upload_at = NOW(), updated_at = NOW() WHERE user_id = NEW.user_id; UPDATE fm_user_storage_tracking SET used_bytes = used_bytes + NEW.size, total_files = total_files + 1, r2_uploaded_bytes = CASE WHEN NEW.r2_uploaded = 1 THEN r2_uploaded_bytes + NEW.size ELSE r2_uploaded_bytes END, local_only_bytes = CASE WHEN NEW.r2_uploaded = 0 THEN local_only_bytes + NEW.size ELSE local_only_bytes END, last_upload_at = NOW(), updated_at = NOW() WHERE user_id = NEW.user_id; END IF; END;";

                if (fm_query($sql)) {
                    echo "<p class='success'>✓ Created INSERT trigger</p>";
                } else {
                    echo "<p class='error'>✗ Failed to create INSERT trigger</p>";
                }

                // Create UPDATE trigger
                $sql = "CREATE TRIGGER trg_fm_files_after_update AFTER UPDATE ON fm_files FOR EACH ROW BEGIN IF OLD.is_folder = 0 THEN IF OLD.is_deleted = 0 AND NEW.is_deleted = 1 THEN UPDATE fm_user_quotas SET used_bytes = GREATEST(0, used_bytes - OLD.size), total_files = GREATEST(0, total_files - 1), r2_uploaded_bytes = CASE WHEN OLD.r2_uploaded = 1 THEN GREATEST(0, r2_uploaded_bytes - OLD.size) ELSE r2_uploaded_bytes END, local_only_bytes = CASE WHEN OLD.r2_uploaded = 0 THEN GREATEST(0, local_only_bytes - OLD.size) ELSE local_only_bytes END, updated_at = NOW() WHERE user_id = OLD.user_id; UPDATE fm_user_storage_tracking SET used_bytes = GREATEST(0, used_bytes - OLD.size), total_files = GREATEST(0, total_files - 1), r2_uploaded_bytes = CASE WHEN OLD.r2_uploaded = 1 THEN GREATEST(0, r2_uploaded_bytes - OLD.size) ELSE r2_uploaded_bytes END, local_only_bytes = CASE WHEN OLD.r2_uploaded = 0 THEN GREATEST(0, local_only_bytes - OLD.size) ELSE local_only_bytes END, updated_at = NOW() WHERE user_id = OLD.user_id; ELSEIF OLD.is_deleted = 1 AND NEW.is_deleted = 0 THEN UPDATE fm_user_quotas SET used_bytes = used_bytes + NEW.size, total_files = total_files + 1, r2_uploaded_bytes = CASE WHEN NEW.r2_uploaded = 1 THEN r2_uploaded_bytes + NEW.size ELSE r2_uploaded_bytes END, local_only_bytes = CASE WHEN NEW.r2_uploaded = 0 THEN local_only_bytes + NEW.size ELSE local_only_bytes END, updated_at = NOW() WHERE user_id = NEW.user_id; UPDATE fm_user_storage_tracking SET used_bytes = used_bytes + NEW.size, total_files = total_files + 1, r2_uploaded_bytes = CASE WHEN NEW.r2_uploaded = 1 THEN r2_uploaded_bytes + NEW.size ELSE r2_uploaded_bytes END, local_only_bytes = CASE WHEN NEW.r2_uploaded = 0 THEN local_only_bytes + NEW.size ELSE local_only_bytes END, updated_at = NOW() WHERE user_id = NEW.user_id; END IF; END IF; END;";

                if (fm_query($sql)) {
                    echo "<p class='success'>✓ Created UPDATE trigger</p>";
                } else {
                    echo "<p class='error'>✗ Failed to create UPDATE trigger</p>";
                }
                ?>
            </div>

            <h2>Step 5: Verification</h2>
            <div class="step">
                <?php
                $commonCount = fm_query("SELECT COUNT(*) as cnt FROM fm_common_folders WHERE is_active = 1");
                $commonCount = $commonCount[0]['cnt'] ?? 0;
                echo "<p>Common folders (active): <strong>{$commonCount}</strong></p>";

                $specialCount = fm_query("SELECT COUNT(*) as cnt FROM fm_special_folders WHERE is_active = 1");
                $specialCount = $specialCount[0]['cnt'] ?? 0;
                echo "<p>Special folders (active): <strong>{$specialCount}</strong></p>";

                $trackingCount = fm_query("SELECT COUNT(*) as cnt FROM fm_user_storage_tracking WHERE used_bytes > 0");
                $trackingCount = $trackingCount[0]['cnt'] ?? 0;
                echo "<p>Users with storage tracking: <strong>{$trackingCount}</strong></p>";

                $triggers = fm_query("SHOW TRIGGERS WHERE `Table` = 'fm_files'");
                $triggerCount = count($triggers ?? []);
                echo "<p>Active triggers: <strong>{$triggerCount}</strong></p>";

                $globalStats = fm_get_global_storage_stats();
                echo "<p>Global VPS Usage: <strong>" . fm_format_bytes($globalStats['total_used_bytes']) . " / " . fm_format_bytes($globalStats['vps_total_bytes']) . " ({$globalStats['vps_usage_percent']}%)</strong></p>";
                ?>
            </div>

            <div style="background: #10b981; color: white; padding: 20px; border-radius: 5px; margin: 20px 0;">
                <h3 style="margin: 0 0 10px 0;">✅ Fix Complete!</h3>
                <p style="margin: 0;">All fixes have been applied successfully. Please refresh the file manager page to see the changes.</p>
            </div>

            <a href="manage/pages/file_manager" class="button">Go to File Manager</a>
            <a href="?action=show" class="button" style="background: #6b7280;">Run Again</a>

            <?php
        }
        ?>
    </div>
</body>
</html>

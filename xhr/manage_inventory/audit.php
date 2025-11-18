<?php
/**
 * CONSOLIDATED AUDIT TRAIL ENDPOINTS
 * Handles all audit logging and retrieval operations
 * Consolidated from: inventory_audit.php, advanced.php, inventory_complete.php
 */

global $db, $wo, $sqlConnect;

// Get audit trail for purchase (with filters)
if ($s === 'get_audit_trail') {
    header('Content-Type: application/json; charset=utf-8');

    $purchase_id = isset($_POST['purchase_id']) ? (int)$_POST['purchase_id'] : 0;
    $client_id = isset($_POST['client_id']) ? (int)$_POST['client_id'] : 0;
    $category = isset($_POST['category']) ? trim($_POST['category']) : '';
    $action_type = isset($_POST['action_type']) ? trim($_POST['action_type']) : '';
    $date_from = isset($_POST['date_from']) ? $_POST['date_from'] : null;
    $date_to = isset($_POST['date_to']) ? $_POST['date_to'] : null;
    $user_name = isset($_POST['user_name']) ? trim($_POST['user_name']) : '';
    $limit = isset($_POST['limit']) ? (int)$_POST['limit'] : 100;
    $offset = isset($_POST['offset']) ? (int)$_POST['offset'] : 0;

    if ($purchase_id <= 0 && $client_id <= 0) {
        echo json_encode(['status' => 400, 'message' => 'Purchase ID or Client ID required']);
        exit;
    }

    try {
        if ($purchase_id > 0) {
            $db->where('purchase_id', $purchase_id);
        }

        if ($client_id > 0) {
            $db->where('client_id', $client_id);
        }

        if (!empty($category)) {
            $db->where('action_category', $category);
        }

        if (!empty($action_type)) {
            $db->where('action_type', $action_type);
        }

        if ($date_from) {
            $db->where('performed_at', $date_from . ' 00:00:00', '>=');
        }

        if ($date_to) {
            $db->where('performed_at', $date_to . ' 23:59:59', '<=');
        }

        if ($user_name) {
            $db->where('(performed_by LIKE ? OR created_by_name LIKE ?)',
                ['%' . $user_name . '%', '%' . $user_name . '%'], 'OR');
        }

        $db->orderBy('performed_at', 'DESC');
        $db->limit($limit, $offset);
        $records = $db->get('crm_audit_trail');

        // Get total count for pagination
        $db->where('1', '1', '=');
        if ($purchase_id > 0) {
            $db->where('purchase_id', $purchase_id);
        }
        if ($client_id > 0) {
            $db->where('client_id', $client_id);
        }
        if (!empty($category)) {
            $db->where('action_category', $category);
        }
        if (!empty($action_type)) {
            $db->where('action_type', $action_type);
        }
        if ($date_from) {
            $db->where('performed_at', $date_from . ' 00:00:00', '>=');
        }
        if ($date_to) {
            $db->where('performed_at', $date_to . ' 23:59:59', '<=');
        }
        $total = (int)$db->getValue('crm_audit_trail', 'COUNT(*)');

        $result = [];
        if (!empty($records)) {
            foreach ($records as $record) {
                $result[] = [
                    'id' => (int)$record->id,
                    'category' => $record->action_category ?? '',
                    'action' => $record->action_type ?? '',
                    'description' => $record->action_description ?? '',
                    'timestamp' => $record->performed_at ?? $record->created_at ?? '',
                    'user' => $record->created_by_name ?? 'System',
                    'user_id' => (int)($record->performed_by ?? $record->created_by ?? 0),
                    'old_value' => $record->before_values ? json_decode($record->before_values, true) :
                                  ($record->old_value ? json_decode($record->old_value, true) : null),
                    'new_value' => $record->after_values ? json_decode($record->after_values, true) :
                                  ($record->new_value ? json_decode($record->new_value, true) : null),
                    'related_table' => $record->related_table ?? '',
                    'related_id' => (int)($record->related_record_id ?? 0),
                    'ip_address' => $record->ip_address ?? ''
                ];
            }
        }

        echo json_encode([
            'status' => 200,
            'success' => true,
            'audit' => $result,
            'audit_trail' => $result,
            'audit_logs' => $result,
            'count' => count($result),
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset
        ]);
    } catch (Exception $e) {
        echo json_encode(['status' => 500, 'message' => $e->getMessage()]);
    }
    exit;
}

// Log audit action (manual logging)
if ($s === 'log_audit_action' || $s === 'log_audit') {
    header('Content-Type: application/json; charset=utf-8');

    $purchase_id = isset($_POST['purchase_id']) ? (int)$_POST['purchase_id'] : 0;
    $client_id = isset($_POST['client_id']) ? (int)$_POST['client_id'] : 0;
    $category = isset($_POST['category']) ? trim($_POST['category']) : '';
    $action_type = isset($_POST['action_type']) ? trim($_POST['action_type']) : '';
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';
    $old_value = isset($_POST['old_value']) ? $_POST['old_value'] : null;
    $new_value = isset($_POST['new_value']) ? $_POST['new_value'] : null;
    $related_table = isset($_POST['related_table']) ? trim($_POST['related_table']) : null;
    $related_id = isset($_POST['related_id']) ? (int)$_POST['related_id'] : null;

    if ($purchase_id <= 0 || !$category || !$action_type) {
        echo json_encode(['status' => 400, 'message' => 'Missing required fields']);
        exit;
    }

    try {
        // Ensure values are JSON strings if they're arrays
        if (is_array($old_value)) {
            $old_value = json_encode($old_value);
        }
        if (is_array($new_value)) {
            $new_value = json_encode($new_value);
        }

        $audit_data = [
            'purchase_id' => $purchase_id,
            'client_id' => $client_id,
            'action_category' => $category,
            'audit_category' => $category,
            'action_type' => $action_type,
            'action_description' => $description,
            'before_values' => $old_value,
            'old_value' => $old_value,
            'after_values' => $new_value,
            'new_value' => $new_value,
            'related_table' => $related_table,
            'related_record_id' => $related_id,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'performed_at' => date('Y-m-d H:i:s'),
            'created_at' => date('Y-m-d H:i:s'),
            'performed_by' => $wo['user']['id'] ?? null,
            'created_by' => $wo['user']['id'] ?? null,
            'created_by_name' => $wo['user']['name'] ?? 'System'
        ];

        $audit_id = $db->insert('crm_audit_trail', $audit_data);

        echo json_encode([
            'status' => 200,
            'success' => true,
            'message' => 'Audit logged',
            'audit_id' => (int)$audit_id
        ]);
    } catch (Exception $e) {
        echo json_encode(['status' => 500, 'message' => $e->getMessage()]);
    }
    exit;
}

// Get audit summary by category
if ($s === 'get_audit_summary') {
    header('Content-Type: application/json; charset=utf-8');

    $purchase_id = isset($_POST['purchase_id']) ? (int)$_POST['purchase_id'] : 0;
    $client_id = isset($_POST['client_id']) ? (int)$_POST['client_id'] : 0;

    if ($purchase_id <= 0 && $client_id <= 0) {
        echo json_encode(['status' => 400, 'message' => 'Purchase ID or Client ID required']);
        exit;
    }

    try {
        // Get counts by category
        $categories = ['payment', 'invoice', 'email', 'reschedule', 'manual_adjustment', 'transfer', 'refund', 'merge', 'cancel'];
        $summary = [];

        foreach ($categories as $cat) {
            $db->where('action_category', $cat);
            if ($purchase_id > 0) {
                $db->where('purchase_id', $purchase_id);
            }
            if ($client_id > 0) {
                $db->where('client_id', $client_id);
            }

            $count = (int)$db->getValue('crm_audit_trail', 'COUNT(*) as cnt');

            if ($count > 0) {
                $summary[$cat] = $count;
            }
        }

        echo json_encode([
            'status' => 200,
            'success' => true,
            'summary' => $summary,
            'total' => array_sum($summary)
        ]);
    } catch (Exception $e) {
        echo json_encode(['status' => 500, 'message' => $e->getMessage()]);
    }
    exit;
}

// Get recent audit activity
if ($s === 'get_recent_audit') {
    header('Content-Type: application/json; charset=utf-8');

    $purchase_id = isset($_POST['purchase_id']) ? (int)$_POST['purchase_id'] : 0;
    $limit = isset($_POST['limit']) ? (int)$_POST['limit'] : 10;

    if ($purchase_id <= 0) {
        echo json_encode(['status' => 400, 'message' => 'Purchase ID required']);
        exit;
    }

    try {
        $db->where('purchase_id', $purchase_id);
        $db->orderBy('performed_at', 'DESC');
        $db->limit($limit);
        $records = $db->get('crm_audit_trail');

        $result = [];
        if (!empty($records)) {
            foreach ($records as $record) {
                $result[] = [
                    'id' => (int)$record->id,
                    'category' => $record->action_category ?? '',
                    'action' => $record->action_type ?? '',
                    'description' => $record->action_description ?? '',
                    'timestamp' => $record->performed_at ?? $record->created_at ?? '',
                    'user' => $record->created_by_name ?? 'System'
                ];
            }
        }

        echo json_encode([
            'status' => 200,
            'success' => true,
            'recent_activity' => $result,
            'count' => count($result)
        ]);
    } catch (Exception $e) {
        echo json_encode(['status' => 500, 'message' => $e->getMessage()]);
    }
    exit;
}

/**
 * STANDARDIZED HELPER FUNCTION: Log Audit Action
 * Use this function throughout the application for consistent audit logging
 */
function logAuditAction($purchase_id, $client_id, $category, $action_type, $description, $old_value = null, $new_value = null, $related_table = null, $related_id = null) {
    global $db, $wo;

    try {
        // Ensure values are JSON strings if they're arrays
        if (is_array($old_value)) {
            $old_value = json_encode($old_value);
        }
        if (is_array($new_value)) {
            $new_value = json_encode($new_value);
        }

        $audit_data = [
            'purchase_id' => (int)$purchase_id,
            'client_id' => (int)$client_id,
            'action_category' => $category,
            'audit_category' => $category,
            'action_type' => $action_type,
            'action_description' => $description,
            'before_values' => $old_value,
            'old_value' => $old_value,
            'after_values' => $new_value,
            'new_value' => $new_value,
            'related_table' => $related_table,
            'related_record_id' => $related_id,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'performed_at' => date('Y-m-d H:i:s'),
            'created_at' => date('Y-m-d H:i:s'),
            'performed_by' => $wo['user']['id'] ?? null,
            'created_by' => $wo['user']['id'] ?? null,
            'created_by_name' => $wo['user']['name'] ?? 'System'
        ];

        return $db->insert('crm_audit_trail', $audit_data);
    } catch (Exception $e) {
        error_log('Audit logging failed: ' . $e->getMessage());
        return false;
    }
}

/**
 * ALIAS: logAudit - for backward compatibility
 */
function logAudit($purchase_id, $client_id, $category, $action_type, $description, $old_value = null, $new_value = null, $related_table = null, $related_id = null) {
    return logAuditAction($purchase_id, $client_id, $category, $action_type, $description, $old_value, $new_value, $related_table, $related_id);
}

/**
 * ALIAS: logAuditTrail - for backward compatibility
 */
function logAuditTrail($client_id, $purchase_id, $action_type, $action_category, $description, $before_value, $after_value, $affected_tables, $user_id) {
    return logAuditAction($purchase_id, $client_id, $action_category, $action_type, $description, $before_value, $after_value, $affected_tables, null);
}

?>

<?php

// Get audit trail for purchase
if ($s === 'get_audit_trail') {
    $purchase_id = isset($_POST['purchase_id']) ? (int)$_POST['purchase_id'] : 0;
    $category = isset($_POST['category']) ? trim($_POST['category']) : '';
    $date_from = isset($_POST['date_from']) ? $_POST['date_from'] : null;
    $date_to = isset($_POST['date_to']) ? $_POST['date_to'] : null;
    $limit = isset($_POST['limit']) ? (int)$_POST['limit'] : 100;

    if ($purchase_id <= 0){
        echo json_encode(['status' => 400, 'message' => 'Invalid purchase_id']);
        exit;
    }

    try {
        $query = $db->where('purchase_id', $purchase_id);

        if (!empty($category)){
            $query = $query->where('audit_category', $category);
        }

        if ($date_from){
            $query = $query->where('created_at', '>=', $date_from . ' 00:00:00');
        }

        if ($date_to){
            $query = $query->where('created_at', '<=', $date_to . ' 23:59:59');
        }

        $records = $query->orderBy('created_at', 'DESC')->limit($limit)->get('crm_audit_trail');

        $result = [];
        if (!empty($records)){
            foreach ($records as $record){
                $result[] = [
                    'id' => (int)$record->id,
                    'category' => $record->audit_category,
                    'action' => $record->action_type,
                    'description' => $record->action_description,
                    'timestamp' => $record->created_at,
                    'user' => $record->created_by_name ?? 'System',
                    'old_value' => $record->old_value ? json_decode($record->old_value, true) : null,
                    'new_value' => $record->new_value ? json_decode($record->new_value, true) : null,
                    'related_table' => $record->related_table,
                    'related_id' => (int)$record->related_record_id
                ];
            }
        }

        echo json_encode([
            'status' => 200,
            'audit' => $result,
            'count' => count($result)
        ]);
    } catch (Exception $e){
        echo json_encode(['status' => 500, 'message' => $e->getMessage()]);
    }
    exit;
}

// Log audit action
if ($s === 'log_audit_action') {
    if (!Wo_IsAdmin()){
        // Non-admin can log their own actions, but system logs automatically
        // This endpoint mainly for explicit manual logging
    }

    $purchase_id = isset($_POST['purchase_id']) ? (int)$_POST['purchase_id'] : 0;
    $client_id = isset($_POST['client_id']) ? (int)$_POST['client_id'] : 0;
    $category = isset($_POST['category']) ? trim($_POST['category']) : '';
    $action_type = isset($_POST['action_type']) ? trim($_POST['action_type']) : '';
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';

    if ($purchase_id <= 0 || !$category || !$action_type){
        echo json_encode(['status' => 400, 'message' => 'Missing required fields']);
        exit;
    }

    try {
        $audit_data = [
            'purchase_id' => $purchase_id,
            'client_id' => $client_id,
            'audit_category' => $category,
            'action_type' => $action_type,
            'action_description' => $description,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'created_at' => date('Y-m-d H:i:s'),
            'created_by' => $wo['user']['id'] ?? null,
            'created_by_name' => $wo['user']['name'] ?? 'System'
        ];

        $audit_id = $db->insert('crm_audit_trail', $audit_data);

        echo json_encode([
            'status' => 200,
            'message' => 'Audit logged',
            'audit_id' => (int)$audit_id
        ]);
    } catch (Exception $e){
        echo json_encode(['status' => 500, 'message' => $e->getMessage()]);
    }
    exit;
}

// Get audit summary by category
if ($s === 'get_audit_summary') {
    $purchase_id = isset($_POST['purchase_id']) ? (int)$_POST['purchase_id'] : 0;

    if ($purchase_id <= 0){
        echo json_encode(['status' => 400, 'message' => 'Invalid purchase_id']);
        exit;
    }

    try {
        // Get counts by category
        $categories = ['payment', 'invoice', 'email', 'reschedule', 'manual_adjustment', 'transfer', 'refund'];
        $summary = [];

        foreach ($categories as $cat){
            $count = (int)$db->where('purchase_id', $purchase_id)
                ->where('audit_category', $cat)
                ->getValue('crm_audit_trail', 'COUNT(*) as cnt');
            
            if ($count > 0){
                $summary[$cat] = $count;
            }
        }

        echo json_encode([
            'status' => 200,
            'summary' => $summary,
            'total' => array_sum($summary)
        ]);
    } catch (Exception $e){
        echo json_encode(['status' => 500, 'message' => $e->getMessage()]);
    }
    exit;
}

?>

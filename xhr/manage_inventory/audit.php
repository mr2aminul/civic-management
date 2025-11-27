<?php
/**
 * Audit Trail Module - Complete Audit Logging & Retrieval
 * Handles: audit trail retrieval, filtering, reporting
 */

header('Content-Type: application/json; charset=utf-8');

if ($s === 'get_audit_trail') {
    try {
        $client_id = isset($_GET['client_id']) ? intval($_GET['client_id']) : 0;
        $purchase_id = isset($_GET['purchase_id']) ? intval($_GET['purchase_id']) : 0;
        $category = isset($_GET['category']) ? Wo_Secure($_GET['category']) : '';
        $action_type = isset($_GET['action']) ? Wo_Secure($_GET['action']) : '';
        $date_from = isset($_GET['date_from']) ? Wo_Secure($_GET['date_from']) : '';
        $date_to = isset($_GET['date_to']) ? Wo_Secure($_GET['date_to']) : '';
        $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
        $limit = 25;
        $offset = ($page - 1) * $limit;

        if (!$client_id && !$purchase_id) {
            echo json_encode(['status' => 400, 'message' => 'Client ID or Purchase ID required']);
            exit;
        }


        // Build where conditions
        if ($purchase_id) {
            $db->where('purchase_id', $purchase_id);
        }
        if ($client_id) {
            $db->where('client_id', $client_id);
        }
        if ($category) {
            $db->where('action_category', $category);
        }
        if ($action_type) {
            $db->where('action_type', $action_type);
        }
        if ($date_from) {
            $db->where('performed_at', $date_from . ' 00:00:00', '>=');
        }
        if ($date_to) {
            $db->where('performed_at', $date_to . ' 23:59:59', '<=');
        }

        $db->orderBy('performed_at', 'DESC');
        $total = $db->getValue('crm_audit_trail', 'count(*)');
        
        // Rebuild where conditions for second query
        if ($purchase_id) {
            $db->where('purchase_id', $purchase_id);
        }
        if ($client_id) {
            $db->where('client_id', $client_id);
        }
        if ($category) {
            $db->where('action_category', $category);
        }
        if ($action_type) {
            $db->where('action_type', $action_type);
        }
        if ($date_from) {
            $db->where('performed_at', $date_from . ' 00:00:00', '>=');
        }
        if ($date_to) {
            $db->where('performed_at', $date_to . ' 23:59:59', '<=');
        }

        $db->orderBy('performed_at', 'DESC');
        
        $logs = $db->orderBy('performed_at', 'DESC')
            ->get('crm_audit_trail', [$offset, $limit]);

        $result = [];
        if (!empty($logs)) {
            foreach ($logs as $log) {
                $result[] = [
                    'id' => $log->id,
                    'action' => $log->action_type ?? '',
                    'action_type' => $log->action_type ?? '',
                    'category' => $log->action_category ?? '',
                    'action_category' => $log->action_category ?? '',
                    'description' => $log->action_description ?? '',
                    'performed_at' => $log->performed_at ?? '',
                    'created_at' => $log->performed_at ?? '',
                    'timestamp' => $log->performed_at ?? '',
                    'performed_by' => $log->performed_by ?? 0,
                    'performed_by_name' => 'User #' . ($log->performed_by ?? 0),
                    'changed_by_name' => 'User #' . ($log->performed_by ?? 0),
                    'before_values' => $log->before_values ?? '',
                    'after_values' => $log->after_values ?? '',
                    'purchase_id' => $log->purchase_id ?? 0
                ];
            }
        }

        echo json_encode([
            'status' => 200,
            'logs' => $result,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'pages' => ceil($total / $limit)
        ]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['status' => 500, 'message' => 'Error: ' . $e->getMessage()]);
        exit;
    }
}

if ($s === 'get_audit_detail') {
    try {
        $audit_id = isset($_GET['audit_id']) ? intval($_GET['audit_id']) : 0;
        
        if (!$audit_id) {
            echo json_encode(['status' => 400, 'message' => 'Audit ID required']);
            exit;
        }

        $db->where('id', $audit_id);
        $audit = $db->getOne('crm_audit_trail');

        if (!$audit) {
            echo json_encode(['status' => 404, 'message' => 'Audit record not found']);
            exit;
        }

        echo json_encode(['status' => 200, 'audit' => (array)$audit]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['status' => 500, 'message' => 'Error: ' . $e->getMessage()]);
        exit;
    }
}

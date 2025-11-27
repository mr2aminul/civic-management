<?php
    // Get all clients (for transfer purchase modal)
    if ($s === 'get_all_clients') {
        header('Content-Type: application/json; charset=utf-8');
        
        try {
            $clients = $db->orderBy('name', 'ASC')->get(T_CUSTOMERS, null, ['id', 'name', 'phone', 'address']);
            
            $result = [];
            if (!empty($clients)) {
                foreach ($clients as $client) {
                    $result[] = [
                        'id' => $client->id,
                        'name' => $client->name,
                        'phone' => $client->phone ?? '',
                        'address' => $client->address ?? ''
                    ];
                }
            }
            
            echo json_encode($result);
        } catch (Exception $e) {
            echo json_encode(['status' => 500, 'message' => 'Failed to load clients: ' . $e->getMessage()]);
        }
        exit;
    }

    if ($s === 'get_nominees') {
        header('Content-Type: application/json; charset=utf-8');
        
        $client_id = isset($_GET['client_id']) ? (int)$_GET['client_id'] : 0;
        
        if ($client_id <= 0) {
            echo json_encode(['status' => 400, 'message' => 'Invalid Client ID']);
            exit;
        }
        
        try {
            // Query the dedicated crm_nominees table
            $nominees = $db->where('customer_id', $client_id)->get('crm_nominees');
            
            $result = [];
            if ($nominees) {
                foreach ($nominees as $n) {
                    $result[] = [
                        'id' => $n->id,
                        'name' => $n->name,
                        'relation' => $n->relation,
                        'address' => $n->address ?? '',
                        'phone' => $n->phone ?? '',
                        'share_parcent' => $n->share_parcent ?? ''
                    ];
                }
            }
            
            echo json_encode(['status' => 200, 'nominees' => $result]);
        } catch (Exception $e) {
            echo json_encode(['status' => 500, 'message' => 'Error: ' . $e->getMessage()]);
        }
        exit;
    }

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

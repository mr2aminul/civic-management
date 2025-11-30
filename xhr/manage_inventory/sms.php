<?php
/**
 * SMS Queue & Management Module
 * Handles: SMS queueing, sending, and logs
 */

header('Content-Type: application/json; charset=utf-8');

// 1. GET SMS
if ($s == 'get_sms') {
    try {
        // Check both GET and POST for parameters
        $purchase_id = isset($_POST['purchase_id']) ? intval($_POST['purchase_id']) : (isset($_GET['purchase_id']) ? intval($_GET['purchase_id']) : 0);
        $client_id = isset($_POST['client_id']) ? intval($_POST['client_id']) : (isset($_GET['client_id']) ? intval($_GET['client_id']) : 0);
        $status = isset($_POST['status']) ? Wo_Secure($_POST['status']) : (isset($_GET['status']) ? Wo_Secure($_GET['status']) : '');

        // Build where conditions - all filters are optional
        if ($purchase_id) $db->where('purchase_id', $purchase_id);
        if ($client_id) $db->where('client_id', $client_id);
        if ($status) $db->where('status', $status);

        $db->orderBy('queue_date', 'DESC');
        $sms_list = $db->get('crm_sms_queue');

        echo json_encode(['status' => 200, 'sms' => $sms_list]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['status' => 500, 'message' => 'Error: ' . $e->getMessage()]);
        exit;
    }
}

// 2. QUEUE SMS
if ($s === 'queue_sms') {
    try {
        $purchase_id = isset($_POST['purchase_id']) ? intval($_POST['purchase_id']) : 0;
        $client_id = isset($_POST['client_id']) ? intval($_POST['client_id']) : 0;
        
        // Handle frontend payload mapping
        $recipient_type = isset($_POST['recipient_type']) ? Wo_Secure($_POST['recipient_type']) : 'custom';
        $custom_recipient = isset($_POST['custom_recipient']) ? Wo_Secure($_POST['custom_recipient']) : '';
        $message_body = isset($_POST['message_body']) ? $_POST['message_body'] : (isset($_POST['message']) ? $_POST['message'] : '');
        $scheduled_date = isset($_POST['scheduled_date']) ? Wo_Secure($_POST['scheduled_date']) : null;

        $recipient_phone = '';
        $recipient_name = '';
        
        // Fetch recipient phone based on type
        if ($recipient_type === 'current_client' || $recipient_type === 'select_client') {
            if ($client_id > 0) {
                $client = $db->where('user_id', $client_id)->getOne('wo_users', ['phone_number', 'first_name', 'last_name']);
                if ($client) {
                    $recipient_phone = $client->phone_number;
                    $recipient_name = trim($client->first_name . ' ' . $client->last_name);
                }
            }
        } elseif ($recipient_type === 'current_purchase' || $recipient_type === 'select_purchase') {
            if ($purchase_id > 0) {
                if ($client_id == 0) {
                    $purchase = $db->where('id', $purchase_id)->getOne('wo_booking_helper', ['client_id']);
                    if ($purchase) {
                        $client_id = $purchase->client_id;
                    }
                }
                if ($client_id > 0) {
                    $client = $db->where('user_id', $client_id)->getOne('wo_users', ['phone_number', 'first_name', 'last_name']);
                    if ($client) {
                        $recipient_phone = $client->phone_number;
                        $recipient_name = trim($client->first_name . ' ' . $client->last_name);
                    }
                }
            }
        } elseif ($recipient_type === 'custom') {
            $recipient_phone = $custom_recipient;
        }

        if (!$recipient_phone || !$message_body) {
            echo json_encode(['status' => 400, 'message' => 'Phone and Message required or could not be found']);
            exit;
        }

        $data = [
            'purchase_id' => $purchase_id,
            'client_id' => $client_id,
            'recipient_phone' => $recipient_phone,
            'recipient_name' => $recipient_name,
            'sms_type' => $recipient_type,
            'message_body' => $message_body,
            'metadata' => json_encode([
                'message' => $message_body,
                'client_name' => $recipient_name,
                'senderid' => '38756'
            ]),
            'status' => $scheduled_date ? 'scheduled' : 'queued',
            'scheduled_send_date' => $scheduled_date,
            'queue_date' => date('Y-m-d H:i:s')
        ];

        $id = $db->insert('crm_sms_queue', $data);

        if ($id) {
            echo json_encode(['status' => 200, 'message' => 'SMS queued successfully', 'queue_id' => $id]);
        } else {
            echo json_encode(['status' => 500, 'message' => 'Failed to queue SMS: ' . $db->getLastError()]);
        }
        exit;
    } catch (Exception $e) {
        echo json_encode(['status' => 500, 'message' => 'Error: ' . $e->getMessage()]);
        exit;
    }
}

// 3. SEND SMS (Mock for now, or integrate API)
if ($s === 'send_sms') {
    try {
        $sms_id = isset($_POST['sms_id']) ? intval($_POST['sms_id']) : 0;
        
        if (!$sms_id) {
            echo json_encode(['status' => 400, 'message' => 'SMS ID required']);
            exit;
        }

        $db->where('id', $sms_id);
        $sms = $db->getOne('crm_sms_queue');

        if (!$sms) {
            echo json_encode(['status' => 404, 'message' => 'SMS not found']);
            exit;
        }

        // TODO: Integrate actual SMS gateway here (e.g., Twilio, MessageBird)
        // For now, we simulate success
        $success = true; 

        if ($success) {
            $db->where('id', $sms_id);
            $db->update('crm_sms_queue', [
                'status' => 'sent',
                'sent_date' => date('Y-m-d H:i:s')
            ]);

            echo json_encode(['status' => 200, 'message' => 'SMS sent successfully']);
        } else {
            $db->where('id', $sms_id);
            $db->update('crm_sms_queue', [
                'status' => 'failed',
                'last_error' => 'Gateway error'
            ]);
            echo json_encode(['status' => 500, 'message' => 'Failed to send SMS']);
        }
        exit;
    } catch (Exception $e) {
        echo json_encode(['status' => 500, 'message' => 'Error: ' . $e->getMessage()]);
        exit;
    }
}

// 4. DELETE SMS
if ($s === 'delete_sms') {
    try {
        $sms_id = isset($_POST['sms_id']) ? intval($_POST['sms_id']) : 0;
        
        if (!$sms_id) {
            echo json_encode(['status' => 400, 'message' => 'SMS ID required']);
            exit;
        }

        $db->where('id', $sms_id);
        $result = $db->delete('crm_sms_queue');

        if ($result) {
            echo json_encode(['status' => 200, 'message' => 'SMS deleted successfully']);
        } else {
            echo json_encode(['status' => 500, 'message' => 'Failed to delete SMS']);
        }
        exit;
    } catch (Exception $e) {
        echo json_encode(['status' => 500, 'message' => 'Error: ' . $e->getMessage()]);
        exit;
    }
}

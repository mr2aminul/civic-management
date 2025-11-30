<?php
// ===============================
//  🔐 CONFIGURATION & SECURITY
// ===============================
error_reporting(E_ALL);
ini_set("display_errors", 1);
date_default_timezone_set("Asia/Dhaka");
header("Content-type: application/json");

$is_lockout = is_lockout();
if ($s == "lockout_check") {
    echo json_encode([
        "status" => $is_lockout ? 400 : 200,
        "message" => $is_lockout ? "Session Timeout!" : "Session still alive!"
    ]);
    exit;
}

if ($f == "manage_clients") {
    // Check user permissions
    if (!(Wo_IsAdmin() || Wo_IsModerator() || check_permission("manage-clients") || check_permission("clients"))) {
        echo json_encode([
            'status' => 404,
            'message' => "You don’t have permission"
        ]);
        exit;
    }
    
if ($s == 'search_clients') {
    header('Content-Type: application/json; charset=utf-8');
    global $db;

    // debug mode via ?debug=1
    $debug = (isset($_REQUEST['debug']) && $_REQUEST['debug'] == '1') ? true : false;

    $searchValue = isset($_REQUEST['q']) ? trim((string)$_REQUEST['q']) : '';
    $project_filter = isset($_REQUEST['project_id']) ? trim((string)$_REQUEST['project_id']) : '';
    $bh_status_input = isset($_REQUEST['bh_status']) ? trim((string)$_REQUEST['bh_status']) : 'all';

    // optional exclusion parameters:
    // pass exclude_client_id to explicitly exclude a client id
    // or pass purchaseId to attempt to resolve and exclude the current client attached to that purchase
    $exclude_client_id = isset($_REQUEST['exclude_client_id']) ? intval($_REQUEST['exclude_client_id']) : 0;
    $exclude_purchase_id = isset($_REQUEST['purchaseId']) ? trim((string)$_REQUEST['purchaseId']) : '';
    $include_purchase = (isset($_REQUEST['include_purchase']) && $_REQUEST['include_purchase'] == '1');
    
    // Direct lookup support for invoice pre-selection
    $direct_client_id = isset($_REQUEST['client_id']) ? intval($_REQUEST['client_id']) : 0;
    $direct_purchase_id = isset($_REQUEST['purchase_id']) ? intval($_REQUEST['purchase_id']) : 0;
    

    // Quick sanity: ensure db exists
    if (!isset($db) || !$db) {
        if ($debug) { echo json_encode(['error' => 'Missing $db']); exit(); }
        echo json_encode([]); exit();
    }

    // Build booking_helper status array
    $bh_status_arr = [];
    if ($bh_status_input === '' || strtolower($bh_status_input) === 'all') {
        $bh_status_arr = [0,1,2,3,4];
    } else {
        $parts = preg_split('/\s*,\s*/', $bh_status_input);
        foreach ($parts as $p) {
            if (is_numeric($p)) {
                $n = intval($p);
                if ($n >= 0 && $n <= 4) $bh_status_arr[] = $n;
            }
        }
        if (empty($bh_status_arr)) $bh_status_arr = [0,1,2,3,4];
    }
    $bh_status_list = implode(',', array_unique($bh_status_arr));

    // If purchaseId provided, try to resolve client id to exclude
    if ($exclude_client_id <= 0 && $exclude_purchase_id !== '') {
        // try several queries safely; stop once we find a valid client id
        try {
            // 1) booking_helper.id = ?
            $trySqls = [
                "SELECT DISTINCT bh.client_id FROM `" . T_BOOKING_HELPER . "` bh WHERE bh.id = ? LIMIT 1",
                // maybe purchaseId actually equals booking_id
                "SELECT DISTINCT bh.client_id FROM `" . T_BOOKING_HELPER . "` bh WHERE bh.booking_id = ? LIMIT 1",
                // booking -> booking_helper join (if booking.file_num or booking id maps)
                "SELECT DISTINCT bh.client_id FROM `" . T_BOOKING . "` b JOIN `" . T_BOOKING_HELPER . "` bh ON bh.booking_id = b.id WHERE b.id = ? LIMIT 1",
            ];
            foreach ($trySqls as $sql) {
                $rows = [];
                try { $rows = $db->rawQuery($sql, [$exclude_purchase_id]); } catch (Exception $e) { $rows = []; }
                if (!empty($rows)) {
                    $r = $rows[0];
                    $cid = is_object($r) ? ($r->client_id ?? null) : ($r['client_id'] ?? null);
                    if ($cid && intval($cid) > 0) { $exclude_client_id = intval($cid); break; }
                }
            }
        } catch (Exception $e) { /* ignore lookup errors */ }
    }

    $clientIds = [];
    
    // ---------- DIRECT LOOKUP: If client_id provided, use it directly ----------
    // ---------- DIRECT LOOKUP: If client_id provided, use it directly ----------
    // This bypasses the complex search logic below to ensure we get the specific client
    // requested for invoice pre-selection, even if other filters (like project) might exclude it.
    if ($direct_client_id > 0) {
        $db->where('id', $direct_client_id);
        $clients = $db->objectbuilder()->get(T_CUSTOMERS, 1, 'id, name, phone, email, nid');
        
        if (!empty($clients)) {
            $client = $clients[0];
            $item = [
                'id' => $client->id,
                'text' => $client->name . ($client->phone ? " ({$client->phone})" : '')
            ];
            
            if ($include_purchase) {
                if ($direct_purchase_id > 0) {
                    $purchases = $db->rawQuery("SELECT id, file_num FROM " . T_BOOKING_HELPER . " WHERE client_id = ? AND id = ? LIMIT 1", [$client->id, $direct_purchase_id]);
                } else {
                    $purchases = $db->rawQuery("SELECT id, file_num FROM " . T_BOOKING_HELPER . " WHERE client_id = ? ORDER BY id DESC", [$client->id]);
                }
                $item['purchases'] = $purchases;
            }
            
            echo json_encode([$item]);
            exit();
        } else {
            echo json_encode([]);
            exit();
        }
    }

    // ---------- 1) If project_filter provided, load project clients as a base set ----------
    $projectClients = [];
    if ($project_filter !== '') {
        try {
            if (is_numeric($project_filter)) {
                $pid = intval($project_filter);
                $sqlp = "SELECT DISTINCT bh.client_id
                         FROM `" . T_BOOKING_HELPER . "` bh
                         JOIN `" . T_BOOKING . "` b ON b.id = bh.booking_id
                         WHERE (b.project = ? OR b.project_id = ?)
                         AND bh.client_id IS NOT NULL AND b.status = 2 AND bh.status IN ($bh_status_list)";
                $rows = $db->rawQuery($sqlp, [$project_filter, $pid]);
            } else {
                $pf = mb_strtolower(trim($project_filter));
                $pf_no_space = str_replace(' ', '', $pf);
                $sqlp = "SELECT DISTINCT bh.client_id
                         FROM `" . T_BOOKING_HELPER . "` bh
                         JOIN `" . T_BOOKING . "` b ON b.id = bh.booking_id
                         WHERE bh.client_id IS NOT NULL AND b.status = 2 AND bh.status IN ($bh_status_list)
                         AND (LOWER(TRIM(b.project)) = ? OR REPLACE(LOWER(b.project), ' ', '') = ?)";
                $rows = $db->rawQuery($sqlp, [$pf, $pf_no_space]);
            }
            foreach ($rows as $r) {
                $cid = is_object($r) ? ($r->client_id ?? null) : ($r['client_id'] ?? null);
                if ($cid) $projectClients[] = (int)$cid;
            }
        } catch (Exception $e) { /* ignore */ }
        $projectClients = array_values(array_unique($projectClients));
    }

    // ---------- 2) If searchValue present: search file_num (booking_helper) AND customer fields ----------
    if ($searchValue !== '') {
        $like = '%' . $searchValue . '%';

        // a) booking_helper.file_num (not restricted to numeric-only queries)
        try {
            $sql = "SELECT DISTINCT bh.client_id
                    FROM `" . T_BOOKING_HELPER . "` bh
                    WHERE bh.file_num LIKE ? AND bh.client_id IS NOT NULL AND bh.status IN ($bh_status_list)";
            $rows = $db->rawQuery($sql, [$like]);
            foreach ($rows as $r) {
                $cid = is_object($r) ? ($r->client_id ?? null) : ($r['client_id'] ?? null);
                if ($cid) $clientIds[] = (int)$cid;
            }
        } catch (Exception $e) { /* ignore */ }

        // b) booking.file_num via booking->booking_helper
        try {
            $sql2 = "SELECT DISTINCT bh.client_id
                     FROM `" . T_BOOKING . "` b
                     JOIN `" . T_BOOKING_HELPER . "` bh ON bh.booking_id = b.id
                     WHERE b.file_num LIKE ? AND bh.client_id IS NOT NULL AND b.status = 2 AND bh.status IN ($bh_status_list)";
            $rows2 = $db->rawQuery($sql2, [$like]);
            foreach ($rows2 as $r) {
                $cid = is_object($r) ? ($r->client_id ?? null) : ($r['client_id'] ?? null);
                if ($cid) $clientIds[] = (int)$cid;
            }
        } catch (Exception $e) { /* ignore */ }

        // c) customers table: name / phone / email / nid
        try {
            $rows3 = $db->rawQuery(
                "SELECT id FROM `" . T_CUSTOMERS . "` 
                 WHERE name LIKE ? OR phone LIKE ? OR email LIKE ? OR nid LIKE ?",
                 [$like, $like, $like, $like]
            );
            foreach ($rows3 as $r) {
                $cid = is_object($r) ? ($r->id ?? null) : ($r['id'] ?? null);
                if ($cid) $clientIds[] = (int)$cid;
            }
        } catch (Exception $e) { /* ignore */ }
    }

    $clientIds = array_values(array_unique($clientIds));

    // ---------- 3) Combine with projectClients intelligently ----------
    if (!empty($projectClients)) {
        if (!empty($clientIds)) {
            // intersect if both present
            $clientIds = array_values(array_intersect($clientIds, $projectClients));
        } else {
            // no text search matches -> start from project clients
            $clientIds = $projectClients;
        }
    }

    // ---------- 3.5) Remove excluded client id if present ----------
    if (!empty($exclude_client_id)) {
        $exclude_client_id = intval($exclude_client_id);
        $clientIds = array_values(array_filter($clientIds, function($v) use ($exclude_client_id) { return intval($v) !== $exclude_client_id; }));
    }

    // ---------- 4) If still empty, return [] early (but in debug mode show details) ----------
    if (empty($clientIds)) {
        if ($debug) {
            echo json_encode([
                'debug' => true,
                'searchValue' => $searchValue,
                'project_filter' => $project_filter,
                'bh_status_list' => $bh_status_list,
                'clientIds_after_search' => $clientIds,
                'projectClients' => $projectClients,
                'exclude_client_id' => $exclude_client_id,
                'exclude_purchase_id' => $exclude_purchase_id
            ]);
            exit();
        }
        echo json_encode([]);
        exit();
    }

    // } (else block removed for direct lookup)
    
    // end direct lookup check

    // ---------- 5) Fetch clients ----------
    $db->where('id', $clientIds, 'IN');

    // also ensure final fetch excludes the client explicitly (defensive)
    if (!empty($exclude_client_id)) {
        $db->where('id', $exclude_client_id, '<>');
    }

    $db->orderBy('name', 'ASC');
    try {
        $clients = $db->objectbuilder()->get(T_CUSTOMERS, null, 'id, name, phone, email, nid');
    } catch (Exception $e) {
        if ($debug) { echo json_encode(['error' => 'DB fetch error', 'message' => $e->getMessage()]); exit(); }
        echo json_encode([]); exit();
    }

    // ---------- Output for Select2 ----------
    $output = [];
    foreach ($clients as $c) {
        // sanity: skip excluded again just in case
        if (!empty($exclude_client_id) && intval($c->id) === intval($exclude_client_id)) continue;
        
        $item = [
            'id' => $c->id,
            'text' => $c->name . ($c->phone ? " ({$c->phone})" : '')
        ];

        if ($include_purchase) {
            // Fetch purchases (booking helpers) for this client
            // If direct_purchase_id is provided, only fetch that specific purchase
            if ($direct_purchase_id > 0) {
                $purchases = $db->rawQuery("SELECT id, file_num FROM " . T_BOOKING_HELPER . " WHERE client_id = ? AND id = ? LIMIT 1", [$c->id, $direct_purchase_id]);
            } else {
                $purchases = $db->rawQuery("SELECT id, file_num FROM " . T_BOOKING_HELPER . " WHERE client_id = ? ORDER BY id DESC", [$c->id]);
            }
            $item['purchases'] = $purchases;
        }

        $output[] = $item;
    }

    if ($debug) {
        echo json_encode([
            'debug' => true,
            'searchValue' => $searchValue,
            'project_filter' => $project_filter,
            'bh_status_list' => $bh_status_list,
            'clientIds' => $clientIds,
            'exclude_client_id' => $exclude_client_id,
            'result_count' => count($output),
            'results' => $output
        ]);
        exit();
    }

    echo json_encode($output);
    exit();
}



    if ($s == 'view_client_modal') {
    	$client_id = isset($_POST['client_id']) ? $_POST['client_id'] : '';
    	
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

    	if (empty($client_id)) {
    		$data = array(
    			'status'  => 400,
    			'message' => 'Something went wrong!'
    		);
    	} else {
    		$wo['client'] = GetCustomerById($client_id);
    		$wo['booking_helper'] = GetBookingHelpers($client_id, 'client_id');
    // 		$wo['invoice'] = GetInvoicesByCustomerId($client_id);
    		$wo['additional'] = GetAddiData_cId($client_id);
    		
    		$data = array(
    			'status'  => 200,
    			'result' => Wo_LoadManagePage('clients/modals/view_client')
    		);
    	}

    echo json_encode($data);
    exit();

    }
	
	if ($s == "editClient_modal") {
        $userID = isset($_POST['client_id']) ? Wo_Secure($_POST['client_id']) : '';
    
        if (empty($userID)) {
            $errors[] = 'Something Went Wrong!';
            $data = array(
                'status' => 400,
                'result' => $errors
            );
        } else {
            $wo['client'] = GetCustomerById($userID);
            $wo['purchase'] = GetPurchaseByClientId($userID);
            $wo['additional'] = GetAddiData_cId($userID);
    
            if (!$wo['client']) {
                $errors[] = 'Client not found!';
            }
    
            $data = array(
                'status' => (empty($errors)) ? 200 : 400,
                'result' => Wo_LoadManagePage('clients/modals/edit-client')
            );
    
            // ✅ Log client edit view request
            $clientName = !empty($wo['client']['name']) ? $wo['client']['name'] : "Unknown";
            $logDetails = "Opened client editor for {$clientName} (ID: {$userID})";
            logActivity('clients', 'edit', $logDetails);
        }
    }

	
	if ($s == 'update_client') {
		$client_id = isset($_GET['client_id']) ? $_GET['client_id'] : '';
		$name = isset($_GET['name']) ? $_GET['name'] : '';
		$address = isset($_GET['address']) ? $_GET['address'] : '';
		$phone = isset($_GET['phone']) ? $_GET['phone'] : '';
		$getAdditional = isset($_GET['additional']) ? $_GET['additional'] : array();
		
		$is_exist = $db->where('id', $client_id)->getOne(T_CUSTOMERS);
				
		if (empty($client_id)) {
			$data = array(
				'status'  => 400,
				'message' => "Client not found!"
			);
		} else if (empty($is_exist)) {
			$data = array(
				'status'  => 400,
				'message' => "Client not found!"
			);
		} else if (empty($name)) {
			$data = array(
				'status'  => 400,
				'message' => "Name can't be empty!"
			);
		} else if (empty($getAdditional)) {
			$data = array(
				'status'  => 400,
				'message' => "Something went worng!"
			);
		} else if (empty($address)) {
			$data = array(
				'status'  => 400,
				'message' => "Address can't be empty!"
			);
		} else if (empty($phone)) {
			$data = array(
				'status'  => 400,
				'message' => "Phone can't be empty!"
			);
		} else {
			$serializedData = array(
				'spouse_name' => isset($getAdditional['spouse_name']) ? $getAdditional['spouse_name'] : '',
				'fathers_name' => isset($getAdditional['fathers_name']) ? $getAdditional['fathers_name'] : '',
				'mothers_name' => isset($getAdditional['mothers_name']) ? $getAdditional['mothers_name'] : '',
				'permanent_addr' => isset($getAdditional['permanent_addr']) ? $getAdditional['permanent_addr'] : '',
				'profession' => isset($getAdditional['profession']) ? $getAdditional['profession'] : '',
				'email' => isset($getAdditional['email']) ? $getAdditional['email'] : '',
				'phone' => isset($phone) ? $phone : '',
				'nationality' => isset($getAdditional['nationality']) ? $getAdditional['nationality'] : '',
				'birthday' => isset($getAdditional['birthday']) ? $getAdditional['birthday'] : '',
				'religion' => isset($getAdditional['religion']) ? $getAdditional['religion'] : '',
				'nid' => isset($getAdditional['nid']) ? $getAdditional['nid'] : '',
				'passport' => isset($getAdditional['passport']) ? $getAdditional['passport'] : '',
				'nomine_name' => isset($getAdditional['nomine_name']) ? $getAdditional['nomine_name'] : '',
				'nomine_address' => isset($getAdditional['nomine_address']) ? $getAdditional['nomine_address'] : '',
				'nomine_relation' => isset($getAdditional['nomine_relation']) ? $getAdditional['nomine_relation'] : '',
				'reference' => isset($getAdditional['reference']) ? $getAdditional['reference'] : '',
			);
			
			$additional = serialize($serializedData);
			$ref_id = isset($getAdditional['reference']) ? $getAdditional['reference'] : '';
			$data_array = array(
				'name' => $name,
				'phone' => $phone,
				'address' => $address,
				'additional' => $additional,
				'reference' => $ref_id
			);
			
			$update = $db->where('id', $client_id)->update(T_CUSTOMERS, $data_array);

			if ($update) {
				$message = 'User information updated!';
			} else {
				$message = 'Something Went Wrong!';
			}

			$data = array(
				'status'  => 200,
				'message' => $message
			);
		}
	}
	
    if ($s == 'create_client') {
        // ---------------- helpers ----------------
        function val($v) { return is_string($v) ? trim($v) : $v; }
    
        function normalize_phone($phone, $default_country = 'BD') {
            if (empty($phone)) return false;
            $p = trim($phone);
            $p = preg_replace('/[^\d\+]/u', '', $p);
            if (strpos($p, '00') === 0) $p = '+' . substr($p, 2);
            if (strpos($p, '+') === 0) {
                return '+' . preg_replace('/\D/', '', substr($p, 1));
            }
            if (!preg_match('/^\d+$/', $p)) return false;
    
            if (strtoupper($default_country) === 'BD') {
                // 01XXXXXXXXX (11 digits) -> +8801XXXXXXXXX
                if (preg_match('/^01[0-9]{9}$/', $p)) return '+880' . substr($p, 1);
                // 1XXXXXXXXX (10 digits, missing leading 0) -> +8801XXXXXXXXX
                if (preg_match('/^1[0-9]{9}$/', $p)) return '+880' . substr('0' . $p, 1);
                // 8801XXXXXXXXX -> +8801XXXXXXXXX
                if (preg_match('/^8801[0-9]{9}$/', $p)) return '+' . $p;
            }
    
            $len = strlen($p);
            if ($len >= 7 && $len <= 15) return '+' . $p;
            return false;
        }
    
        function is_valid_e164($phone) {
            return (bool)preg_match('/^\+[1-9]\d{1,14}$/', $phone);
        }
    
        // fuzzy name similarity: similar_text % + levenshtein tolerance
        function is_similar_name($a, $b, $percent_threshold = 0.78) {
            $a = mb_strtolower(trim(preg_replace('/\s+/', ' ', $a)));
            $b = mb_strtolower(trim(preg_replace('/\s+/', ' ', $b)));
            if ($a === $b) return true;
            similar_text($a, $b, $pct);
            if (($pct / 100) >= $percent_threshold) return true;
            $dist = levenshtein($a, $b);
            $max_allowed = max(1, floor(max(mb_strlen($a), mb_strlen($b)) * 0.20));
            return ($dist <= $max_allowed);
        }
    
    
        // ---------------- collect & sanitize ----------------
        $name = isset($_POST['name']) ? val($_POST['name']) : '';
        $address = isset($_POST['address']) ? val($_POST['address']) : '';
        $phone_input = isset($_POST['phone']) ? val($_POST['phone']) : '';
        $getAdditional = isset($_POST['additional']) && is_array($_POST['additional']) ? $_POST['additional'] : [];
    
        // raw nominees from form (nominees[])
        $nominees_input = [];
        if (!empty($_POST['nominees']) && is_array($_POST['nominees'])) {
            $nominees_input = $_POST['nominees'];
        }
    
        // ---------------- validations ----------------
        $errors = [];
        if (empty($name)) $errors[] = "Name can't be empty!";
        if (empty($address)) $errors[] = "Address can't be empty!";
        if (empty($phone_input)) $errors[] = "Phone can't be empty!";
    
        if (!empty($getAdditional['email']) && !filter_var($getAdditional['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Email is invalid.";
        }
    
        // birthday normalization: accept YYYY-MM-DD or DD/MM/YYYY
        if (!empty($getAdditional['birthday'])) {
            $bd = $getAdditional['birthday'];
            $dt = DateTime::createFromFormat('Y-m-d', $bd) ?: DateTime::createFromFormat('d/m/Y', $bd);
            if (!$dt) $errors[] = "Birthday format invalid. Use YYYY-MM-DD or DD/MM/YYYY.";
            else $getAdditional['birthday'] = $dt->format('Y-m-d');
        }
    
        if (!empty($getAdditional['nid']) && strlen(trim($getAdditional['nid'])) < 6) $errors[] = "NID seems too short.";
        if (!empty($getAdditional['passport']) && strlen(trim($getAdditional['passport'])) < 5) $errors[] = "Passport seems too short.";
    
        // normalize phone (BD-first)
        $phone_normalized = normalize_phone($phone_input, 'BD');
        if (!$phone_normalized || !is_valid_e164($phone_normalized)) {
            $errors[] = "Phone number cannot be normalized/validated. Please check format.";
        }
    
        // ---------------- nominee validation (required fields) ----------------
        $validated_nominees = [];
        if (!empty($nominees_input) && is_array($nominees_input)) {
            foreach ($nominees_input as $i => $n) {
                // ensure it's array-like
                if (!is_array($n)) {
                    $errors[] = "Nominee #" . ($i + 1) . " is malformed.";
                    continue;
                }
    
                $n_name = isset($n['name']) ? trim($n['name']) : '';
                $n_relation = isset($n['relation']) ? trim($n['relation']) : '';
                // share_parcent must be present and not empty string (but '0' allowed)
                $share_exists = array_key_exists('share_parcent', $n);
                $n_share_raw = $share_exists ? trim((string)$n['share_parcent']) : null;
    
                if ($n_name === '') $errors[] = "Nominee #" . ($i + 1) . ": name is required.";
                if ($n_relation === '') $errors[] = "Nominee #" . ($i + 1) . ": relation is required.";
                if (!$share_exists || $n_share_raw === '') {
                    $errors[] = "Nominee #" . ($i + 1) . ": share (%) is required (use 0 if zero).";
                } else {
                    // numeric check (allow integer or decimal)
                    if (!is_numeric($n_share_raw)) {
                        $errors[] = "Nominee #" . ($i + 1) . ": share (%) must be numeric.";
                    } else {
                        $n_share = (float)$n_share_raw;
                        if ($n_share < 0 || $n_share > 100) {
                            $errors[] = "Nominee #" . ($i + 1) . ": share (%) must be between 0 and 100.";
                        }
                    }
                }
    
                // nominee address fallback to customer's address if missing/empty
                $n_address = (isset($n['address']) && trim($n['address']) !== '') ? trim($n['address']) : $address;
    
                $n_phone_nominee = isset($n['phone']) ? trim($n['phone']) : null;
                $n_birthday_nominee = isset($n['birthday']) ? $n['birthday'] : null;
    
                // Only push a normalized nominee entry (we'll insert after main validation)
                $validated_nominees[] = [
                    'name' => $n_name,
                    'relation' => $n_relation,
                    'share_parcent' => (isset($n_share) ? (string)$n_share : $n_share_raw),
                    'address' => $n_address,
                    'phone' => $n_phone_nominee,
                    'birthday' => $n_birthday_nominee
                ];
            }
        }
    
        // If any validation errors so far, return them (HTTP 400)
        if (!empty($errors)) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 400, 'message' => implode(' ', $errors)]);
            exit;
        }
    
        // ---------------- existence checks ----------------
        // Check normalized phone, nid, passport, exact name+address (no project constraint)
        $params = [];
        $sql = "SELECT id, name, address, phone FROM " . T_CUSTOMERS . " WHERE (phone = ?)";
        $params[] = $phone_normalized;
    
        if (!empty($getAdditional['nid'])) {
            $sql .= " OR nid = ?";
            $params[] = $getAdditional['nid'];
        }
        if (!empty($getAdditional['passport'])) {
            $sql .= " OR passport = ?";
            $params[] = $getAdditional['passport'];
        }
    
        $sql .= " OR (name = ? AND address = ?)";
        $params[] = $name;
        $params[] = $address;
        $sql .= " LIMIT 1";
    
        $existing = $db->rawQueryOne($sql, $params);
        if ($existing) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 400, 'message' => 'Client already exists!', 'candidate' => $existing]);
            exit;
        }
    
        // fuzzy name search (global)
        $first3 = mb_substr($name, 0, 3);
        $sqlF = "SELECT id, name, address, phone FROM " . T_CUSTOMERS . " 
                 WHERE (SOUNDEX(name) = SOUNDEX(?) OR name LIKE ?) LIMIT 50";
        $candidates = $db->rawQuery($sqlF, [$name, $first3 . '%']);
    
        foreach ($candidates as $cand) {
            if (is_similar_name($name, $cand->name)) {
                similar_text(mb_strtolower($address), mb_strtolower($cand->address), $addrPct);
                if ($addrPct >= 55) {
                    header('Content-Type: application/json');
                    echo json_encode(['status' => 400, 'message' => 'Client likely exists (fuzzy match).', 'candidate' => $cand]);
                    exit;
                }
                if (!empty($cand->phone) && $cand->phone === $phone_normalized) {
                    header('Content-Type: application/json');
                    echo json_encode(['status' => 400, 'message' => 'Client already exists (phone+fuzzy name).', 'candidate' => $cand]);
                    exit;
                }
            }
        }
    
        // ---------------- prepare insert ----------------
        // mapCols: only actual columns in crm_customers (note: no nomine_* here)
        $mapCols = [
            'spouse_name','fathers_name','mothers_name','permanent_addr','profession',
            'email','nationality','birthday','religion','nid','passport','reference'
        ];
    
        $insertData = [
            'name' => $name,
            'phone' => $phone_normalized,   // store normalized phone in phone column
            'address' => $address,
            'additional' => isset($getAdditional) ? serialize($getAdditional) : ''
        ];
    
        // copy mapped additional fields into columns if present
        foreach ($mapCols as $col) {
            if (isset($getAdditional[$col]) && $getAdditional[$col] !== '') {
                $insertData[$col] = val($getAdditional[$col]);
            }
        }
    
        // collect unknown extras into additional_json (and include raw phone)
        $unknownExtras = [];
        foreach ($getAdditional as $k => $v) {
            if (!in_array($k, $mapCols) && $k !== 'reference') {
                $unknownExtras[$k] = $v;
            }
        }
        $unknownExtras['phone_raw'] = $phone_input; // audit original
    
        if (!empty($unknownExtras)) {
            $insertData['additional_json'] = json_encode($unknownExtras, JSON_UNESCAPED_UNICODE);
        }
    
        // ---------------- insert customer (transactional) ----------------
        $db->startTransaction();
        $insertId = $db->insert(T_CUSTOMERS, $insertData);
    
        if (!$insertId) {
            // possible duplicate-key or other DB error
            $db->rollback();
            $err = $db->getLastError();
            $msg = 'Customer insert failed.';
            if ($err) $msg .= ' ' . $err;
            // try to detect duplicate key on phone (common)
            if (stripos($err, 'Duplicate') !== false || stripos($err, 'Duplicate entry') !== false) {
                $msg = 'Customer insert failed: duplicate phone or unique constraint.';
            }
            header('Content-Type: application/json');
            echo json_encode(['status' => 500, 'message' => $msg]);
            exit;
        }
    
        // ---------------- create nominees (use validated nominees; address fallback already applied) ----------------
        $created_nominee_ids = [];
        if (!empty($validated_nominees)) {
            // create_or_get_nominees expects ($customer_id, $nominees)
            $created_nominee_ids = create_or_get_nominees($insertId, $validated_nominees);
        }
    
        // commit transaction
        $db->commit();
    
        // ---------------- success response ----------------
        $response = [
            'status' => 200,
            'message' => 'User Created!',
            'id' => $insertId
        ];
        if (!empty($created_nominee_ids)) $response['nominee_ids'] = $created_nominee_ids;
    
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }

    if ($s == 'fetch_clients') {
        header('Content-Type: application/json; charset=utf-8');
        global $db;
    
        // show errors temporarily while debugging (remove in production)
        // ini_set('display_errors', 1);
        // ini_set('display_startup_errors', 1);
        // error_reporting(E_ALL);
    
        $start = isset($_POST['start']) ? (int)$_POST['start'] : 0;
        $length = isset($_POST['length']) ? (int)$_POST['length'] : 10;
        $page_num = ($length > 0) ? intval($start / $length) + 1 : 1;
    
        $searchValue = isset($_POST['search']['value']) ? trim($_POST['search']['value']) : '';
        $project_filter = isset($_POST['project_id']) ? trim($_POST['project_id']) : '';
    
        // If frontend still sends 'not-assigned', treat it as no special filter (we dropped that feature)
        if ($project_filter === 'not-assigned') {
            $project_filter = '';
        }
    
        // Toggle: set to true if you want to remove duplicate project names (case-sensitive).
        $dedupe_projects = false;
    
        // Toggle: set to true if you want to remove duplicate plots.
        $dedupe_plots = false;
    
        // ----- booking_helper status filter (POST: bh_status) -----
        // Accepts: 'all' or CSV of numeric statuses (0..4). Defaults to all.
        $bh_status_input = isset($_POST['bh_status']) ? trim((string)$_POST['bh_status']) : 'all';
    
        $bh_status_arr = [];
        if ($bh_status_input === '' || strtolower($bh_status_input) === 'all') {
            $bh_status_arr = [0,1,2,3,4];
        } else {
            $parts = preg_split('/\s*,\s*/', $bh_status_input);
            foreach ($parts as $p) {
                if (is_numeric($p)) {
                    $n = intval($p);
                    if ($n >= 0 && $n <= 4) $bh_status_arr[] = $n;
                }
            }
            if (empty($bh_status_arr)) {
                $bh_status_arr = [0,1,2,3,4];
            }
        }
        $bh_status_arr = array_values(array_unique(array_map('intval', $bh_status_arr)));
        $bh_status_list = implode(',', $bh_status_arr);
    
        try {
            $recordsTotal = (int)$db->getValue(T_CUSTOMERS, 'count(id)');
        } catch (Exception $e) {
            echo json_encode([
                "draw" => intval($_POST['draw'] ?? 0),
                "recordsTotal" => 0,
                "recordsFiltered" => 0,
                "data" => [],
                "error" => "DB error: " . $e->getMessage()
            ]);
            exit();
        }
    
        // ---------- 1) Build client ID list based on filters ----------
        $clientIdFilters = null;
    
        // ---------- Numeric search: treat as file_num search ----------
        if ($searchValue !== '' && is_numeric($searchValue)) {
            $like = '%' . $searchValue . '%';
            $bookingClients = [];
    
            try {
                $sql = "SELECT DISTINCT bh.client_id
                        FROM `" . T_BOOKING_HELPER . "` bh
                        WHERE bh.file_num LIKE ? AND bh.client_id IS NOT NULL AND bh.status IN ($bh_status_list)";
                $rows = $db->rawQuery($sql, [$like]);
                foreach ($rows as $r) {
                    $cid = is_object($r) ? ($r->client_id ?? null) : ($r['client_id'] ?? null);
                    if ($cid !== null) $bookingClients[] = (int)$cid;
                }
    
                $sql2 = "SELECT DISTINCT bh.client_id
                         FROM `" . T_BOOKING . "` b
                         JOIN `" . T_BOOKING_HELPER . "` bh ON bh.booking_id = b.id
                         WHERE b.file_num LIKE ? AND bh.client_id IS NOT NULL AND b.status = 2 AND bh.status IN ($bh_status_list)";
                $rows2 = $db->rawQuery($sql2, [$like]);
                foreach ($rows2 as $r) {
                    $cid = is_object($r) ? ($r->client_id ?? null) : ($r['client_id'] ?? null);
                    if ($cid !== null) $bookingClients[] = (int)$cid;
                }
            } catch (Exception $e) { /* ignore */ }
    
            $bookingClients = array_values(array_unique(array_filter($bookingClients)));
            if (!empty($bookingClients)) $clientIdFilters = $bookingClients;
            else {
                echo json_encode([
                    "draw" => intval($_POST['draw'] ?? 0),
                    "recordsTotal" => $recordsTotal,
                    "recordsFiltered" => 0,
                    "data" => []
                ]);
                exit();
            }
        }
    
        // ---------- Project filter ----------
        if ($project_filter !== '') {
            $projectClients = [];
    
            if (is_numeric($project_filter)) {
                $pid = intval($project_filter);
                try {
                    $sqlp = "SELECT DISTINCT bh.client_id
                             FROM `" . T_BOOKING_HELPER . "` bh
                             JOIN `" . T_BOOKING . "` b ON b.id = bh.booking_id
                             WHERE (b.project = ? OR b.project_id = ?) AND bh.client_id IS NOT NULL AND b.status = 2 AND bh.status IN ($bh_status_list)";
                    $rows = $db->rawQuery($sqlp, [$project_filter, $pid]);
                    foreach ($rows as $r) {
                        $cid = is_object($r) ? ($r->client_id ?? null) : ($r['client_id'] ?? null);
                        if ($cid !== null) $projectClients[] = (int)$cid;
                    }
                } catch (Exception $e) { /* ignore */ }
            } else {
                $pf = mb_strtolower(trim($project_filter));
                $pf_no_space = str_replace(' ', '', $pf);
                try {
                    $sqlp = "SELECT DISTINCT bh.client_id
                             FROM `" . T_BOOKING_HELPER . "` bh
                             JOIN `" . T_BOOKING . "` b ON b.id = bh.booking_id
                             WHERE bh.client_id IS NOT NULL AND b.status = 2 AND bh.status IN ($bh_status_list)
                             AND (
                                LOWER(TRIM(b.project)) = ?
                                OR REPLACE(LOWER(b.project), ' ', '') = ?
                             )";
                    $rows = $db->rawQuery($sqlp, [$pf, $pf_no_space]);
                    foreach ($rows as $r) {
                        $cid = is_object($r) ? ($r->client_id ?? null) : ($r['client_id'] ?? null);
                        if ($cid !== null) $projectClients[] = (int)$cid;
                    }
                } catch (Exception $e) { /* ignore */ }
            }
    
            $projectClients = array_values(array_unique(array_filter($projectClients)));
            if (empty($projectClients)) {
                echo json_encode([
                    "draw" => intval($_POST['draw'] ?? 0),
                    "recordsTotal" => $recordsTotal,
                    "recordsFiltered" => 0,
                    "data" => []
                ]);
                exit();
            }
    
            if (is_array($clientIdFilters)) {
                $clientIdFilters = array_values(array_intersect($clientIdFilters, $projectClients));
                if (empty($clientIdFilters)) {
                    echo json_encode([
                        "draw" => intval($_POST['draw'] ?? 0),
                        "recordsTotal" => $recordsTotal,
                        "recordsFiltered" => 0,
                        "data" => []
                    ]);
                    exit();
                }
            } else {
                $clientIdFilters = $projectClients;
            }
        }
    
        // ---------- If no filters provided, include all customers (newly created clients included) ----------
        if ($clientIdFilters === null) {
            $allClients = [];
            try {
                $rows = $db->rawQuery("SELECT id FROM `" . T_CUSTOMERS . "`");
                foreach ($rows as $r) {
                    $cid = is_object($r) ? ($r->id ?? null) : ($r['id'] ?? null);
                    if ($cid !== null) $allClients[] = (int)$cid;
                }
            } catch (Exception $e) { /* ignore */ }
    
            $allClients = array_values(array_unique(array_filter($allClients)));
            if (empty($allClients)) {
                echo json_encode([
                    "draw" => intval($_POST['draw'] ?? 0),
                    "recordsTotal" => $recordsTotal,
                    "recordsFiltered" => 0,
                    "data" => []
                ]);
                exit();
            }
            $clientIdFilters = $allClients;
        }
    
        // ---------- 2) If search is non-numeric, match both name and file_num (either) ----------
        $applySearchFilter = ($searchValue !== '');
        if ($applySearchFilter && !is_numeric($searchValue)) {
            $like = '%' . $searchValue . '%';
            $matchedIds = [];
    
            // names
            try {
                $rowsN = $db->rawQuery("SELECT id FROM `" . T_CUSTOMERS . "` WHERE name LIKE ?", [$like]);
                foreach ($rowsN as $r) {
                    $cid = is_object($r) ? ($r->id ?? null) : ($r['id'] ?? null);
                    if ($cid !== null) $matchedIds[] = (int)$cid;
                }
            } catch (Exception $e) { /* ignore */ }
    
            // bh.file_num
            try {
                $sqlF = "SELECT DISTINCT bh.client_id
                         FROM `" . T_BOOKING_HELPER . "` bh
                         WHERE bh.file_num LIKE ? AND bh.client_id IS NOT NULL AND bh.status IN ($bh_status_list)";
                $rowsF = $db->rawQuery($sqlF, [$like]);
                foreach ($rowsF as $r) {
                    $cid = is_object($r) ? ($r->client_id ?? null) : ($r['client_id'] ?? null);
                    if ($cid !== null) $matchedIds[] = (int)$cid;
                }
    
                // also check booking.file_num via booking -> booking_helper
                $sqlFb = "SELECT DISTINCT bh.client_id
                          FROM `" . T_BOOKING . "` b
                          JOIN `" . T_BOOKING_HELPER . "` bh ON bh.booking_id = b.id
                          WHERE b.file_num LIKE ? AND bh.client_id IS NOT NULL AND b.status = 2 AND bh.status IN ($bh_status_list)";
                $rowsFb = $db->rawQuery($sqlFb, [$like]);
                foreach ($rowsFb as $r) {
                    $cid = is_object($r) ? ($r->client_id ?? null) : ($r['client_id'] ?? null);
                    if ($cid !== null) $matchedIds[] = (int)$cid;
                }
            } catch (Exception $e) { /* ignore */ }
    
            $matchedIds = array_values(array_unique(array_filter($matchedIds)));
    
            if (empty($matchedIds)) {
                echo json_encode([
                    "draw" => intval($_POST['draw'] ?? 0),
                    "recordsTotal" => $recordsTotal,
                    "recordsFiltered" => 0,
                    "data" => []
                ]);
                exit();
            }
    
            // intersect with existing clientIdFilters (if set), else use matchedIds
            if (is_array($clientIdFilters)) {
                $clientIdFilters = array_values(array_intersect($clientIdFilters, $matchedIds));
                if (empty($clientIdFilters)) {
                    echo json_encode([
                        "draw" => intval($_POST['draw'] ?? 0),
                        "recordsTotal" => $recordsTotal,
                        "recordsFiltered" => 0,
                        "data" => []
                    ]);
                    exit();
                }
            } else {
                $clientIdFilters = $matchedIds;
            }
        }
    
        // ---------- compute recordsFiltered ----------
        $db->where('id', $clientIdFilters, 'IN');
        try {
            $recordsFiltered = (int)$db->getValue(T_CUSTOMERS, 'count(id)');
        } catch (Exception $e) {
            $recordsFiltered = 0;
        }
    
        if ($recordsFiltered === 0) {
            echo json_encode([
                "draw" => intval($_POST['draw'] ?? 0),
                "recordsTotal" => $recordsTotal,
                "recordsFiltered" => 0,
                "data" => []
            ]);
            exit();
        }
    
        // ---------- 3) Ordering & Pagination ----------
        $db->where('id', $clientIdFilters, 'IN');
    
        $orderColumn = isset($_POST['order'][0]['column']) ? $_POST['order'][0]['column'] : null;
        $orderDirection = (isset($_POST['order'][0]['dir']) && strtolower($_POST['order'][0]['dir']) === 'asc') ? 'ASC' : 'DESC';
    
        if ($orderColumn !== null) {
            $oc = (int)$orderColumn;
            if ($oc === 1) $db->orderBy('name', $orderDirection);
            else $db->orderBy('id', $orderDirection);
        } else {
            // Default ordering: clients WITHOUT any booking_helper rows (for selected bh statuses) first,
            // then clients with booking_helper ordered by their smallest file_num (ascending).
            $sub_count = "(SELECT COUNT(*) FROM `" . T_BOOKING_HELPER . "` bh WHERE bh.client_id = " . T_CUSTOMERS . ".id AND bh.status IN ($bh_status_list))";
            $sub_min_file = "(SELECT MIN(NULLIF(bh.file_num, '')) FROM `" . T_BOOKING_HELPER . "` bh WHERE bh.client_id = " . T_CUSTOMERS . ".id AND bh.status IN ($bh_status_list))";
    
            $db->orderBy($sub_count, 'ASC');
            $db->orderBy($sub_min_file, 'DESC');
            $db->orderBy('id', 'DESC');
        }
    
        $db->pageLimit = $length > 0 ? $length : 10;
        try {
            $clients = $db->objectbuilder()->paginate(T_CUSTOMERS, $page_num);
        } catch (Exception $e) {
            echo json_encode([
                "draw" => intval($_POST['draw'] ?? 0),
                "recordsTotal" => $recordsTotal,
                "recordsFiltered" => $recordsFiltered,
                "data" => [],
                "error" => "DB paginate error: " . $e->getMessage()
            ]);
            exit();
        }
    
        if (empty($clients)) {
            echo json_encode([
                "draw" => intval($_POST['draw'] ?? 0),
                "recordsTotal" => $recordsTotal,
                "recordsFiltered" => $recordsFiltered,
                "data" => []
            ]);
            exit();
        }
    
        // ---------- 4) Aggregate bookings for page's clients ----------
        $pageClientIds = [];
        foreach ($clients as $c) {
            $cid = is_object($c) ? ($c->id ?? 0) : ($c['id'] ?? 0);
            if ($cid > 0) $pageClientIds[] = (int)$cid;
        }
        if (empty($pageClientIds)) {
            echo json_encode([
                "draw" => intval($_POST['draw'] ?? 0),
                "recordsTotal" => $recordsTotal,
                "recordsFiltered" => $recordsFiltered,
                "data" => []
            ]);
            exit();
        }
        $ids_str = implode(',', $pageClientIds);
    
        $helperAgg = [];
        try {
            // build plot aggregator SQL depending on dedupe setting
            $plot_group_concat = $dedupe_plots
                ? "GROUP_CONCAT(DISTINCT NULLIF(b.plot, '') SEPARATOR '||')"
                : "GROUP_CONCAT(NULLIF(b.plot, '') SEPARATOR '||')";
    
            $sqlAgg1 = "
                SELECT bh.client_id,
                       TRIM(BOTH ',' FROM GROUP_CONCAT(NULLIF(b.project, '') SEPARATOR ',')) AS projects_b,
                       TRIM(BOTH ',' FROM GROUP_CONCAT(DISTINCT NULLIF(bh.file_num, '') SEPARATOR ',')) AS file_nums_bh,
                       TRIM(BOTH ',' FROM GROUP_CONCAT(DISTINCT NULLIF(b.block, '') SEPARATOR '||')) AS blocks_b,
                       TRIM(BOTH ',' FROM " . $plot_group_concat . ") AS plots_b
                FROM `" . T_BOOKING_HELPER . "` bh
                JOIN `" . T_BOOKING . "` b ON b.id = bh.booking_id AND b.status = 2
                WHERE bh.client_id IN ($ids_str) AND bh.status IN ($bh_status_list)
                GROUP BY bh.client_id
            ";
            $rowsAgg1 = $db->rawQuery($sqlAgg1);
            foreach ($rowsAgg1 as $ar) {
                $cid = is_object($ar) ? ($ar->client_id ?? 0) : ($ar['client_id'] ?? 0);
                $helperAgg[(int)$cid] = (array)$ar;
            }
        } catch (Exception $e) { /* ignore */ }
    
        // ---------- 5) Build response rows ----------
        $outputData = [];
        foreach ($clients as $client) {
            $client_id = is_object($client) ? ($client->id ?? 0) : ($client['id'] ?? 0);
            $client_name = is_object($client) ? ($client->name ?? '') : ($client['name'] ?? '');
            $client_nid = is_object($client) ? ($client->nid ?? '') : ($client['nid'] ?? '');
            $client_birthday = is_object($client) ? ($client->birthday ?? '') : ($client['birthday'] ?? '');
            $client_phone = is_object($client) ? ($client->phone ?? '') : ($client['phone'] ?? '');
            $client_email = is_object($client) ? ($client->email ?? '') : ($client['email'] ?? '');
    
            $hb = isset($helperAgg[$client_id]) ? $helperAgg[$client_id] : [];
    
            // normalize projects (preserve duplicates by default)
            $projects_all = $hb ? explode(',', (isset($hb['projects_b']) ? $hb['projects_b'] : '')) : array();
            $projects_all = array_map('trim', $projects_all);
            $projects_all = array_filter($projects_all, function($v){ return $v !== ''; });
            if ($dedupe_projects) {
                $projects_all = array_values(array_unique($projects_all));
            }
            $project_names_display = implode('<br> ', array_map(function($p){
                return ucwords(str_replace('-', ' ', $p));
            }, $projects_all));
            $project_names = $project_names_display;
    
            // normalize & dedupe blocks
            $blocks = $hb ? preg_split('/\|\|/', (isset($hb['blocks_b']) ? $hb['blocks_b'] : '')) : array();
            $blocks = array_map('trim', $blocks);
            $blocks = array_filter($blocks, function($v){ return $v !== ''; });
            $blocks = array_values(array_unique($blocks));
            $block_html = implode('<br>', $blocks);
    
            // normalize & (optionally) preserve duplicates for plots
            $plots = $hb ? preg_split('/\|\|/', (isset($hb['plots_b']) ? $hb['plots_b'] : '')) : array();
            $plots = array_map('trim', $plots);
            $plots = array_filter($plots, function($v){ return $v !== ''; });
            if ($dedupe_plots) {
                $plots = array_values(array_unique($plots));
            }
            $plots_html = implode('<br>', $plots);
    
            // normalize & dedupe file numbers
            $file_nums_arr = $hb ? explode(',', (isset($hb['file_nums_bh']) ? $hb['file_nums_bh'] : '')) : array();
            $file_nums_arr = array_map('trim', $file_nums_arr);
            $file_nums_arr = array_filter($file_nums_arr, function($v){ return $v !== ''; });
            $file_nums_arr = array_values(array_unique($file_nums_arr));
            $file_nums_csv = implode('<br> ', $file_nums_arr);
    
            // Actions (fixed quoting & concatenation)
            $action = '';
            if (function_exists('Wo_IsAdmin') && (Wo_IsAdmin() || (function_exists('Wo_IsModerator') && Wo_IsModerator()) || (function_exists('check_permission') && check_permission('manage-clients')))) {
                $action  = '<div class="d-flex align-items-center gap-3 fs-6">';
                $action .= '<a href="javascript:;" class="text-primary" onclick="viewClient(' . $client_id . ')"><i class="fadeIn animated bx bx-book-open"></i></a>';
                $action .= '<a href="javascript:;" class="text-warning d-flex" onclick="editClient(' . $client_id . ')"><i class="fadeIn animated bx bx-edit-alt"></i></a>';
                $action .= '<a href="javascript:;" class="text-danger" onclick="deleteClient(' . $client_id . ')"><i class="fadeIn animated bx bx-trash"></i></a>';
                $action .= '</div>';
            } elseif (function_exists('check_permission') && check_permission('clients')) {
                $action  = '<div class="d-flex align-items-center gap-3 fs-6">';
                $action .= '<a href="javascript:;" class="text-primary" onclick="viewClient(' . $client_id . ')"><i class="fadeIn animated bx bx-book-open"></i></a>';
                $action .= '</div>';
            }
    
            // If a project_filter is provided, skip clients that have no matching project entries
            if ($project_filter !== '' && empty($project_names)) continue;
    
            // Safety check for search (we already filtered by IDs earlier)
            if ($searchValue !== '' && stripos($client_name, $searchValue) === false && stripos($file_nums_csv, $searchValue) === false) {
                continue;
            }
    
            $outputData[] = array(
                'id' => $client_id,
                'customer_name' => $client_name,
                'project_name' => $project_names,
                'project_name_display' => $project_names_display,
                'nid' => $client_nid,
                'birthday' => ($client_birthday ? date('d M Y', strtotime($client_birthday)) : '-'),
                'phone' => $client_phone,
                'email' => $client_email,
                'block' => $block_html,
                'plots' => $plots_html,
                'file_nums' => $file_nums_csv,
                'actions' => $action
            );
        }
    
        echo json_encode(array(
            "draw" => intval($_POST['draw'] ?? 0),
            "recordsTotal" => $recordsTotal,
            "recordsFiltered" => $recordsFiltered,
            "data" => $outputData
        ));
        exit();
    }
    }

?>

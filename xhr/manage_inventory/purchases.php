<?php
    // Get purchase by ID (for Select2 preselection)
    if ($s === 'get_purchase_by_id') {
        header('Content-Type: application/json; charset=utf-8');
        
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        $project_id = isset($_GET['project_id']) ? trim($_GET['project_id']) : '';
        
        if ($id <= 0) {
            echo json_encode(['status' => 400, 'message' => 'Invalid ID']);
            exit;
        }
        
        try {
            $booking = $db->where('id', $id)->getOne(T_BOOKING);
            
            if (!$booking) {
                echo json_encode(['status' => 404, 'message' => 'Purchase not found']);
                exit;
            }
            
            // Build normalized item
            $labelParts = [];
            if (!empty($booking->block)) $labelParts[] = ucwords($booking->block);
            $labelParts[] = 'Plot ' . $booking->plot;
            if (!empty($booking->katha)) $labelParts[] = $booking->katha . ' katha';
            if (!empty($booking->road)) $labelParts[] = 'Road ' . $booking->road;
            if (!empty($booking->facing)) $labelParts[] = 'Facing ' . $booking->facing;
            $label = implode(' • ', array_filter($labelParts));
            
            $status = (string)($booking->status ?? '0');
            $status_label = 'Available';
            if ($status === '2') $status_label = 'Sold';
            elseif ($status === '4') $status_label = 'Cancelled';
            elseif ($status === '3') $status_label = 'Complete';
            
            $item = [
                'id' => $booking->id,
                'text' => $label,
                'plot' => $booking->plot,
                'block' => $booking->block,
                'katha' => $booking->katha,
                'road' => $booking->road,
                'facing' => $booking->facing,
                'status' => $status,
                'status_label' => $status_label,
                'available' => in_array($status, ['0', '1']) ? 1 : 0
            ];
            
            echo json_encode(['status' => 200, 'item' => $item]);
        } catch (Exception $e) {
            echo json_encode(['status' => 500, 'message' => 'Server error: ' . $e->getMessage()]);
        }
        exit;
    }

    // ------------------ GET PURCHASES LIST (Sold/Booked) ------------------
    // (Removed duplicate handler to allow server-side processing handler below to run)

    // ------------------------------
    // GET PURCHASE DETAILS FOR PAYMENT SCHEDULE
    // ------------------------------
    if ($s == 'get_purchase_details') {
        global $db, $wo;
    
        $purchase_id = isset($_GET['purchase_id']) ? (int)$_GET['purchase_id'] : (isset($_POST['purchase_id']) ? (int)$_POST['purchase_id'] : 0);
    
        if (!$purchase_id) {
            echo json_encode(['status' => 400, 'message' => 'Invalid purchase ID']);
            exit;
        }
    
        // Get helper/purchase
        $helper = $db->where('id', $purchase_id)->getOne(T_BOOKING_HELPER);
        if (!$helper) {
            echo json_encode(['status' => 404, 'message' => 'Purchase not found']);
            exit;
        }
    
        // Get booking
        $booking = $db->where('id', $helper->booking_id)->getOne(T_BOOKING);
        if (!$booking) {
            echo json_encode(['status' => 404, 'message' => 'Booking not found']);
            exit;
        }
    
        // Get client
        $client = function_exists('GetCustomerById') ? GetCustomerById($helper->client_id) : null;
        if (!$client) {
            echo json_encode(['status' => 404, 'message' => 'Client not found']);
            exit;
        }
    
        // numeric baseline
        $per_katha     = (float) ($helper->per_katha ?? 0);
        $katha         = (float) ($booking->katha ?? 0);
        $down_payment  = (float) ($helper->down_payment ?? 0);
        $booking_money = (float) ($helper->booking_money ?? 0);
        $total_price   = $per_katha * $katha;
        $remaining_after_advance = $total_price - ($down_payment + $booking_money);
    
        // read from crm_payment_schedule only (exclude archived status=99)
        $existing_schedule = [];
        $rows = $db->where('purchase_id', $purchase_id)->where('status', 99, '!=')->orderBy('due_date', 'ASC')->get('crm_payment_schedule');
    
        if ($rows && is_array($rows) && count($rows) > 0) {
            foreach ($rows as $r) {
                $existing_schedule[] = [
                    'particular'                 => $r->particular ?? '',
                    'type'                       => $r->type ?? '',
                    'date'                       => ($r->due_date && $r->due_date !== '0000-00-00') ? date('Y-m-d', strtotime($r->due_date)) : '',
                    'payment_date'               => ($r->payment_date && $r->payment_date !== '0000-00-00') ? date('Y-m-d', strtotime($r->payment_date)) : '',
                    'payment_method'             => $r->payment_method ?? '',
                    'installment_amount'         => (float) $r->installment_amount,
                    'paid_amount'                => (float) $r->paid_amount,
                    'installment'                => (int) ($r->installment_number ?? 0),
                    'money_receipt_no'           => $r->money_receipt_no ?? '',
                    'total_dues'                 => '', // legacy UI field (not stored)
                    'remarks'                    => $r->remarks ?? '',
                    'adjustment'                 => !!$r->is_adjustment,
                    'manual_adjustment'          => !!($r->manual_adjustment ?? false),
                    'manual_installment_edit'    => !!($r->manual_edit ?? false),
                    'original_installment_amount'=> (float) ($r->previous_amount ?? $r->installment_amount),
                    'till_due'                   => '',
                    'over_due'                   => 0,
                    'due_now'                    => 0,
                    'history'                    => [],
                    'paid'                       => ((float)$r->paid_amount >= (float)$r->installment_amount),
                    'status'                     => isset($r->status) ? (int)$r->status : 0
                ];
            }
        }
    
        // Payment configuration fields read directly from helper columns
        $payment_mode = $helper->mode_of_payment;
        $installments = intval($helper->installment_count ?? 60);
        $adjustment_type = $helper->adjustment_type ?? 'year_end';
        $monthly_amount = is_numeric($helper->monthly_amount ?? null) ? (float)round($helper->monthly_amount, 2) : null;
        $yearly_adjustment = is_numeric($helper->yearly_adjustment ?? null) ? (float)round($helper->yearly_adjustment, 2) : 0.00;
        $installment_start_date = $helper->start_date ?? date('Y-m-d');
        $start_option = $helper->installment_start_option ?? 'exact';
    
        // booking/down dates (legacy columns kept for UI compatibility)
        $booking_due_date = $helper->booking_due_date ?? '';
        $booking_payment_date = $helper->booking_payment_date ?? '';
        $down_due_date = $helper->down_due_date ?? '';
        $down_payment_date = $helper->down_payment_date ?? '';
    
        // build response
        $response = [
            'status' => 200,
            'purchase_id' => $helper->id,
            'client_name' => $client['name'] ?? '',
            'project_name' => $booking->project ?? '',
            'project_slug' => $booking->project ?? '',
            'total_price' => $total_price,
            'down_payment' => $down_payment,
            'booking_money' => $booking_money,
            'remaining_amount' => $remaining_after_advance,
            'per_katha' => $per_katha,
            'file_num' => $booking->file_num ?? '',
            'plot' => $booking->plot ?? '',
            'katha' => $katha,
            'block' => $booking->block ?? '',
            'road' => $booking->road ?? '',
            'facing' => $booking->facing ?? '',
            'default_start_date' => date('Y-m-d'),
            'default_installments' => $installments,
            'schedule' => $existing_schedule,
            'booking_due_date' => $booking_due_date,
            'booking_payment_date' => $booking_payment_date,
            'down_due_date' => $down_due_date,
            'down_payment_date' => $down_payment_date,
            'payment_mode' => (string)$payment_mode,
            'installments' => (int)$installments,
            'adjustment_type' => (string)$adjustment_type,
            'monthly_amount' => $monthly_amount,
            'yearly_adjustment' => (float)$yearly_adjustment,
            'installment_start_date' => $installment_start_date,
            'start_option' => (string)$start_option
        ];
    
        echo json_encode($response);
        exit;
    }

    // ------------------ NEW: Get available purchases (for Select2) ------------------
    if ($s === 'get_available_purchases') {
        header('Content-Type: application/json; charset=utf-8');
    
        $project_id = isset($_GET['project_id']) ? $_GET['project_id'] : (isset($_POST['project_id']) ? $_POST['project_id'] : '');
    
        if (!$project_id) {
            echo json_encode([]);
            exit;
        }
    
        $free_statuses = ['0', '1', '4', 'available','cancelled','canceled'];
    
        // Fetch bookings for project
        $bookings = $db->where('project', $project_id)->get(T_BOOKING);
    
        if (empty($bookings)) {
            echo json_encode([]);
            exit;
        }
    
        // Pre-fetch helpers grouped by booking_id
        $bookingIds = array_column($bookings, 'id');
        $helpers = [];
        if (!empty($bookingIds)) {
            $rawHelpers = $db->where('booking_id', $bookingIds, 'IN')->objectbuilder()->get(T_BOOKING_HELPER);
            foreach ($rawHelpers as $h) {
                $helpers[$h->booking_id][] = $h;
            }
        }
    
        $results = [];
        foreach ($bookings as $b) {
            // normalize booking status to string
            $bstatus = strtolower(trim((string)($b->status ?? '')));
    
            // collect helper statuses and detect conflicts
            $helperList = [];
            $hasNonFreeHelper = false;
            if (!empty($helpers[$b->id])) {
                foreach ($helpers[$b->id] as $h) {
                    $hstatus = strtolower(trim((string)($h->status ?? '')));
                    $helperList[] = [
                        'id' => isset($h->id) ? $h->id : null,
                        'booking_id' => $h->booking_id ?? null,
                        'file_id' => $h->file_id ?? null,
                        'status' => $hstatus,
                        'raw' => $h
                    ];
                    if ($hstatus !== '' && !in_array($hstatus, $free_statuses, true)) {
                        $hasNonFreeHelper = true;
                    }
                }
            }
    
            // Decide combined status: prefer a non-free helper status if present; otherwise booking.status
            $combinedStatus = $bstatus;
            if ($hasNonFreeHelper) {
                // pick first non-free helper status for clarity (could be customized)
                foreach ($helperList as $hl) {
                    if ($hl['status'] !== '' && !in_array($hl['status'], $free_statuses, true)) {
                        $combinedStatus = $hl['status'];
                        break;
                    }
                }
            }
    
            // Determine availability based on combinedStatus
            $available = ($combinedStatus === '' || in_array($combinedStatus, $free_statuses, true));
    
            // Produce a friendly status label (always a string; respects '0')
            if ($combinedStatus === '0' || $combinedStatus === '1' || $combinedStatus === 'available') {
                $status_label = 'Available';
            } elseif ($combinedStatus === '2' || $combinedStatus === 'sold' || $combinedStatus === 'booked') {
                $status_label = 'Sold';
            } elseif ($combinedStatus === '4' || $combinedStatus === 'cancelled' || $combinedStatus === 'canceled') {
                $status_label = 'Cancelled';
            } elseif ($combinedStatus === '') {
                $status_label = '';
            } else {
                // fallback: ucfirst raw status
                $status_label = ucfirst($combinedStatus);
            }
    
            // disabled flag: true when NOT available (frontend can choose to honor or ignore)
            $disabled = !$available;
    
            // Build label for Select2 display
            $labelParts = [];
            if (!empty($b->block)) $labelParts[] = ucwords($b->block);
            $labelParts[] = 'Plot ' . $b->plot;
            if (!empty($b->katha)) $labelParts[] = $b->katha . ' katha';
            if (!empty($b->road)) $labelParts[] = 'Road ' . $b->road;
            $label = implode(' • ', array_filter($labelParts));
    
            // summary of helper conflicts (if any non-free helpers exist)
            $conflicts = [];
            if (!empty($helperList)) {
                foreach ($helperList as $hl) {
                    if ($hl['status'] !== '' && !in_array($hl['status'], $free_statuses, true)) {
                        $conflicts[] = [
                            'helper_id' => $hl['id'],
                            'booking_id' => $hl['booking_id'],
                            'file_id' => $hl['file_id'],
                            'status' => $hl['status']
                        ];
                    }
                }
            }
    
            $results[] = [
                'id'            => $b->id,
                'text'          => $label ?: ('Plot ' . $b->plot),
                'katha'         => $b->katha,
                'plot'          => $b->plot,
                'block'         => $b->block,
                'road'          => $b->road,
                'status'        => $combinedStatus,      // raw/computed status (may be '0', 'sold', 'booked', etc.)
                'status_label'  => $status_label,        // human readable label (string; "0" will become "Available")
                'available'     => $available ? 1 : 0,   // helpful for client-side quick checks
                'disabled'      => $disabled ? 1 : 0,    // UI may use this to visually disable selection
                'helpers'       => $helperList,          // raw helper entries (if you want to inspect)
                'conflicts'     => $conflicts            // simplified conflict summary (only non-free helpers)
            ];
        }
    
        echo json_encode($results);
        exit;
    }
        
    if ($s === 'get_purchases_list') {
        header('Content-Type: application/json; charset=utf-8');

        // DataTable parameters
        $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 0;
        $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
        $length = isset($_POST['length']) ? intval($_POST['length']) : 25;
        $search = isset($_POST['search']) ? $_POST['search'] : ''; // Global search value
        $order = isset($_POST['order']) ? $_POST['order'] : [];
        
        // Custom filters
        $project_filter = isset($_POST['project_filter']) ? $_POST['project_filter'] : '';
        $status_filter = isset($_POST['status_filter']) ? $_POST['status_filter'] : '';

        // Base query
        $db->join('wo_booking b', 'h.booking_id = b.id', 'LEFT');
        $db->join(T_PROJECTS . ' p', 'b.project = p.id', 'LEFT');
        $db->join(T_CUSTOMERS . ' c', 'h.client_id = c.id', 'LEFT');
        
        // Select columns
        $cols = [
            'h.id as purchase_id',
            'h.file_num',
            'h.status',
            'h.booking_money',
            'h.down_payment',
            'h.per_katha',
            'b.katha',
            'b.plot',
            'b.block',
            'b.road',
            'p.name as project_name',
            'c.name as client_name',
            'c.id as client_id'
        ];

        // Apply filters
        if (!empty($search)) {
            $db->where('(h.file_num LIKE ? OR c.name LIKE ? OR p.name LIKE ? OR b.plot LIKE ?)', ["%$search%", "%$search%", "%$search%", "%$search%"]);
        }
        
        if (!empty($project_filter)) {
            $db->where('(p.id = ? OR p.slug = ?)', [$project_filter, $project_filter]);
        }
        
        if ($status_filter !== '') {
            $db->where('h.status', $status_filter);
        }

        // Clone for total count
        $countDb = clone $db;
        $totalRecords = $countDb->getValue('wo_booking_helper h', 'count(*)');
        $filteredRecords = $totalRecords; // For now assume filtered = total if no complex search logic outside where

        // Ordering
        if (!empty($order)) {
            $colIdx = $order[0]['column'];
            $dir = $order[0]['dir'];
            $colName = 'h.id'; // Default
            
            switch ($colIdx) {
                case 0: $colName = 'h.file_num'; break;
                case 1: $colName = 'c.name'; break;
                case 2: $colName = 'p.name'; break;
                case 3: $colName = 'b.block'; break;
                case 4: $colName = 'b.plot'; break;
                case 5: $colName = 'b.katha'; break;
                // Add more as needed
            }
            $db->orderBy($colName, $dir);
        } else {
            $db->orderBy('h.id', 'DESC');
        }

        // Pagination
        if ($length != -1) {
            $db->pageLimit = $length;
            $purchases = $db->arraybuilder()->paginate('wo_booking_helper h', ($start / $length) + 1, $cols);
        } else {
            $purchases = $db->get('wo_booking_helper h', null, $cols);
        }

        // Process data
        $data = [];
        foreach ($purchases as $p) {
            // Calculate financials
            $total_price = (float)$p['per_katha'] * (float)$p['katha'];
            $paid = $db->where('purchase_id', $p['purchase_id'])->getValue('crm_payment_schedule', 'SUM(paid_amount)');
            $total_paid = (float)$paid + (float)$p['booking_money'] + (float)$p['down_payment']; // Include initial payments if not in schedule? 
            // Actually usually booking/down are in schedule as rows 1 & 2. Let's assume schedule sum is correct if generated properly.
            // If schedule table is used, it should contain all payments.
            // Let's stick to the logic used elsewhere:
            $total_paid = (float)$paid; 
            
            // Status label
            $status_labels = [
                '0' => 'Available', '1' => 'Active', '2' => 'Sold', '3' => 'Complete', '4' => 'Cancelled'
            ];
            $status_label = $status_labels[$p['status']] ?? 'Unknown';

            $data[] = [
                'purchase_id' => $p['purchase_id'],
                'file_num' => $p['file_num'],
                'client_name' => $p['client_name'],
                'client_id' => $p['client_id'],
                'project' => $p['project_name'],
                'block' => $p['block'],
                'plot' => $p['plot'],
                'katha' => $p['katha'],
                'total_price' => $total_price,
                'total_paid' => $total_paid,
                'total_due' => max(0, $total_price - $total_paid),
                'status_label' => $status_label
            ];
        }

        echo json_encode([
            'draw' => $draw,
            'recordsTotal' => (int)$totalRecords,
            'recordsFiltered' => (int)$filteredRecords, 
            'data' => $data
        ]);
        exit;
    }

    // ... (previous code) ...
    
    if ($s === 'search_purchases') {
        header('Content-Type: application/json; charset=utf-8');
    
        $q = isset($_GET['q']) ? trim($_GET['q']) : (isset($_POST['q']) ? trim($_POST['q']) : '');
        $page = isset($_GET['page']) ? (int)$_GET['page'] : (isset($_POST['page']) ? (int)$_POST['page'] : 1);
        $per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : (isset($_POST['per_page']) ? (int)$_POST['per_page'] : 30);
        $project_input = isset($_GET['project_id']) ? $_GET['project_id'] : (isset($_POST['project_id']) ? $_POST['project_id'] : ''); 
        
        // Resolve project_id if it's a slug
        $project_id = '';
        if ($project_input) {
            if (is_numeric($project_input)) {
                $project_id = $project_input;
            } else {
                // It's a slug, find the ID
                $proj = $db->where('slug', $project_input)->getOne(T_PROJECTS, ['id']);
                if ($proj) {
                    $project_id = $proj->id;
                }
            }
        }
    
        if ($page < 1) $page = 1;
        if ($per_page < 1) $per_page = 30;
        $offset = ($page - 1) * $per_page;
        $limit = $per_page + 1; // request one extra to detect "more"
    
        // Tokenize the query (whitespace split)
        $tokens = [];
        if ($q !== '') {
            $rawTokens = preg_split('/\s+/', $q);
            foreach ($rawTokens as $t) {
                $tTrim = trim($t);
                if ($tTrim !== '') $tokens[] = $tTrim;
            }
        }
    
        // Base WHERE and params
        $whereParts = [];
        $params = [];
        
        if ($project_input) {
            $whereParts[] = "`project` = ?";
            $params[] = $project_input;
        }
    
        // Process each token and append a single OR-group per token.
        foreach ($tokens as $token) {
            $tok = trim($token);
            if ($tok === '') continue;
            $lower = mb_strtolower($tok, 'UTF-8');
    
            $groupSql = [];     // OR clauses for this token
            $groupParams = [];  // params for this token (kept contiguous)
    
            // 1) explicit KATHA forms: "k5", "5k", "k 5"
            if (preg_match('/^(?:k(?:atha)?)[:\-\s]*([0-9]+(?:\.\d+)?)$/i', $tok, $m) ||
                preg_match('/^([0-9]+(?:\.\d+)?)[:\-\s]*k(?:atha)?$/i', $tok, $m)) {
                $num = $m[1];
                $groupSql[] = "CAST(`katha` AS DECIMAL(10,3)) = ?";
                $groupParams[] = $num;
                $groupSql[] = "LOWER(`katha`) LIKE ?";
                $groupParams[] = '%' . $lower . '%';
            }
            // 2) explicit PLOT prefix: "p1543", "plot1543", "1543p"
            elseif (preg_match('/^(?:p(?:lot)?)[:\-\s]*([A-Za-z0-9\-_\/]+)$/i', $tok, $m) ||
                    preg_match('/^([A-Za-z0-9\-_\/]+)[:\-\s]*p(?:lot)?$/i', $tok, $m)) {
                $val = mb_strtolower($m[1], 'UTF-8');
                $groupSql[] = "LOWER(`plot`) = ?";
                $groupParams[] = $val;
                $groupSql[] = "LOWER(`plot`) LIKE ?";
                $groupParams[] = '%' . $val . '%';
                $groupSql[] = "LOWER(CONCAT_WS(' ', `block`, `plot`)) LIKE ?";
                $groupParams[] = '%' . $lower . '%';
            }
            // 3) explicit ROAD prefix: "r2", "rd30", "road 4", "2r"
            elseif (preg_match('/^(?:r|rd|road)[:\-\s]*([0-9]+)$/i', $tok, $m) ||
                    preg_match('/^([0-9]+)[:\-\s]*(?:r|rd|road)$/i', $tok, $m)) {
                $num = (int)$m[1];
                $groupSql[] = "`road` RLIKE ?";
                $groupParams[] = '[[:<:]]' . $num . '[[:>:]]';
                $groupSql[] = "CAST(REGEXP_REPLACE(`road`, '[^0-9\\-]', '') AS SIGNED) = ?";
                $groupParams[] = $num;
                $groupSql[] = "LOWER(`road`) LIKE ?";
                $groupParams[] = '%' . $lower . '%';
            }
            // 4) explicit BLOCK forms: "bA", "blockA", "block A", "A block", "BA"
            // IMPORTANT: block matching uses only equality checks as you requested.
            elseif (preg_match('/^(?:b|block)[:\-\s]*([A-Za-z0-9\-\_\/]{1,8})$/i', $tok, $m) ||
                    preg_match('/^([A-Za-z0-9\-\_\/]{1,8})[:\-\s]*(?:block|b)$/i', $tok, $m)) {
                $val = mb_strtolower($m[1], 'UTF-8');
    
                // If token is a single character (like "a" or "B"), do a single equality check:
                if (mb_strlen($val, 'UTF-8') === 1) {
                    $groupSql[] = "LOWER(`block`) = ?";
                    $groupParams[] = $val;
                } else {
                    // Multi-letter token: split into characters and create equality ORs:
                    // (LOWER(block) = ? OR LOWER(block) = ? ...)
                    $chars = preg_split('//u', $val, -1, PREG_SPLIT_NO_EMPTY);
                    $chars = array_values(array_unique($chars));
                    foreach ($chars as $ch) {
                        $groupSql[] = "LOWER(`block`) = ?";
                        $groupParams[] = $ch;
                    }
                    // Note: we do NOT add LIKE or IN; we only use equality checks as requested.
                }
            }
            // 5) mixed letters+digits e.g. "p12a", "r12b" -> leading-letter heuristics
            elseif (preg_match('/[A-Za-z]/', $tok) && preg_match('/\d/', $tok)) {
                $first = strtolower($tok[0]);
                if ($first === 'p') {
                    if (preg_match('/^p[:\-\s]*([A-Za-z0-9\-_\/]+)$/i', $tok, $m)) $val = mb_strtolower($m[1], 'UTF-8');
                    else $val = mb_strtolower($tok, 'UTF-8');
                    $groupSql[] = "LOWER(`plot`) = ?"; $groupParams[] = $val;
                    $groupSql[] = "LOWER(`plot`) LIKE ?"; $groupParams[] = '%' . $val . '%';
                    $groupSql[] = "LOWER(CONCAT_WS(' ', `block`, `plot`)) LIKE ?"; $groupParams[] = '%' . $lower . '%';
                } elseif ($first === 'r') {
                    if (preg_match('/^r[:\-\s]*([0-9]+)$/i', $tok, $m)) $num = (int)$m[1];
                    elseif (preg_match('/^([0-9]+)r$/i', $tok, $m)) $num = (int)$m[1];
                    else { preg_match('/([0-9]+)/', $tok, $md); $num = isset($md[1]) ? (int)$md[1] : 0; }
                    if ($num > 0) {
                        $groupSql[] = "`road` RLIKE ?"; $groupParams[] = '[[:<:]]' . $num . '[[:>:]]';
                        $groupSql[] = "CAST(REGEXP_REPLACE(`road`, '[^0-9\\-]', '') AS SIGNED) = ?"; $groupParams[] = $num;
                        $groupSql[] = "LOWER(`road`) LIKE ?"; $groupParams[] = '%' . $lower . '%';
                    } else {
                        $groupSql[] = "LOWER(CONCAT_WS(' ', `block`, `plot`, `katha`, `road`)) LIKE ?";
                        $groupParams[] = '%' . $lower . '%';
                    }
                } elseif ($first === 'k') {
                    if (preg_match('/k[:\-\s]*([0-9]+(?:\.\d+)?)/i', $tok, $m) || preg_match('/([0-9]+(?:\.\d+)?)k/i', $tok, $m)) {
                        $num = $m[1];
                        $groupSql[] = "CAST(`katha` AS DECIMAL(10,3)) = ?"; $groupParams[] = $num;
                        $groupSql[] = "LOWER(`katha`) LIKE ?"; $groupParams[] = '%' . $lower . '%';
                    } else {
                        $groupSql[] = "LOWER(CONCAT_WS(' ', `block`, `plot`, `katha`, `road`)) LIKE ?";
                        $groupParams[] = '%' . $lower . '%';
                    }
                } else {
                    $groupSql[] = "LOWER(CONCAT_WS(' ', `block`, `plot`, `katha`, `road`)) LIKE ?";
                    $groupParams[] = '%' . $lower . '%';
                }
            }
            // 6) short alphabetic tokens (A / B / BA / ABC) -> treat as block codes via equality only
            elseif (preg_match('/^[A-Za-z]{1,4}$/', $tok)) {
                $val = mb_strtolower($tok, 'UTF-8');
                if (mb_strlen($val, 'UTF-8') === 1) {
                    $groupSql[] = "LOWER(`block`) = ?";
                    $groupParams[] = $val;
                } else {
                    // multi-letter: generate equality ORs for each character (no LIKE)
                    $chars = preg_split('//u', $val, -1, PREG_SPLIT_NO_EMPTY);
                    $chars = array_values(array_unique($chars));
                    foreach ($chars as $ch) {
                        $groupSql[] = "LOWER(`block`) = ?";
                        $groupParams[] = $ch;
                    }
                }
            }
            // 7) status words
            elseif (preg_match('/\b(sold|cancelled|canceled|available|booked|complete|completed|pending)\b/i', $tok, $m)) {
                $val = mb_strtolower($m[1], 'UTF-8');
                $groupSql[] = "LOWER(`status`) LIKE ?";
                $groupParams[] = '%' . $val . '%';
                $groupSql[] = "LOWER(`status_label`) LIKE ?";
                $groupParams[] = '%' . $val . '%';
            }
            // 8) bare numeric token: treat heuristically
            elseif (preg_match('/^[0-9]+(?:\.[0-9]+)?$/', $tok)) {
                $numInt = (int)$tok;
                if ($numInt <= 999) {
                    $groupSql[] = "`road` RLIKE ?";
                    $groupParams[] = '[[:<:]]' . $numInt . '[[:>:]]';
                    $groupSql[] = "CAST(REGEXP_REPLACE(`road`, '[^0-9\\-]', '') AS SIGNED) = ?";
                    $groupParams[] = $numInt;
                    $groupSql[] = "LOWER(`road`) LIKE ?";
                    $groupParams[] = '%' . $lower . '%';
                } else {
                    $groupSql[] = "CAST(`katha` AS DECIMAL(10,3)) = ?";
                    $groupParams[] = $tok;
                    $groupSql[] = "LOWER(`katha`) LIKE ?";
                    $groupParams[] = '%' . $lower . '%';
                }
            }
            // 9) fallback: generic LIKE across common fields (not block)
            else {
                $groupSql[] = "LOWER(`plot`) LIKE ?";  $groupParams[] = '%' . $lower . '%';
                $groupSql[] = "LOWER(`katha`) LIKE ?"; $groupParams[] = '%' . $lower . '%';
                $groupSql[] = "LOWER(`road`) LIKE ?";  $groupParams[] = '%' . $lower . '%';
                $groupSql[] = "LOWER(`status`) LIKE ?"; $groupParams[] = '%' . $lower . '%';
                $groupSql[] = "LOWER(CONCAT_WS(' ', `block`,`plot`,`katha`,`road`)) LIKE ?"; $groupParams[] = '%' . $lower . '%';
            }
    
            // Append this token's grouped OR clause and that group's params (contiguous)
            if (!empty($groupSql)) {
                $whereParts[] = '(' . implode(' OR ', $groupSql) . ')';
                foreach ($groupParams as $p) $params[] = $p;
            }
        } // end foreach tokens
    
        // Final SQL and parameter append for offset/limit
        $whereSql = implode(' AND ', $whereParts);
        $sql = "SELECT SQL_CALC_FOUND_ROWS * FROM `" . T_BOOKING . "` WHERE " . $whereSql . " ORDER BY `id` ASC LIMIT ?, ?";
        $params[] = (int)$offset;
        $params[] = (int)$limit;
    
        try {
            $rows = $db->rawQuery($sql, $params);
        } catch (Exception $ex) {
            error_log('search_purchases rawQuery error: ' . $ex->getMessage());
            echo json_encode(['results'=>[], 'more'=>false]);
            exit;
        }
    
        // Determine "more"
        $fetchedCount = is_array($rows) ? count($rows) : 0;
        $more = false;
        if ($fetchedCount > $per_page) {
            $more = true;
            $rows = array_slice($rows, 0, $per_page);
        }
    
        if (empty($rows)) {
            echo json_encode(['results'=>[], 'more'=>$more]);
            exit;
        }
    
        // Pre-fetch helpers for bookings
        $bookingIds = array_column($rows, 'id');
        $helpers = [];
        if (!empty($bookingIds)) {
            $rawHelpers = $db->where('booking_id', $bookingIds, 'IN')->objectbuilder()->get(T_BOOKING_HELPER);
            if ($rawHelpers) {
                foreach ($rawHelpers as $h) {
                    $helpers[$h->booking_id][] = $h;
                }
            }
        }
    
        // free statuses set
        $free_statuses = ['0', '1', '4', 'available', 'cancelled', 'canceled'];
    
        // Build final results (keeps your original logic)
        $results = [];
        foreach ($rows as $b) {
            $bstatus = strtolower(trim((string)($b->status ?? '')));
    
            $helperList = [];
            $hasNonFreeHelper = false;
            if (!empty($helpers[$b->id])) {
                foreach ($helpers[$b->id] as $h) {
                    $hstatus = strtolower(trim((string)($h->status ?? '')));
                    $helperList[] = [
                        'id' => isset($h->id) ? $h->id : null,
                        'booking_id' => $h->booking_id ?? null,
                        'file_id' => $h->file_id ?? null,
                        'status' => $hstatus,
                        'raw' => $h
                    ];
                    if ($hstatus !== '' && !in_array($hstatus, $free_statuses, true)) {
                        $hasNonFreeHelper = true;
                    }
                }
            }
    
            $combinedStatus = $bstatus;
            if ($hasNonFreeHelper) {
                foreach ($helperList as $hl) {
                    if ($hl['status'] !== '' && !in_array($hl['status'], $free_statuses, true)) {
                        $combinedStatus = $hl['status'];
                        break;
                    }
                }
            }
    
            $available = ($combinedStatus === '' || in_array($combinedStatus, $free_statuses, true));
    
            if ($combinedStatus === '0' || $combinedStatus === '1' || $combinedStatus === 'available') {
                $status_label = 'Available';
            } elseif ($combinedStatus === '2' || $combinedStatus === 'sold' || $combinedStatus === 'booked') {
                $status_label = 'Sold';
            } elseif ($combinedStatus === '4' || $combinedStatus === 'cancelled' || $combinedStatus === 'canceled') {
                $status_label = 'Cancelled';
            } elseif ($combinedStatus === '') {
                $status_label = '';
            } else {
                $status_label = ucfirst($combinedStatus);
            }
    
            $disabled = !$available;
    
            // Select2 label
            $labelParts = [];
            if (!empty($b->block)) $labelParts[] = ucwords($b->block);
            $labelParts[] = 'Plot ' . $b->plot;
            if (!empty($b->katha)) $labelParts[] = $b->katha . ' katha';
            if (!empty($b->road)) $labelParts[] = 'Road ' . $b->road;
            if (!empty($b->facing)) $labelParts[] = 'Facing ' . $b->facing;
            $label = implode(' • ', array_filter($labelParts));
            
            // simplified conflicts (non-free helpers)
            $conflicts = [];
            if (!empty($helperList)) {
                foreach ($helperList as $hl) {
                    if ($hl['status'] !== '' && !in_array($hl['status'], $free_statuses, true)) {
                        $conflicts[] = [
                            'helper_id' => $hl['id'],
                            'booking_id' => $hl['booking_id'],
                            'file_id' => $hl['file_id'],
                            'status' => $hl['status']
                        ];
                    }
                }
            }
    
            $results[] = [
                'id'            => $b->id,
                'text'          => $label ?: ('Plot ' . $b->plot),
                'katha'         => $b->katha,
                'plot'          => $b->plot,
                'facing'        => $b->facing,
                'block'         => $b->block,
                'road'          => $b->road,
                'status'        => $combinedStatus,
                'status_label'  => $status_label,
                'available'     => $available ? 1 : 0,
                'disabled'      => $disabled ? 1 : 0,
                'helpers'       => $helperList,
                'conflicts'     => $conflicts
            ];
        }
    
        echo json_encode(['results' => $results, 'more' => $more]);
        exit;
    }

    // ------------------ NEW: Check plot/booking conflicts ------------------
    if ($s === 'check_plot_booking') {
        header('Content-Type: application/json; charset=utf-8');
    
        // Accept either POST or GET. Project can be numeric id or slug (string)
        $project_raw = isset($_POST['project_id']) ? trim($_POST['project_id']) : (isset($_GET['project_id']) ? trim($_GET['project_id']) : '');
        $purchase_id = isset($_POST['purchase_id']) ? (int) $_POST['purchase_id'] : (isset($_GET['purchase_id']) ? (int) $_GET['purchase_id'] : 0);

        // Basic validation
        if ($project_raw === '' || $purchase_id <= 0) {
            http_response_code(400);
            echo json_encode([
                'status' => 400,
                'message' => 'Missing or invalid parameters.',
                'project' => $project_raw,
                'purchase_id' => $purchase_id
            ]);
            exit;
        }
    
        $conflicts = [];
        $free_statuses = ['0','1','available','cancelled','canceled'];
    
        // Query booking
        $bookings = $db->where('id', $purchase_id)->where('project', $project_raw)->get(T_BOOKING);
    
        if (empty($bookings)) {
            echo json_encode(['status' => 200, 'available' => true, 'message' => 'No booking found (treat as available).']);
            exit;
        }
    
        foreach ($bookings as $bk) {
            $bstatus = isset($bk->status) ? strtolower(trim((string)$bk->status)) : '';
    
            // fetch helpers for this booking;
            $helpers = $db->where('booking_id', $bk->id)->groupBy('client_id')->get(T_BOOKING_HELPER);
    
            if (!empty($helpers)) {
                foreach ($helpers as $h) {
                    $hstatus = isset($h->status) ? strtolower(trim((string)$h->status)) : ($bstatus ?: '');
                    if ($hstatus === '') $hstatus = 'unknown';
    
                    if (!in_array($hstatus, $free_statuses, true)) {
                        $conflicts[] = [
                            'booking_id' => $bk->id,
                            'status'     => $hstatus,
                            'time'       => $h->time ?? null
                        ];
                    }
                }
            } else {
                // no helpers -> rely on booking status
                if ($bstatus !== '' && !in_array($bstatus, $free_statuses, true)) {
                    $conflicts[] = [
                        'booking_id' => $bk->id,
                        'status'     => $bstatus,
                        'time'       => $bk->time ?? null
                    ];
                }
            }
        }
        if (empty($conflicts)) {
            echo json_encode(['status' => 200, 'available' => true, 'message' => 'Plot appears available (no active bookings found).']);
        } else {
            echo json_encode(['status' => 200, 'available' => false, 'message' => 'Active booking(s) found.', 'conflicts' => $conflicts]);
        }
        exit;
    }

// ------------------ Register / assign a purchase to a client (updated for booking money) ------------------
if ($s === 'register_purchase' || $s === 'assign_purchase') {
    header('Content-Type: application/json; charset=utf-8');

    // DEV: set to true during debugging; set false in production
    $DEV_DEBUG = false;

    try {
        // Inputs (sanitize)
        $client_id_raw   = $_POST['client_id'] ?? $_GET['client_id'] ?? 0;
        $client_id       = (int)$client_id_raw;

        $project_raw     = isset($_POST['project_id']) ? trim($_POST['project_id']) : (isset($_GET['project_id']) ? trim($_GET['project_id']) : '');
        $file_num_raw    = isset($_POST['file_num']) ? trim($_POST['file_num']) : (isset($_GET['file_num']) ? trim($_GET['file_num']) : '');
        $purchase_id     = isset($_POST['purchase_id']) ? (int) $_POST['purchase_id'] : (isset($_GET['purchase_id']) ? (int) $_GET['purchase_id'] : 0);
        $down_payment    = isset($_POST['down_payment']) ? floatval($_POST['down_payment']) : 0.0;
        $booking_money   = isset($_POST['booking_money']) ? floatval($_POST['booking_money']) : 0.0; // Add booking money
        $per_katha       = isset($_POST['per_katha']) ? floatval($_POST['per_katha']) : 0.0;
        $nominee_ids_raw = $_POST['nominee_ids'] ?? $_GET['nominee_ids'] ?? '[]';
        $force           = isset($_POST['force']) ? ($_POST['force'] === '1' || $_POST['force'] === 1 || $_POST['force'] === true) :
                           (isset($_GET['force']) ? ($_GET['force'] === '1' || $_GET['force'] === 1) : false);
        
        // NEW: Schedule parameter for auto-generated schedule
        $schedule_raw = $_POST['schedule'] ?? $_GET['schedule'] ?? null;
        $schedule = null;
        if ($schedule_raw) {
            $decoded_schedule = json_decode($schedule_raw, true);
            if (is_array($decoded_schedule)) {
                $schedule = $decoded_schedule;
            }
        }
        
        // Schedule generation parameters (to store in helper)
        $payment_mode = $_POST['payment_mode'] ?? null;
        $installments = isset($_POST['installments']) ? (int)$_POST['installments'] : 60;
        $adjustment_type = $_POST['adjustment_type'] ?? 'year_end';
        $monthly_amount = isset($_POST['monthly_amount']) ? (float)$_POST['monthly_amount'] : 0;
        $yearly_adjustment = isset($_POST['yearly_adjustment']) ? (float)$_POST['yearly_adjustment'] : 0;
        $start_date = $_POST['start_date'] ?? date('Y-m-d');
        $start_option = $_POST['start_option'] ?? 'exact';
        $interest_rate = isset($_POST['interest_percent']) ? (float)$_POST['interest_percent'] : 3.0;

        // Basic required validation
        $missing = [];
        if ($client_id <= 0)      $missing[] = 'client_id';
        if ($project_raw === '')  $missing[] = 'project_id';
        if ($file_num_raw === '') $missing[] = 'file_num';
        if ($purchase_id <= 0)    $missing[] = 'purchase_id';
        if ($per_katha <= 0)      $missing[] = 'per_katha';
        if ($down_payment < 0)    $missing[] = 'down_payment';
        if ($booking_money < 0)   $missing[] = 'booking_money'; // Validate booking money

        if (!empty($missing)) {
            http_response_code(400);
            echo json_encode(['status'=>400,'message'=>'Missing or invalid parameters: ' . implode(', ', $missing)]);
            exit;
        }

        // Normalize file_num: keep letters/numbers, dash, underscore, slash and spaces
        $file_num = preg_replace('/[^\p{L}\p{N}\-\_\/\s]/u', '', $file_num_raw);
        $file_num = trim($file_num);

        // nominee_ids -> array of ints (accept JSON or comma list or array)
        $nominee_ids = [];
        if (is_string($nominee_ids_raw)) {
            $decoded = json_decode($nominee_ids_raw, true);
            if (is_array($decoded)) {
                $nominee_ids = $decoded;
            } else {
                // try comma separated
                $tmp = preg_split('/\s*,\s*/', trim($nominee_ids_raw));
                $nominee_ids = array_filter($tmp, function($v){ return $v !== ''; });
            }
        } elseif (is_array($nominee_ids_raw)) {
            $nominee_ids = $nominee_ids_raw;
        }
        // coerce to ints where possible
        $nominee_ids = array_values(array_map(function($v){
            if (is_numeric($v)) return (int)$v;
            return $v;
        }, $nominee_ids));
        $nominee_ids_json = json_encode($nominee_ids);

        // --- Verify client exists in crm_customers ---
        $clientExists = $db->where('id', $client_id)->getValue('crm_customers', 'id');
        if (!$clientExists) {
            http_response_code(404);
            echo json_encode(['status'=>404,'message'=>'Client not found (crm_customers).']);
            exit;
        }

        // --- Find booking in wo_booking ---
        $booking = $db->where('id', $purchase_id)->getOne('wo_booking');
        if (!$booking) {
            http_response_code(404);
            echo json_encode(['status'=>404,'message'=>'Selected booking not found (wo_booking).']);
            exit;
        }

        // store booking katha for later price validation (if present)
        $booking_katha = null;
        if (isset($booking->katha)) {
            // booking.katha is varchar, so sanitize numeric part
            $bk = preg_replace('/[^\d\.\-]/', '', (string)$booking->katha);
            $booking_katha = $bk !== '' ? floatval($bk) : null;
        }

        // optional strict validation: ensure (booking_money + down_payment) <= per_katha * katha
        if ($booking_katha !== null) {
            $expected_total = $per_katha * $booking_katha;
            if (($booking_money + $down_payment) > $expected_total) {
                http_response_code(422);
                echo json_encode(['status'=>422,'message'=>'Total advance (booking money + down payment) cannot exceed total price (per_katha * katha).','debug'=>[
                    'per_katha'=>$per_katha,'katha'=>$booking_katha,'expected_total'=>$expected_total,'booking_money'=>$booking_money,'down_payment'=>$down_payment
                ]]);
                exit;
            }
        }

        // Conflict detection (use wo_booking_helper)
        $conflicts = [];
        $free_statuses = ['0','1','available','cancelled','canceled'];

        // We'll look for existing helper that belongs to the *same client* + booking.
        $existingHelperForClient = $db
            ->where('booking_id', $booking->id)
            ->where('client_id', (string)$client_id)
            ->orderBy('id', 'DESC')
            ->getOne('wo_booking_helper');

        // Fetch all helpers for this booking to detect conflicts from *other* clients
        $helpers = $db->where('booking_id', $booking->id)->get('wo_booking_helper');
        if (!empty($helpers)) {
            foreach ($helpers as $h) {
                // if the helper belongs to the current client, skip adding as a conflict
                $h_client_id = isset($h->client_id) ? (string)$h->client_id : '';
                $hstatus = isset($h->status) ? strtolower(trim((string)$h->status)) : '';

                if ($h_client_id === (string)$client_id) {
                    // skip conflict for same client; we'll update this helper later instead of inserting
                    continue;
                }

                if ($hstatus !== '' && !in_array($hstatus, $free_statuses, true)) {
                    $conflicts[] = ['booking_id' => $booking->id, 'file_id' => $h->file_num ?? ($h->file_id ?? null), 'status' => $hstatus, 'helper_id' => $h->id ?? null];
                }
            }
        }

        // Also consider booking.status itself as conflict (but if booking was created by same client we don't know; so treat booking.status as conflict)
        $bstatus = isset($booking->status) ? strtolower(trim((string)$booking->status)) : '';
        if ($bstatus !== '' && !in_array($bstatus, $free_statuses, true)) {
            // If booking already marked sold but the same client has helper, allow update — otherwise count as conflict.
            $allow_if_same_client = ($existingHelperForClient ? true : false);
            if (!$allow_if_same_client) {
                $conflicts[] = ['booking_id' => $booking->id, 'file_id' => $booking->file_num ?? null, 'status' => $bstatus];
            }
        }

        // If there are conflicts (from other clients) and not forcing, reject
        if (!empty($conflicts) && !$force) {
            http_response_code(409);
            echo json_encode(['status'=>409,'message'=>'Active booking(s) exist for this plot (other client). Use force to override.','conflicts'=>$conflicts]);
            exit;
        }

        // ------------------ Now: either update existing helper (same client) OR insert new ------------------

        // Start transaction
        $db->startTransaction();

        if ($existingHelperForClient) {
            // Update existing helper for same client instead of inserting a new helper
            $updateData = [
                'file_num'     => $file_num,
                'status'       => '2', // sold
                'time'         => time(),
                'nominee_ids'  => $nominee_ids_json,
                'per_katha'    => $per_katha,
                'down_payment' => $down_payment,
                'booking_money'=> $booking_money, // Add booking money
                'cancel_date'  => '', // clear cancel date on re-book
            ];
            
            // Add schedule generation parameters if provided
            if ($payment_mode) $updateData['mode_of_payment'] = $payment_mode;
            if ($monthly_amount) $updateData['monthly_amount'] = $monthly_amount;
            if ($adjustment_type) $updateData['adjustment_type'] = $adjustment_type;
            if ($yearly_adjustment) $updateData['yearly_adjustment'] = $yearly_adjustment;
            if ($start_date) $updateData['start_date'] = $start_date;
            if ($start_option) $updateData['installment_start_option'] = $start_option;
            if ($installments) $updateData['installment_count'] = $installments;
            if ($interest_rate) $updateData['interest_rate'] = $interest_rate;

            $ok = $db->where('id', $existingHelperForClient->id)->update('wo_booking_helper', $updateData);
            if ($ok === false) {
                $db->rollback();
                $err = $db->getLastError();
                http_response_code(500);
                echo json_encode(['status'=>500,'message'=>'Failed to update existing booking helper.','debug'=>($DEV_DEBUG ? $err : null)]);
                exit;
            }

            // Update booking record in wo_booking: status (int) and file_num
            $updateBooking = ['status' => 2, 'file_num' => $file_num];
            $updateOk = $db->where('id', $booking->id)->update('wo_booking', $updateBooking);
            if ($updateOk === false) {
                $db->rollback();
                $err = $db->getLastError();
                http_response_code(500);
                echo json_encode(['status'=>500,'message'=>'Failed to update booking (wo_booking).','debug'=>($DEV_DEBUG ? $err : null)]);
                exit;
            }
            
            // Save schedule if provided
            if ($schedule && is_array($schedule) && count($schedule) > 0) {
                // Delete old schedule for this purchase (exclude status=99)
                $db->where('purchase_id', $existingHelperForClient->id);
                $db->where('status', '99', '!=');
                $db->delete('crm_payment_schedule');
                
                // Insert new schedule
                foreach ($schedule as $item) {
                    $scheduleData = [
                        'purchase_id' => $existingHelperForClient->id,
                        'installment_number' => $item['installment_number'],
                        'particular' => $item['particular'],
                        'due_date' => $item['due_date'],
                        'installment_amount' => $item['installment_amount'],
                        'installment_type' => $item['installment_type'] ?? 'installment',
                        'paid_amount' => $item['paid_amount'] ?? 0,
                        'status' => $item['status'] ?? 0,
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s')
                    ];
                    
                    $insertSchedule = $db->insert('crm_payment_schedule', $scheduleData);
                    if (!$insertSchedule) {
                        $db->rollback();
                        echo json_encode(['status'=>500,'message'=>'Failed to save payment schedule']);
                        exit;
                    }
                }
            }

            $db->commit();

            $insert = $existingHelperForClient->id; // treat as the 'purchase id' returned

            // Build response row (reuse your existing HTML)
            $proj_display = htmlspecialchars($project_raw, ENT_QUOTES);
            $status_raw = (string) ($updateBooking['status'] ?? ($booking->status ?? '1'));
            $status_badges = [
                '1' => '<span class="badge bg-info">Available</span>',
                '2' => '<span class="badge bg-success">Sold</span>',
                '3' => '<span class="badge bg-success">Complete</span>',
                '4' => '<span class="badge bg-danger">Canceled</span>'
            ];
            $status_html = $status_badges[$status_raw] ?? $status_badges['1'];

            $rowHtml  = '<tr id="purchaseRow_' . $insert . '">';
            $rowHtml .= '<td>' . $proj_display . '</td>';
            $rowHtml .= '<td>' . htmlspecialchars($booking->block ?? 'N/A') . '</td>';
            $rowHtml .= '<td>' . htmlspecialchars($booking->katha ?? '') . '</td>';
            $rowHtml .= '<td>' . htmlspecialchars($booking->plot ?? '') . '</td>';
            $rowHtml .= '<td>' . htmlspecialchars($booking->road ?? '') . '</td>';
            $rowHtml .= '<td>' . htmlspecialchars($file_num) . '</td>';
            $rowHtml .= '<td>' . date('d M Y') . '</td>';
            $rowHtml .= '<td>' . $status_html . '</td>';
            $rowHtml .= '<td><div class="d-flex gap-1">';
            $rowHtml .= '<button class="btn btn-sm btn-info print-booking-form" data-id="' . $insert . '" title="Print Form"><i class="lni lni-printer"></i></button>';
            $rowHtml .= '<button class="btn btn-sm btn-success update_installment" data-id="' . $insert . '" title="Payment Schedule"><i class="lni lni-dollar"></i></button>';
            $rowHtml .= '<button class="btn btn-sm btn-warning change_plot_btn" data-id="' . $insert . '" title="Change Plot"><i class="lni lni-exchange"></i></button>';
            $rowHtml .= '<button class="btn btn-sm btn-danger cancel-purchase" data-id="' . $insert . '" title="Cancel Purchase"><i class="lni lni-close"></i></button>';
            $rowHtml .= '</div></td>';
            $rowHtml .= '</tr>';

            $logUser = 'User #' . ($wo['user']['id'] ?? '999');
            // logActivity('purchase', 'update', "{$logUser} updated purchase #{$insert} for booking #{$booking->id}");
            if ($db->tableExists('crm_audit_trail')) {
                $db->insert('crm_audit_trail', [
                    'user_id' => $wo['user']['id'] ?? 0,
                    'action_category' => 'purchase',
                    'action_type' => 'update',
                    'action_description' => "Purchase #{$insert} updated for booking #{$booking->id}",
                    'details' => json_encode(['purchase_id' => $insert, 'booking_id' => $booking->id]),
                    'performed_by' => $wo['user']['id'] ?? 0,
                    'ip_address' => $_SERVER['REMOTE_ADDR'],
                    'performed_at' => date('Y-m-d H:i:s')
                ]);
            }

            $resp = ['status'=>200,'message'=>'Existing purchase updated.','purchase_id'=>$insert,'html'=>$rowHtml];
            if ($DEV_DEBUG) {
                $resp['debug'] = [
                    'action'        => 'updated_existing_helper',
                    'existing_id'   => $existingHelperForClient->id,
                    'booking_id'    => $booking->id,
                    'client_id'     => $client_id,
                    'nominee_ids'   => $nominee_ids,
                    'booking_money' => $booking_money,
                ];
            }

            echo json_encode($resp);
            exit;
        }

        // No existing helper for this client -> insert new as usual
        $helperData = [
            'booking_id'    => $booking->id,
            'client_id'     => (string)$client_id, // your schema shows client_id is varchar(32)
            'file_num'      => $file_num, // sold (schema uses varchar)
            'status'        => '2', // sold (schema uses varchar)
            'time'          => time(),
            'nominee_ids'   => $nominee_ids_json,
            'per_katha'     => $per_katha,
            'down_payment'  => $down_payment,
            'booking_money' => $booking_money, // Add booking money
            'cancel_date'   => '',
        ];
        
        // Add schedule generation parameters if provided
        if ($payment_mode) $helperData['mode_of_payment'] = $payment_mode;
        if ($monthly_amount) $helperData['monthly_amount'] = $monthly_amount;
        if ($adjustment_type) $helperData['adjustment_type'] = $adjustment_type;
        if ($yearly_adjustment) $helperData['yearly_adjustment'] = $yearly_adjustment;
        if ($start_date) $helperData['start_date'] = $start_date;
        if ($start_option) $helperData['installment_start_option'] = $start_option;
        if ($installments) $helperData['installment_count'] = $installments;
        if ($interest_rate) $helperData['interest_rate'] = $interest_rate;

        $insert = $db->insert('wo_booking_helper', $helperData);
        if (!$insert) {
            $db->rollback();
            $err = $db->getLastError();
            http_response_code(500);
            echo json_encode(['status'=>500,'message'=>'Failed to create booking helper (wo_booking_helper).','debug'=>($DEV_DEBUG ? $err : null)]);
            exit;
        }

        // Update booking record in wo_booking: status (int) and file_num (text)
        $updateBooking = ['status' => 2, 'file_num' => $file_num];
        $updateOk = $db->where('id', $booking->id)->update('wo_booking', $updateBooking);
        if ($updateOk === false) {
            $db->rollback();
            $err = $db->getLastError();
            http_response_code(500);
            echo json_encode(['status'=>500,'message'=>'Failed to update booking (wo_booking).','debug'=>($DEV_DEBUG ? $err : null)]);
            exit;
        }
        
        // Save schedule if provided
        if ($schedule && is_array($schedule) && count($schedule) > 0) {
            foreach ($schedule as $item) {
                $scheduleData = [
                    'purchase_id' => $insert,
                    'installment_number' => $item['installment_number'],
                    'particular' => $item['particular'],
                    'due_date' => $item['due_date'],
                    'installment_amount' => $item['installment_amount'],
                    'installment_type' => $item['installment_type'] ?? 'installment',
                    'paid_amount' => $item['paid_amount'] ?? 0,
                    'status' => $item['status'] ?? 0,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                ];
                
                $insertSchedule = $db->insert('crm_payment_schedule', $scheduleData);
                if (!$insertSchedule) {
                    $db->rollback();
                    echo json_encode(['status'=>500,'message'=>'Failed to save payment schedule']);
                    exit;
                }
            }
        }

        $db->commit();

        // build response row for new insert
        $proj_display = htmlspecialchars($project_raw, ENT_QUOTES);
        $status_raw = (string) ($updateBooking['status'] ?? ($booking->status ?? '1'));
        $status_badges = [
            '1' => '<span class="badge bg-info">Available</span>',
            '2' => '<span class="badge bg-success">Sold</span>',
            '3' => '<span class="badge bg-success">Complete</span>',
            '4' => '<span class="badge bg-danger">Canceled</span>'
        ];
        $status_html = $status_badges[$status_raw] ?? $status_badges['1'];

        $rowHtml  = '<tr id="purchaseRow_' . $insert . '">';
        $rowHtml .= '<td>' . $proj_display . '</td>';
        $rowHtml .= '<td>' . htmlspecialchars($booking->block ?? 'N/A') . '</td>';
        $rowHtml .= '<td>' . htmlspecialchars($booking->katha ?? '') . '</td>';
        $rowHtml .= '<td>' . htmlspecialchars($booking->plot ?? '') . '</td>';
        $rowHtml .= '<td>' . htmlspecialchars($booking->road ?? '') . '</td>';
        $rowHtml .= '<td>' . htmlspecialchars($file_num) . '</td>';
        $rowHtml .= '<td>' . date('d M Y') . '</td>';
        $rowHtml .= '<td>' . $status_html . '</td>';
        $rowHtml .= '<td><div class="d-flex gap-1">';
        $rowHtml .= '<button class="btn btn-sm btn-info print-booking-form" data-id="' . $insert . '" title="Print Form"><i class="lni lni-printer"></i></button>';
        $rowHtml .= '<button class="btn btn-sm btn-success update_installment" data-id="' . $insert . '" title="Payment Schedule"><i class="lni lni-dollar"></i></button>';
        $rowHtml .= '<button class="btn btn-sm btn-warning change_plot_btn" data-id="' . $insert . '" title="Change Plot"><i class="lni lni-exchange"></i></button>';
        $rowHtml .= '<button class="btn btn-sm btn-danger cancel-purchase" data-id="' . $insert . '" title="Cancel Purchase"><i class="lni lni-close"></i></button>';
        $rowHtml .= '</div></td>';
        $rowHtml .= '</tr>';

        $logUser = 'User #' . ($wo['user']['id'] ?? '999');
        // logActivity('purchase', 'create', "{$logUser} created purchase #{$insert} for booking #{$booking->id}");
        if ($db->tableExists('crm_audit_trail')) {
            $db->insert('crm_audit_trail', [
                'user_id' => $wo['user']['id'] ?? 0,
                'action_category' => 'purchase',
                'action_type' => 'create',
                'action_description' => "Purchase #{$insert} created for booking #{$booking->id}",
                'details' => json_encode(['purchase_id' => $insert, 'booking_id' => $booking->id]),
                'performed_by' => $wo['user']['id'] ?? 0,
                'ip_address' => $_SERVER['REMOTE_ADDR'],
                'performed_at' => date('Y-m-d H:i:s')
            ]);
        }

        $resp = ['status'=>200,'message'=>'Purchase registered successfully.','purchase_id'=>$insert,'html'=>$rowHtml];
        if ($DEV_DEBUG) {
            $resp['debug'] = [
                'booking_id'   => $booking->id,
                'booking_katha'=> $booking_katha,
                'nominee_ids'  => $nominee_ids,
                'file_num'     => $file_num,
                'booking_money'=> $booking_money,
                'force'        => $force
            ];
        }

        echo json_encode($resp);
        exit;

    } catch (Exception $ex) {
        if (isset($db) && method_exists($db, 'rollback')) $db->rollback();
        http_response_code(500);
        echo json_encode(['status'=>500,'message'=>'Internal server error','error'=>$ex->getMessage()]);
        exit;
    }
}

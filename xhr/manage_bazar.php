<?php
// xhr/manage_bazar.php  (two-table version with item management + partitions support)
// Last updated: Sep 30, 2025
date_default_timezone_set('Asia/Dhaka');
header('Content-Type: application/json; charset=utf-8');

// suppress deprecated warnings (MysqliDb dynamic property deprecation on PHP 8.2+)
// you can remove this if vendor is updated to declare properties properly
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');

/* ------------------------------------------------
   CONFIG: table constants
   ------------------------------------------------ */
if (!defined('T_BAZAR_ITEMS')) define('T_BAZAR_ITEMS','crm_bazar_items');
if (!defined('T_BAZAR_LOGS'))  define('T_BAZAR_LOGS','crm_bazar_logs');

$s = $_GET['s'] ?? $_POST['s'] ?? '';
$f = $_GET['f'] ?? $_POST['f'] ?? '';

global $db, $wo;

/* ---------- Permission helper ---------- */
function has_manage_bazar_permission(){
    if (function_exists('Wo_IsAdmin') && Wo_IsAdmin()) return true;
    if (function_exists('Wo_IsModerator') && Wo_IsModerator()) return true;
    if (function_exists('check_permission') && (check_permission('bazar') || check_permission('manage-bazar'))) return true;
    global $wo;
    if (isset($wo['user']['is_bazar']) && $wo['user']['is_bazar'] == 1) return true;
    return false;
}

if ($f !== 'manage_bazar') { echo json_encode(['status'=>400,'message'=>'Invalid endpoint']); exit; }
if (!has_manage_bazar_permission()) { echo json_encode(['status'=>403,'message'=>"You don't have permission"]); exit; }

/* ---------- Helper to safely read DB row values (supports object or array returns) ---------- */
function row_value($row, $key, $default = null) {
    if ($row === null || $row === false) return $default;
    if (is_array($row)) {
        return array_key_exists($key, $row) ? $row[$key] : $default;
    }
    if (is_object($row)) {
        return property_exists($row, $key) ? $row->{$key} : $default;
    }
    return $default;
}

/* ---------- Lower-level helpers ---------- */

/**
 * get_opening_balance(bazar_id, ts)
 * returns opening quantity (float)
 */
function get_opening_balance($bazar_id, $ts, $include_hidden = true) {
    global $db;
    $whereHidden = $include_hidden ? '' : ' AND is_hidden = 0 ';
    $sqlAdd = "SELECT COALESCE(SUM(quantity),0) AS s FROM " . T_BAZAR_LOGS .
              " WHERE bazar_id = ? AND type = 'add' AND date_ts < ? {$whereHidden}";
    $rowA = $db->rawQueryOne($sqlAdd, [$bazar_id, $ts]);
    $adds = (float) row_value($rowA, 's', 0.0);

    $sqlUse = "SELECT COALESCE(SUM(quantity),0) AS s FROM " . T_BAZAR_LOGS .
              " WHERE bazar_id = ? AND type = 'use' AND date_ts < ? {$whereHidden}";
    $rowU = $db->rawQueryOne($sqlUse, [$bazar_id, $ts]);
    $uses = (float) row_value($rowU, 's', 0.0);

    $opening = $adds - $uses;
    if ($opening < 0) $opening = 0.0;
    return round($opening, 3);
}

/**
 * recalc_item_balance(bazar_id)
 * recomputes quantity in items table from logs
 */
function recalc_item_balance($bazar_id) {
    global $db;
    $rowA = $db->rawQueryOne("SELECT COALESCE(SUM(quantity),0) AS s FROM " . T_BAZAR_LOGS . " WHERE bazar_id = ? AND type = 'add'", [$bazar_id]);
    $adds = (float) row_value($rowA, 's', 0.0);

    $rowU = $db->rawQueryOne("SELECT COALESCE(SUM(quantity),0) AS s FROM " . T_BAZAR_LOGS . " WHERE bazar_id = ? AND type = 'use'", [$bazar_id]);
    $uses = (float) row_value($rowU, 's', 0.0);

    $final = $adds - $uses;
    if ($final < 0) $final = 0.0;
    $db->where('id', $bazar_id)->update(T_BAZAR_ITEMS, ['quantity' => round($final,3), 'updated_at' => time()]);
    return round($final,3);
}

/* ============================
   ADMIN: ITEM MANAGEMENT
   endpoints: admin_add_item, admin_update_item, admin_delete_item
   ============================ */

if ($s === 'admin_add_item') {
    if ( !( (function_exists('Wo_IsAdmin') && Wo_IsAdmin()) || (function_exists('Wo_IsModerator') && Wo_IsModerator()) || (function_exists('check_permission') && check_permission('manage-bazar')) ) ) {
        echo json_encode(['status' => 403, 'message' => 'Permission denied']);
        exit;
    }
    $name = trim($_POST['name'] ?? '');
    $unit = trim($_POST['unit'] ?? '');
    $icon = trim($_POST['icon'] ?? '');
    $quantity = isset($_POST['quantity']) ? (float) $_POST['quantity'] : 0.0;
    $price = isset($_POST['price']) ? (float) $_POST['price'] : 0.0;
    $low = isset($_POST['low_threshold']) ? (int) $_POST['low_threshold'] : 0;

    if ($name === '' || $unit === '') {
        echo json_encode(['status'=>400,'message'=>'Name and unit required']); exit;
    }

    if ($db->where('name', $name)->getOne(T_BAZAR_ITEMS)) {
        echo json_encode(['status'=>400,'message'=>'Item already exists']); exit;
    }

    $id = $db->insert(T_BAZAR_ITEMS, [
        'name'=>$name,'unit'=>$unit,'icon'=>$icon,'quantity'=>round($quantity,3),'price'=>round($price,2),'low_threshold'=>$low,'created_at'=>time(),'updated_at'=>time()
    ]);
    if (!$id) { echo json_encode(['status'=>500,'message'=>'Insert failed']); exit; }

    // If initial quantity > 0, add an initial log entry (so history is preserved)
    if ($quantity > 0) {
        $db->insert(T_BAZAR_LOGS, [
            'bazar_id'=>$id,'type'=>'add','quantity'=>round($quantity,3),'unit_price'=>round($price,2),'user_id'=>null,'date_ts'=>time(),'is_hidden'=>0,'created_at'=>time()
        ]);
    }

    if (function_exists('logActivity')) logActivity('bazar','create',"Admin added item {$name}");
    recalc_item_balance($id);
    echo json_encode(['status'=>200,'message'=>'Item created','item_id'=>$id]);
    exit;
}

if ($s === 'admin_update_item') {
    if ( !( (function_exists('Wo_IsAdmin') && Wo_IsAdmin()) || (function_exists('Wo_IsModerator') && Wo_IsModerator()) || (function_exists('check_permission') && check_permission('manage-bazar')) ) ) {
        echo json_encode(['status' => 403, 'message' => 'Permission denied']);
        exit;
    }
    
    $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
    if (!$id) { echo json_encode(['status'=>400,'message'=>'Missing id']); exit; }
    $name = trim($_POST['name'] ?? '');
    $unit = trim($_POST['unit'] ?? '');
    $icon = trim($_POST['icon'] ?? '');
    $price = isset($_POST['price']) ? (float) $_POST['price'] : null;
    $low = isset($_POST['low_threshold']) ? (int) $_POST['low_threshold'] : null;

    $it = $db->where('id', $id)->getOne(T_BAZAR_ITEMS);
    if (!$it) { echo json_encode(['status'=>404,'message'=>'Item not found']); exit; }
    if ($name === '' || $unit === '') { echo json_encode(['status'=>400,'message'=>'Name and unit required']); exit; }

    // check name uniqueness
    if ($name !== $it->name && $db->where('name', $name)->getOne(T_BAZAR_ITEMS)) {
        echo json_encode(['status'=>400,'message'=>'Another item has this name']); exit;
    }

    $upd = ['name'=>$name,'unit'=>$unit,'icon'=>$icon,'updated_at'=>time()];
    if ($price !== null) $upd['price'] = round($price,2);
    if ($low !== null) $upd['low_threshold'] = $low;

    $db->where('id',$id)->update(T_BAZAR_ITEMS, $upd);
    if (function_exists('logActivity')) logActivity('bazar','update',"Admin updated item {$name}");
    echo json_encode(['status'=>200,'message'=>'Item updated']);
    exit;
}

if ($s === 'admin_delete_item') {
    if (! (function_exists('Wo_IsAdmin') && Wo_IsAdmin()) && ! (function_exists('Wo_IsModerator') && Wo_IsModerator()) ) {
        echo json_encode(['status'=>403,'message'=>'Permission denied']); exit;
    }
    $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
    if (!$id) { echo json_encode(['status'=>400,'message'=>'Missing id']); exit; }

    $db->startTransaction();
    try {
        $db->where('bazar_id', $id)->delete(T_BAZAR_LOGS);
        $db->where('id', $id)->delete(T_BAZAR_ITEMS);
        $db->commit();
        if (function_exists('logActivity')) logActivity('bazar','delete',"Admin deleted item id {$id}");
        echo json_encode(['status'=>200,'message'=>'Item and logs deleted']);
    } catch (Exception $e) {
        $db->rollback();
        echo json_encode(['status'=>500,'message'=>'Delete failed: '.$e->getMessage()]);
    }
    exit;
}

/* ============================
   EXISTING endpoints (add_entry, use_bazar, delete_entry, fetch_all_entries, fetch_history, reports)
   ============================ */

/* --- add_entry (bulk add) --- */
if ($s === 'add_entry' || $s === 'bulk_add_bazar') {
    $bazarIds   = $_POST['bazar_id'] ?? [];
    $quantities = $_POST['quantity'] ?? [];
    $prices     = $_POST['unit_price'] ?? $_POST['price'] ?? [];
    $user_id    = (int) ($_POST['user_id'] ?? 0);
    $entry_date_raw = trim($_POST['entry_date'] ?? '');
    if (!is_array($bazarIds) || empty($bazarIds)) { echo json_encode(['status'=>400,'message'=>'Invalid input']); exit; }

    $time_of_day = date('H:i:s');
    if ($entry_date_raw !== '') {
        $candidate = $entry_date_raw . ' ' . $time_of_day;
        $entry_ts = strtotime($candidate) ?: time();
    } else $entry_ts = time();

    $db->startTransaction();
    $errors = []; $success = 0; $affected = [];
    foreach ($bazarIds as $i => $bRaw) {
        $b_id = (int)$bRaw;
        if ($b_id < 1) { $errors[] = "Row ".($i+1)." invalid item id"; continue; }
        $qty = (isset($quantities[$i]) && is_numeric($quantities[$i])) ? (float)$quantities[$i] : null;
        $price = (isset($prices[$i]) && is_numeric($prices[$i])) ? (float)$prices[$i] : null;
        if ($qty === null || $qty <= 0) { $errors[] = "Row ".($i+1)." invalid quantity"; continue; }

        $item = $db->where('id',$b_id)->getOne(T_BAZAR_ITEMS);
        if (!$item) { $errors[] = "Row ".($i+1)." item not found"; continue; }

        $ins = [
            'bazar_id' => $b_id,
            'type' => 'add',
            'quantity' => round($qty,3),
            'unit_price' => $price,
            'user_id' => $user_id,
            'date_ts' => $entry_ts,
            'is_hidden' => 0,
            'created_at' => time()
        ];
        $db->insert(T_BAZAR_LOGS, $ins);
        if (function_exists('logActivity')) logActivity('bazar','add', "Added {$qty} to {$item->name} by user {$user_id}");
        $success++; $affected[] = $b_id;
    }

    $affected = array_unique($affected);
    foreach ($affected as $aid) recalc_item_balance($aid);

    $db->commit();
    echo json_encode(['status' => ($success?200:400), 'message' => ($success?($success.' rows added'):'No rows processed') . (!empty($errors) ? ' Errors: '.implode('; ',$errors) : '')]);
    exit;
}

/* --- use_bazar / consume_entry --- */
if ($s === 'use_bazar' || $s === 'consume_entry') {
    $bazarIds = $_POST['bazar_id'] ?? [];
    $qtys     = $_POST['quantity'] ?? [];
    $currs    = $_POST['current_balance'] ?? [];
    $user_id  = (int) ($_POST['user_id'] ?? 0);
    $entry_date_raw = trim($_POST['entry_date'] ?? '');
    if (!is_array($bazarIds) || empty($bazarIds)) { echo json_encode(['status'=>400,'message'=>'Invalid input']); exit; }

    $time_of_day = date('H:i:s');
    if ($entry_date_raw !== '') {
        $candidate = $entry_date_raw . ' ' . $time_of_day;
        $entry_ts = strtotime($candidate) ?: time();
    } else $entry_ts = time();

    $db->startTransaction();
    $errors = []; $success = 0; $affected = [];

    foreach ($bazarIds as $i => $bRaw) {
        $b_id = (int)$bRaw;
        if ($b_id < 1) { $errors[] = "Row ".($i+1)." invalid item id"; continue; }

        $givenCurr = (isset($currs[$i]) && is_numeric($currs[$i])) ? (float)$currs[$i] : null;
        $givenUse  = (isset($qtys[$i]) && is_numeric($qtys[$i])) ? (float)$qtys[$i] : null;

        $item = $db->where('id',$b_id)->getOne(T_BAZAR_ITEMS);
        if (!$item) { $errors[] = "Row ".($i+1)." item not found"; continue; }

        $opening = get_opening_balance($b_id, $entry_ts, true);
        $use_amount = 0.0;

        if ($givenCurr !== null) {
            if ($givenCurr > $opening) { $errors[] = "Row ".($i+1)." current_balance greater than opening; use Add"; continue; }
            $use_amount = round($opening - $givenCurr, 3);
            if ($use_amount <= 0) { $errors[] = "Row ".($i+1)." no consumption detected"; continue; }
        } else {
            if ($givenUse === null || $givenUse <= 0) { $errors[] = "Row ".($i+1)." invalid quantity"; continue; }
            $use_amount = round($givenUse, 3);
            if ($use_amount > $opening) { $errors[] = "Row ".($i+1)." insufficient stock: opening {$opening}"; continue; }
        }

        $insUsage = [
            'bazar_id' => $b_id,
            'type' => 'use',
            'quantity' => $use_amount,
            'unit_price' => null,
            'user_id' => $user_id,
            'date_ts' => $entry_ts,
            'is_hidden' => 0,
            'created_at' => time()
        ];
        $db->insert(T_BAZAR_LOGS, $insUsage);
        if (function_exists('logActivity')) logActivity('bazar','use', "Used {$use_amount} of {$item->name} by user {$user_id}");
        $success++; $affected[] = $b_id;
    }

    $affected = array_unique($affected);
    foreach ($affected as $aid) recalc_item_balance($aid);

    $db->commit();
    echo json_encode(['status' => ($success?200:400), 'message' => ($success?($success.' items processed'):'No items processed') . (!empty($errors) ? ' Errors: '.implode('; ',$errors) : '')]);
    exit;
}

/* --- delete_entry (log hard-delete) --- */
if ($s === 'delete_entry') {
    if ( !( (function_exists('Wo_IsAdmin') && Wo_IsAdmin()) || (function_exists('Wo_IsModerator') && Wo_IsModerator()) || (function_exists('check_permission') && check_permission('manage-bazar')) ) ) {
        echo json_encode(['status' => 403, 'message' => 'Permission denied']);
        exit;
    }

    $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
    if (!$id) { echo json_encode(['status'=>400,'message'=>'Missing id']); exit; }

    $db->startTransaction();
    try {
        $row = $db->where('id', $id)->getOne(T_BAZAR_LOGS);
        if (!$row) { $db->rollback(); echo json_encode(['status'=>404,'message'=>'Entry not found']); exit; }
        $bazar_id = (int) row_value($row, 'bazar_id', 0);
        $db->where('id', $id)->delete(T_BAZAR_LOGS);
        recalc_item_balance($bazar_id);
        $db->commit();
        echo json_encode(['status'=>200,'message'=>'Entry deleted and calculations updated']);
    } catch (Exception $e) {
        $db->rollback();
        echo json_encode(['status'=>500,'message'=>'Failed: '.$e->getMessage()]);
    }
    exit;
}

/* --- fetch_all_entries (DataTables) --- */
if ($s === 'fetch_all_entries') {
    $start  = isset($_POST['start']) ? (int)$_POST['start'] : 0;
    $length = isset($_POST['length']) ? (int)$_POST['length'] : 10;
    $draw   = isset($_POST['draw']) ? (int)$_POST['draw'] : 1;
    $data_start = $_POST['data_start'] ?? date('Y-m-d');
    $data_end   = $_POST['data_end'] ?? date('Y-m-d');

    $startTs = strtotime($data_start . ' 00:00:00');
    $endTs   = strtotime($data_end . ' 23:59:59');

    $rows = $db->rawQuery("SELECT * FROM " . T_BAZAR_LOGS . " WHERE date_ts BETWEEN ? AND ? ORDER BY date_ts DESC", [$startTs, $endTs]);

    $output = [];
    $running = [];
    $itemCache = [];
    // ensure chronological order for running balance computation
    usort($rows, function($a,$b){ return row_value($a,'date_ts',0) <=> row_value($b,'date_ts',0); });

    foreach ($rows as $r) {
        $id_item = (int) row_value($r, 'bazar_id', 0);
        if (!isset($itemCache[$id_item])) {
            $it = $db->where('id',$id_item)->getOne(T_BAZAR_ITEMS, ['name','unit','icon']);
            if ($it) $itemCache[$id_item] = $it;
            else $itemCache[$id_item] = (object)['name'=>"Item #{$id_item}", 'unit'=>'', 'icon'=>''];
        }
        if (!isset($running[$id_item])) {
            $running[$id_item] = get_opening_balance($id_item, row_value($r,'date_ts', time()), true);
        }
        $type = row_value($r,'type','');
        if ($type === 'add') {
            $running[$id_item] += (float) row_value($r,'quantity',0.0);
            $typeText = 'Add';
        } else {
            $running[$id_item] -= (float) row_value($r,'quantity',0.0);
            $typeText = 'Consume';
        }
        if ($running[$id_item] < 0) $running[$id_item] = 0.0;
        $it = $itemCache[$id_item];

        $output[] = [
            'id' => (int) row_value($r,'id',0),
            'type' => $typeText,
            'date' => date('d-m-Y', (int) row_value($r,'date_ts', time())),
            'item_name' => '<i class="'.htmlspecialchars(row_value($it,'icon','')).'"></i> '.htmlspecialchars(row_value($it,'name','')),
            'quantity' => number_format((float)row_value($r,'quantity',0.0),2).' '.htmlspecialchars(row_value($it,'unit','')),
            'remaining' => number_format($running[$id_item],2).' '.htmlspecialchars(row_value($it,'unit','')),
            'user_name' => row_value($r,'user_id',0) ? 'User#'.row_value($r,'user_id',0) : '',
            'actions' => has_manage_bazar_permission() ? '<button class="btn btn-sm btn-danger delete-row" data-id="'.(int)row_value($r,'id',0).'">Delete</button>' : ''
        ];
    }

    $output = array_reverse($output);
    $paged = array_slice($output, $start, $length);
    echo json_encode(['draw'=>$draw,'recordsTotal'=>count($output),'recordsFiltered'=>count($output),'data'=>$paged]);
    exit;
}

/* --- fetch_history (price & daily) --- */
if ($s === 'fetch_history') {
    $bazar_id = (int) ($_POST['bazar_id'] ?? 0);
    if (!$bazar_id) { echo json_encode(['status'=>400,'message'=>'Invalid Bazar ID']); exit; }
    $start_date = $_POST['data_start'] ?? null; $end_date = $_POST['data_end'] ?? null;
    $startTs = $start_date ? strtotime($start_date . ' 00:00:00') : strtotime('-30 days');
    $endTs = $end_date ? strtotime($end_date . ' 23:59:59') : time();

    $priceHistory = $db->rawQuery("SELECT date_ts AS date, unit_price AS price FROM " . T_BAZAR_LOGS . " WHERE bazar_id = ? AND unit_price IS NOT NULL AND date_ts BETWEEN ? AND ? ORDER BY date_ts ASC", [$bazar_id, $startTs, $endTs]);
    $daily = $db->rawQuery("SELECT FROM_UNIXTIME(date_ts,'%Y-%m-%d') AS d, SUM(CASE WHEN type='add' THEN quantity ELSE 0 END) AS added, SUM(CASE WHEN type='use' THEN quantity ELSE 0 END) AS used FROM " . T_BAZAR_LOGS . " WHERE bazar_id = ? AND date_ts BETWEEN ? AND ? GROUP BY d ORDER BY d ASC", [$bazar_id, $startTs, $endTs]);

    echo json_encode(['status'=>200,'priceHistory'=>$priceHistory,'daily'=>$daily]);
    exit;
}

/* --- report_monthly / report --- */
if ($s === 'report_monthly' || $s === 'report') {
    $month_year = trim($_POST['month_year'] ?? $_GET['month_year'] ?? '');
    $data_start = trim($_POST['data_start'] ?? $_GET['data_start'] ?? '');
    $data_end   = trim($_POST['data_end'] ?? $_GET['data_end'] ?? '');
    if ($month_year) {
        if (!preg_match('/^\d{4}-\d{2}$/', $month_year)) { echo json_encode(['status'=>400,'message'=>'Invalid month']); exit; }
        $startTs = strtotime($month_year.'-01 00:00:00');
        $endTs = strtotime(date('Y-m-t 23:59:59', $startTs));
    } elseif ($data_start && $data_end) {
        $startTs = strtotime($data_start . ' 00:00:00'); $endTs = strtotime($data_end . ' 23:59:59');
    } else { echo json_encode(['status'=>400,'message'=>'Provide month_year or date range']); exit; }

    $items = $db->get(T_BAZAR_ITEMS, null, ['id','name','unit','price']);
    $rows = []; $grandTotal = 0.0;
    foreach ($items as $it) {
        $id = (int) $it->id;
        $opening = get_opening_balance($id, $startTs, true);
        $q = $db->rawQueryOne("SELECT COALESCE(SUM(quantity),0) AS s FROM " . T_BAZAR_LOGS . " WHERE bazar_id = ? AND type = 'add' AND date_ts BETWEEN ? AND ?", [$id,$startTs,$endTs]);
        $added = (float) row_value($q,'s',0.0);
        $q2 = $db->rawQueryOne("SELECT COALESCE(SUM(quantity),0) AS s FROM " . T_BAZAR_LOGS . " WHERE bazar_id = ? AND type = 'use' AND date_ts BETWEEN ? AND ?", [$id,$startTs,$endTs]);
        $consumed = (float) row_value($q2,'s',0.0);
        $closing = $opening + $added - $consumed; if ($closing < 0) $closing = 0.0;
        $price = (float) ($it->price ?? 0.0);
        if (!$price) {
            $lastPrice = $db->rawQueryOne("SELECT unit_price FROM " . T_BAZAR_LOGS . " WHERE bazar_id = ? AND unit_price IS NOT NULL ORDER BY date_ts DESC LIMIT 1", [$id]);
            $p = row_value($lastPrice,'unit_price', 0.0);
            if ($p) $price = (float)$p;
        }
        $total_price = $consumed * $price;
        $rows[] = [
            'item_id' => $id,
            'item_name' => $it->name,
            'unit' => $it->unit,
            'opening' => round($opening,3),
            'added' => round($added,3),
            'consumed' => round($consumed,3),
            'closing' => round($closing,3),
            'price' => number_format($price,2),
            'total_price' => number_format($total_price,2)
        ];
        $grandTotal += $total_price;
    }
    echo json_encode(['status'=>200,'period'=>date('d-m-Y',$startTs).' to '.date('d-m-Y',$endTs),'rows'=>$rows,'grand_total'=>number_format($grandTotal,2)]);
    exit;
}

if ($s === 'report_weekly') {
    $monthYear = $_POST['month_year'] ?? date('Y-m');
    [$year, $month] = explode('-', $monthYear);
    $year = intval($year);
    $month = intval($month);

    $startOfMonth = strtotime(sprintf('%04d-%02d-01 00:00:00', $year, $month));
    $endOfMonth = strtotime(date('Y-m-t 23:59:59', $startOfMonth));

    $weeks = [];
    $cursor = $startOfMonth;
    while ($cursor <= $endOfMonth) {
        $ws = $cursor;
        $we = min(strtotime('+6 days', $ws), $endOfMonth);
        $weeks[] = ['start'=>$ws,'end'=>$we];
        $cursor = strtotime('+7 days', $ws);
    }

    $items = $db->get(T_BAZAR_ITEMS, null, ['id','name','unit']);
    $out = [];

    foreach ($items as $it) {
        $id = (int)$it->id;
        $itemRow = ['item_id'=>$id,'item_name'=>$it->name,'unit'=>$it->unit,'weeks'=>[]];

        foreach ($weeks as $idx=>$w) {
            $opening = get_opening_balance($id, $w['start'], true);

            $added = (float) row_value($db->rawQueryOne(
                "SELECT COALESCE(SUM(quantity),0) AS s FROM " . T_BAZAR_LOGS . " WHERE bazar_id = ? AND type = 'add' AND date_ts BETWEEN ? AND ?",
                [$id,$w['start'],$w['end']]
            ), 's', 0.0);

            $consumed = (float) row_value($db->rawQueryOne(
                "SELECT COALESCE(SUM(quantity),0) AS s FROM " . T_BAZAR_LOGS . " WHERE bazar_id = ? AND type = 'use' AND date_ts BETWEEN ? AND ?",
                [$id,$w['start'],$w['end']]
            ), 's', 0.0);

            $closing = max(0.0, $opening + $added - $consumed);

            $priceRow = $db->rawQueryOne(
                "SELECT unit_price FROM " . T_BAZAR_LOGS . " WHERE bazar_id = ? AND unit_price IS NOT NULL AND date_ts BETWEEN ? AND ? ORDER BY date_ts DESC LIMIT 1",
                [$id,$w['start'],$w['end']]
            );
            if (!$priceRow) {
                $priceRow = $db->rawQueryOne(
                    "SELECT unit_price FROM " . T_BAZAR_LOGS . " WHERE bazar_id = ? AND unit_price IS NOT NULL ORDER BY date_ts DESC LIMIT 1",
                    [$id]
                );
            }
            $price = (float) row_value($priceRow,'unit_price',0.0);
            $totalPrice = $consumed * $price;

            $itemRow['weeks'][] = [
                'week_index'=>$idx+1,
                'start'=>date('d-m-Y',$w['start']),
                'end'=>date('d-m-Y',$w['end']),
                'opening'=>round($opening,3),
                'added'=>round($added,3),
                'consumed'=>round($consumed,3),
                'closing'=>round($closing,3),
                'price'=>$price,
                'total_price'=>$totalPrice
            ];
        }

        $out[] = $itemRow;
    }

    echo json_encode([
        'status'=>200,
        'year'=>$year,
        'month'=>$month,
        'weeks'=>count($weeks),
        'data'=>$out
    ]);
    exit;
}

/* report_item (day-by-day) */
/*
 Updated handler for report_item / report_itemwise
 - ensures numeric calculations are done with raw numbers
 - returns both formatted display strings and raw numeric values
 - follows project conventions (global $db; use $db->rawQueryOne)
*/

// --- report_item (day-by-day)
// --- report_item (day-by-day) — Option A (simple)
// --- report_item (day-by-day) — corrected & safe (avoid IS NOT NULL 3-arg)
if ($s === 'report_item' || $s === 'report_itemwise') {
    global $db;

    $bazar_id   = (int) ($_POST['bazar_id'] ?? 0);
    $data_start = $_POST['data_start'] ?? null;
    $data_end   = $_POST['data_end'] ?? null;

    if (!$bazar_id || !$data_start || !$data_end) {
        echo json_encode(['status' => 400, 'message' => 'Missing params']);
        exit;
    }

    // normalize input dates (expecting YYYY-MM-DD)
    $startTs = strtotime($data_start . ' 00:00:00');
    $endTs   = strtotime($data_end . ' 23:59:59');
    if ($startTs === false || $endTs === false) {
        echo json_encode(['status' => 400, 'message' => 'Invalid date format']);
        exit;
    }

    // get item info (unit, name) once
    $itemRow = $db->where('id', $bazar_id)->getOne(T_BAZAR_ITEMS, ['id','name','unit','price']);
    $defaultUnit = '';
    if ($itemRow) {
        if (is_array($itemRow)) $defaultUnit = $itemRow['unit'] ?? '';
        else $defaultUnit = $itemRow->unit ?? '';
    }

    // fetch all logs for the range (single query)
    $db->where('bazar_id', $bazar_id);
    $db->where('date_ts', [$startTs, $endTs], 'between');
    $logs = $db->get(T_BAZAR_LOGS, null, ['date_ts','quantity','type','unit_price']);

    // aggregate logs by day
    $perDay = [];
    foreach ($logs as $log) {
        $ts = is_array($log) ? (int)$log['date_ts'] : (int)$log->date_ts;
        $dayKey = date('Y-m-d', $ts);
        if (!isset($perDay[$dayKey])) {
            $perDay[$dayKey] = [
                'added' => 0.0,
                'consumed' => 0.0,
                'last_price' => null
            ];
        }

        $qty = is_array($log) ? (float)($log['quantity'] ?? 0) : (float)($log->quantity ?? 0);
        $type = is_array($log) ? ($log['type'] ?? '') : ($log->type ?? '');
        if ($type === 'add') $perDay[$dayKey]['added'] += $qty;
        elseif ($type === 'use') $perDay[$dayKey]['consumed'] += $qty;

        $up = is_array($log) ? ($log['unit_price'] ?? null) : ($log->unit_price ?? null);
        if ($up !== null && $up !== '') {
            // overwrite so we keep the latest price within the day (logs are not ordered here)
            $perDay[$dayKey]['last_price'] = (float)$up;
        }
    }

    // fallback: last known unit_price overall (single safe raw where)
    $db->where('bazar_id', $bazar_id);
    // use a raw where string instead of 3-arg null operator to avoid SQL builder issues
    $db->where('unit_price IS NOT NULL');
    $db->orderBy('date_ts', 'DESC');
    $fallbackPriceRow = $db->getOne(T_BAZAR_LOGS, 'unit_price');
    $fallbackPrice = 0.0;
    if ($fallbackPriceRow) {
        if (is_array($fallbackPriceRow)) $fallbackPrice = (float)($fallbackPriceRow['unit_price'] ?? 0.0);
        else $fallbackPrice = (float)($fallbackPriceRow->unit_price ?? 0.0);
    }

    // build rows day-by-day using get_opening_balance and the aggregates
    $rows = [];
    $cursor = $startTs;
    while ($cursor <= $endTs) {
        $dS = strtotime(date('Y-m-d 00:00:00', $cursor));
        $dayKey = date('Y-m-d', $dS);

        $opening_raw = get_opening_balance($bazar_id, $dS, true);

        $added_raw = $perDay[$dayKey]['added'] ?? 0.0;
        $consumed_raw = $perDay[$dayKey]['consumed'] ?? 0.0;
        $closing_raw = $opening_raw + $added_raw - $consumed_raw;
        if ($closing_raw < 0) $closing_raw = 0.0;

        // price: prefer day's last_price, otherwise fallback
        $price_raw = $perDay[$dayKey]['last_price'] ?? null;
        if ($price_raw === null) $price_raw = $fallbackPrice;

        $total_price_raw = $price_raw * $consumed_raw;

        $rows[] = [
            'date'               => date('d-m-Y', $dS),
            'opening'            => round((float)$opening_raw, 2) . ' ' . $defaultUnit,
            'added'              => round((float)$added_raw, 2) . ' ' . $defaultUnit,
            'consumed'           => round((float)$consumed_raw, 2) . ' ' . $defaultUnit,
            'closing'            => round((float)$closing_raw, 2) . ' ' . $defaultUnit,
            'price_value'        => $price_raw,
            'total_price_value'  => $total_price_raw,
            'price'              => number_format($price_raw, 2, '.', ''),
            'total_price'        => number_format($total_price_raw, 2, '.', ''),
            // use item unit (T_BAZAR_ITEMS) — logs don't contain unit
            'unit'               => $defaultUnit
        ];

        $cursor = strtotime('+1 day', $cursor);
    }

    echo json_encode(['status' => 200, 'item_id' => $bazar_id, 'rows' => $rows]);
    exit;
}




/* --- fetch_items (simple list) --- */
if ($s === 'fetch_items') {
    $items = $db->orderBy('name','ASC')->get(T_BAZAR_ITEMS, null, ['id','name','unit','icon','quantity','price','low_threshold','updated_at']);
    $out = [];
    foreach ($items as $it) {
        $out[] = [
            'id' => (int)$it->id,
            'name' => '<i class="' . $it->icon . '"></i> ' . $it->name,
            'unit' => $it->unit,
            'icon' => $it->icon,
            'quantity' => number_format((float)$it->quantity,2),
            'price' => number_format((float)$it->price,2),
            'low_threshold' => (int)$it->low_threshold,
            'updated_at' => $it->updated_at ? date('d-m-Y H:i', (int)$it->updated_at) : ''
        ];
    }
    echo json_encode(['status'=>200,'items'=>$out]);
    exit;
}

/* --- fetch single item --- */
if ($s === 'fetch_item') {
    $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
    if (!$id) { echo json_encode(['status'=>400,'message'=>'Missing id']); exit; }
    $it = $db->where('id',$id)->getOne(T_BAZAR_ITEMS);
    if (!$it) { echo json_encode(['status'=>404,'message'=>'Item not found']); exit; }
    echo json_encode(['status'=>200,'item'=>[
        'id'=>$it->id,'name'=>$it->name,'unit'=>$it->unit,'icon'=>$it->icon,'quantity'=>number_format((float)$it->quantity,2),'price'=>number_format((float)$it->price,2),'low_threshold'=> (int)$it->low_threshold,'updated_at'=>$it->updated_at
    ]]);
    exit;
}

/* --- last_bazar (DataTables list for "Last Bazar" page) --- */
/* Request params (POST):
    - draw, start, length (DataTables)
    - item_id (optional): integer or 0 => All
*/
if ($s === 'last_bazar') {
    $draw   = isset($_POST['draw']) ? (int)$_POST['draw'] : 1;
    $start  = isset($_POST['start']) ? (int)$_POST['start'] : 0;
    $length = isset($_POST['length']) ? (int)$_POST['length'] : 25;
    $item_id = isset($_POST['item_id']) ? (int)$_POST['item_id'] : 0;

    // Build items list (filtered by item_id if provided)
    if ($item_id > 0) {
        $items = $db->where('id', $item_id)->orderBy('name','ASC')->get(T_BAZAR_ITEMS, null, ['id','name','unit','icon','quantity','price','updated_at']);
    } else {
        $items = $db->orderBy('name','ASC')->get(T_BAZAR_ITEMS, null, ['id','name','unit','icon','quantity','price','updated_at']);
    }

    $rows = [];
    foreach ($items as $it) {
        $id = (int) row_value($it, 'id', 0);
        $name = row_value($it, 'name', '');
        $unit = row_value($it, 'unit', '');
        $current_balance = (float) row_value($it, 'quantity', 0.0);

        // find last bazar purchase (latest log with unit_price NOT NULL) for this item
        $lastPriceRow = $db->rawQueryOne(
            "SELECT unit_price, date_ts, quantity FROM " . T_BAZAR_LOGS . " WHERE bazar_id = ? AND unit_price IS NOT NULL ORDER BY date_ts DESC LIMIT 1",
            [$id]
        );

        $last_date_ts = (int) row_value($lastPriceRow, 'date_ts', 0);
        $last_date = $last_date_ts ? date('d-m-Y', $last_date_ts) : '';
        $tk_per_unit = (float) row_value($lastPriceRow, 'unit_price', 0.0);
        $last_qty = (float) row_value($lastPriceRow, 'quantity', 0.0);

        // total = tk_per_unit * last_qty  (total price of that last bazar line)
        $total = $tk_per_unit * $last_qty;

        $rows[] = [
            'id' => $id,
            'item_name' => $name,
            'last_date' => $last_date,
            'tk_per_unit' => $tk_per_unit,           // numeric (for excel)
            'last_qty' => $last_qty,                 // numeric
            'total' => $total,                       // numeric
            'current_balance' => $current_balance,   // numeric
            'unit' => $unit
        ];
    }

    // DataTables response (client-side paging)
    $recordsTotal = count($rows);
    $paged = array_slice($rows, $start, $length);

    // Add serial numbers (sl) per page start and prepare final data for DataTables
    $data_out = [];
    $sl = $start + 1;
    foreach ($paged as $r) {
        $data_out[] = [
            'sl' => $sl++,
            'item' => $r['item_name'],
            'last_bazar_date' => $r['last_date'],
            // keep numeric fields as numbers so excel gets numeric cells
            'last_qty' => $r['last_qty'],
            'tk_per_unit' => $r['tk_per_unit'],
            'total' => $r['total'],
            // current_balance shown with unit in display, but is numeric underneath
            'current_balance' => $r['current_balance'],
            'unit' => $r['unit']
        ];
    }

    echo json_encode([
        'draw' => $draw,
        'recordsTotal' => $recordsTotal,
        'recordsFiltered' => $recordsTotal,
        'data' => $data_out
    ]);
    exit;
}


echo json_encode(['status'=>400,'message'=>'Unknown request']);
exit;

<?php
    // ------------------ EDIT INVENTORY ------------------
    if ($s === 'edit_inventory') {
        $id        = $_POST['id']     ?? null;
        $project   = $_POST['project'] ?? null;
        $block     = isset($_POST['block']) ? strtolower(trim($_POST['block'])) : null;
        $facing    = isset($_POST['facing']) ? strtolower(trim($_POST['facing'])) : null;
        $katha     = isset($_POST['katha']) ? normalizeKatha($_POST['katha']) : null;
        $road      = $_POST['road']      ?? null;
        $plot_num  = $_POST['plot_num']  ?? null;
    
        if (empty($id)) {
            echo json_encode(['status' => 400, 'message' => 'Invalid booking ID.']); exit;
        }
    
        $booking = $db->where('id', $id)->getOne(T_BOOKING);
        if (!$booking) {
            echo json_encode(['status' => 404, 'message' => 'Booking not found.']); exit;
        }
    
        $updateData = [];
        $logChanges = [];
    
        // --- Check each field ---
        if (!is_null($project) && $project != $booking->project) {
            $updateData['project'] = $project;
            $logChanges[] = "project changed from '{$booking->project}' to '{$project}'";
        }
        if (!is_null($block) && $block != $booking->block) {
            $updateData['block'] = $block;
            $logChanges[] = "block changed from '{$booking->block}' to '{$block}'";
        }
        if (!empty($facing) && $facing != $booking->facing) {
            $updateData['facing'] = $facing;
            $logChanges[] = "facing changed from '{$booking->facing}' to '{$facing}'";
        }
        if (!is_null($katha) && $katha != $booking->katha) {
            $updateData['katha'] = $katha;
            $logChanges[] = "katha changed from '{$booking->katha}' to '{$katha}'";
        }
        if (!is_null($road) && $road != $booking->road) {
            $updateData['road'] = $road;
            $logChanges[] = "road changed from '{$booking->road}' to '{$road}'";
        }
        if (!is_null($plot_num) && $plot_num != $booking->plot) {
            $updateData['plot'] = $plot_num; // assuming DB column = plot
            $logChanges[] = "plot changed from '{$booking->plot}' to '{$plot_num}'";
        }
    
        if (empty($updateData)) {
            echo json_encode(['status' => 400, 'message' => 'Nothing to update.']); exit;
        }
    
        // --- Check duplicate ---
        $db->where('id', $id, '!=')
           ->where('project', $updateData['project'] ?? $booking->project)
           ->where('katha', $updateData['katha'] ?? $booking->katha)
           ->where('plot', $updateData['plot'] ?? $booking->plot)
           ->where('road', $updateData['road'] ?? $booking->road);
    
        if (array_key_exists('block', $updateData)) {
            $db->where('block', $updateData['block']);
        } else {
            $db->where('block', $booking->block);
        }
        if (array_key_exists('facing', $updateData)) {
            $db->where('facing', $updateData['facing']);
        } else {
            $db->where('facing', $booking->facing);
        }
    
        $exist = $db->getOne(T_BOOKING);
        if ($exist) {
            echo json_encode([
                'status' => 400,
                'message' => 'Another booking with the same project, block, plot, road, katha & facing already exists!'
            ]); exit;
        }
    
        // --- Perform update ---
        $update = $db->where('id', $id)->update(T_BOOKING, $updateData);
    
        if ($update) {
            // --- Logging ---
            $logUser    = 'User #' . $wo['user']['id']; // adjust to your user system
            $logDate    = date('Y-m-d H:i:s');
            $logDetails = "Booking ID #{$id} ({$booking->project}, Plot {$booking->plot}, Katha {$booking->katha})";
            $logMessage = implode('; ', $logChanges);
            logActivity('booking', 'update', "{$logUser} updated {$logDetails}: {$logMessage}");
    
            echo json_encode(['status' => 200, 'message' => 'Booking updated successfully!']);
        } else {
            echo json_encode(['status' => 500, 'message' => 'Failed to update booking.']);
        }
        exit;
    }

    // ------------------ SUBMIT NEW BOOKING ------------------
    if ($s == 'submit') {
        $project   = isset($_POST['project']) ? strtolower(trim($_POST['project'])) : '';
        $block     = isset($_POST['block']) ? strtolower(trim($_POST['block'])) : '';
        $katha     = isset($_POST['katha']) ? normalizeKatha($_POST['katha']) : '';
        $plot_num  = isset($_POST['plot_num']) ? trim($_POST['plot_num']) : '';
        $facing    = isset($_POST['facing']) ? strtolower(trim($_POST['facing'])) : '';
        $road      = isset($_POST['road']) ? trim($_POST['road']) : '';
        $file_num  = isset($_POST['file_num']) ? strtolower(trim($_POST['file_num'])) : null;

        if ($project == 'moon-hill') {
            if (empty($project) || empty($katha) || empty($plot_num) || empty($facing) || empty($road)) {
                $data = ['status'=>400,'message'=>'All fields except file number are required!'];
            }
            $is_exist = $db->where('project', $project)
                           ->where('katha', $katha)
                           ->where('plot', $plot_num)
                           ->where('facing', $facing)
                           ->where('road', $road)
                           ->getOne(T_BOOKING);
        } else {
            if (empty($project) || empty($katha) || empty($plot_num) || empty($facing) || empty($road)) {
                $data = ['status'=>400,'message'=>'All fields except file number are required!'];
            }
            $is_exist = $db->where('project', $project)
                           ->where('katha', $katha)
                           ->where('plot', $plot_num)
                           ->where('facing', $facing)
                           ->where('road', $road)
                           ->getOne(T_BOOKING);
        }

        if ($is_exist) {
            $data = ['status'=>400,'message'=>'Entry already exists!'];
        } else {
            $data_array = ['project'=>$project,'katha'=>$katha,'plot'=>$plot_num,'facing'=>$facing,'road'=>$road];
            if ($project != 'moon-hill') $data_array['block']=$block;
            if (!empty($file_num)) $data_array['file_num']=$file_num;

            $insert = $db->insert(T_BOOKING,$data_array);
            if ($insert) {
                $data = ['status'=>200,'message'=>'Added successfully!'];
                // Logging
                $logUser    = 'User #' . $wo['user']['id'];
                $logDate    = date('Y-m-d H:i:s');
                $logDetails = "Booking ID #{$insert} ({$project}, Plot {$plot_num}, Katha {$katha})";
                logActivity('booking', 'create', "{$logUser} added new booking {$logDetails}");
            } else {
                $data = ['status'=>400,'message'=>'Something went wrong!'];
            }
        }
    }

    // ------------------ EDIT MODAL ------------------
    if ($s == 'edit_modal') {
        $id = isset($_POST['id']) ? $_POST['id'] : '';
        if (empty($id)) {
            $data = ['status'=>400,'message'=>'Something went wrong!'];
        } else {
            $inventory = $db->where('id', $id)->getOne(T_BOOKING);
            $data = ['status'=>200,'result'=>Wo_LoadManagePage('inventory/edit')];
        }
    }

    // ------------------ UPDATE STATUS ------------------
    if ($s === 'update_status') {
        $id       = !empty($_POST['id']) ? $_POST['id'] : null;
        $file_id  = !empty($_POST['file_id']) ? $_POST['file_id'] : null;
        $file_id2 = !empty($_POST['file_id2']) ? $_POST['file_id2'] : null;
        $status   = isset($_POST['status']) ? $_POST['status'] : '0';
        $date     = !empty($_POST['date']) ? $_POST['date'] : '';

        if (empty($id)) { echo json_encode(['status'=>400,'message'=>'Invalid booking ID.']); exit; }
        if (empty($file_id) && empty($file_id2)) { echo json_encode(['status'=>400,'message'=>'Client/File ID is required!']); exit; }
        if (empty($file_id)) $file_id=$file_id2;
        $timestamp = ($date && strtotime($date)!==false) ? strtotime($date) : time();

        $is_exist = $db->where('booking_id',$id)->where('file_num',$file_id)->getOne(T_BOOKING_HELPER);
        $updateData = ['status'=>$status,'time'=>$timestamp];

        if ($is_exist) {
            $update = $db->where('booking_id',$id)->where('file_num',$file_id)->update(T_BOOKING_HELPER,$updateData);
            $data = $update ? ['status'=>200,'message'=>'Record updated successfully!'] : ['status'=>500,'message'=>'Failed to update record!'];
        } else {
            $lastEntry = $db->where('booking_id',$id)->orderBy('time','DESC')->getOne(T_BOOKING_HELPER);
            if ($lastEntry) {
                $db->where('booking_id',$id)->where('id',$lastEntry->id,'!=')->update(T_BOOKING_HELPER,['status'=>4]);
                $db->where('id',$lastEntry->id)->update(T_BOOKING_HELPER,['status'=>4,'time'=>$timestamp]);
            }
            $insertData = ['booking_id'=>$id,'status'=>$status,'time'=>$timestamp,'file_num'=>$file_id];
            $insert = $db->insert(T_BOOKING_HELPER,$insertData);
            $data = $insert ? ['status'=>200,'message'=>'Record inserted successfully!'] : ['status'=>500,'message'=>'Failed to insert record!'];
        }
        if ($data['status']===200) $db->where('id',$id)->update(T_BOOKING,['status'=>$status,'file_num'=>$file_id]);
    }

    // ------------------ APPLY HOLD ------------------
    if ($s === 'apply_hold') {
        $id = $_POST['id'] ?? null;
        $hold_end_date = $_POST['hold_end_date'] ?? null;
        $hold_auto_release = $_POST['hold_auto_release'] ?? 0;

        if (empty($id) || empty($hold_end_date)) {
            echo json_encode(['status' => 400, 'message' => 'Invalid parameters.']); exit;
        }

        $booking = $db->where('id', $id)->getOne(T_BOOKING);
        if (!$booking) {
            echo json_encode(['status' => 404, 'message' => 'Booking not found.']); exit;
        }

        // Only allow hold if status is Available (1) or already on hold (maybe we want to update hold)
        // Assuming status 1 is Available.
        // We can also introduce a new status '5' for Hold if desired, or just use the date fields.
        // Let's keep status as 1 (Available) but set the hold fields.
        
        $updateData = [
            'hold_end_date' => $hold_end_date,
            'hold_auto_release' => $hold_auto_release
        ];

        if ($db->where('id', $id)->update(T_BOOKING, $updateData)) {
            // Log
            logActivity('inventory', 'hold', "Applied hold on Plot {$booking->plot} until {$hold_end_date}");
            echo json_encode(['status' => 200, 'message' => 'Hold applied successfully.']);
        } else {
            echo json_encode(['status' => 500, 'message' => 'Failed to apply hold.']);
        }
        exit;
    }

    // ------------------ RELEASE HOLD ------------------
    if ($s === 'release_hold') {
        $id = $_POST['id'] ?? null;
        if (empty($id)) {
            echo json_encode(['status' => 400, 'message' => 'Invalid ID.']); exit;
        }

        $updateData = [
            'hold_end_date' => null,
            'hold_auto_release' => 0
        ];

        if ($db->where('id', $id)->update(T_BOOKING, $updateData)) {
             logActivity('inventory', 'release_hold', "Released hold on inventory #{$id}");
             echo json_encode(['status' => 200, 'message' => 'Hold released.']);
        } else {
             echo json_encode(['status' => 500, 'message' => 'Failed to release hold.']);
        }
        exit;
    }

    // Helper function to normalize katha values
    function normalizeKatha($katha) {
        if (empty($katha)) return '';
        // Remove any non-numeric characters except decimal point
        $normalized = preg_replace('/[^0-9.]/', '', trim($katha));
        return $normalized;
    }

    // ------------------ FETCH DATA ------------------
    if ($s == 'fetch') {
        $page_num = isset($_POST['start']) ? $_POST['start']/$_POST['length']+1 : 1;
        $searchValue = isset($_POST['search']['value']) ? $_POST['search']['value'] : '';
        $project = isset($_POST['project']) ? $_POST['project'] : '';
        $block   = isset($_POST['block']) ? $_POST['block'] : '';
        $katha   = isset($_POST['katha']) ? normalizeKatha($_POST['katha']) : '';
        $road    = isset($_POST['road']) ? $_POST['road'] : '';
        $facing  = isset($_POST['facing']) ? $_POST['facing'] : '';
        $plot_num= isset($_POST['plot_num']) ? $_POST['plot_num'] : '';

        if (!empty($searchValue)) {
            $db->where(is_numeric($searchValue)?'file_id':'name','%'.$searchValue.'%','LIKE');
        }
        if (!empty($project)) $db->where('project',$project);
        if (!empty($block) && $block!='Select Block...') $db->where('block',$block);
        if (!empty($katha) && $katha!='Select Katha...') $db->where('katha',$katha);
        if (!empty($road) && $road!='Select Road...') $db->where('road',$road);
        if (!empty($facing) && $facing!='Select Facing...') $db->where('facing',$facing);
        if (!empty($plot_num)) $db->where('plot','%'.$plot_num.'%','LIKE');

        $orderColumn = isset($_POST['order'][0]['column']) ? $_POST['order'][0]['column'] : null;
        $orderDirection = isset($_POST['order'][0]['dir']) ? $_POST['order'][0]['dir'] : null;
        if ($orderColumn!==null && $orderColumn==3) $db->orderBy('plot',$orderDirection=='asc'?'ASC':'DESC');
        else $db->orderBy('plot','DESC');

        $db->pageLimit = $_POST['length'];
        $inventory = $db->objectbuilder()->paginate(T_BOOKING,$page_num);

        $outputData = [];
        if ($inventory) {
            foreach ($inventory as $value) {
                $client = GetCustomerById($value->file_num);

                $status_raw = $value->status;
                if ($status_raw == '1') $status = '<span class="badge bg-info"> Available </span>';
                else if ($status_raw == '2') $status = '<span class="badge bg-success"> Sold </span>';
                else if ($status_raw == '3') $status = '<span class="badge bg-success"> Complete </span>';
                else if ($status_raw == '4') $status = '<span class="badge bg-danger"> Canceled </span>';
                else $status = '<span class="badge bg-info">Available</span>';

                $facingDisplay = (strpos($value->facing,'-')!==false) ? ucwords($value->facing,'-') : ucfirst($value->facing);

                $outputData[] = [
                    'id'      => ucwords($value->id),
                    'block'   => ucwords($value->block),
                    'road'    => ucwords($value->road),
                    'plot'    => 'Plot ' . $value->plot,
                    'katha'   => $value->katha . ' katha',
                    'facing'  => $facingDisplay,
                    'status'  => $status,
                    'file_num'=> $value->file_num
                ];
            }
        }

        $data = [
            "draw" => intval($_POST['draw']),
            "recordsTotal" => $db->totalPages * $_POST['length'],
            "recordsFiltered" => $db->totalPages * $_POST['length'],
            "data" => $outputData
        ];
    }

<?php
    // Get available plots for change plot modal
    if ($s === 'get_available_plots') {
        $project_slug = isset($_GET['project_slug']) ? trim($_GET['project_slug']) : '';
        
        if (empty($project_slug)) {
            echo json_encode([]);
            exit;
        }
        
        global $db;
        
        // Get available plots (status 0 or 1, or no active helpers)
        $plots = $db->where('project', $project_slug)
                   ->where('status', ['0', '1'], 'IN')
                   ->get(T_BOOKING);
        
        $available = [];
        foreach ($plots as $plot) {
            // Check if plot has active helpers
            $active_helper = $db->where('booking_id', $plot->id)
                               ->where('status', ['0', '1', '4'], 'NOT IN')
                               ->getOne(T_BOOKING_HELPER);
            
            if (!$active_helper) {
                $available[] = [
                    'id' => $plot->id,
                    'block' => $plot->block ?? '',
                    'plot' => $plot->plot ?? '',
                    'katha' => $plot->katha ?? '',
                    'road' => $plot->road ?? '',
                    'plot_number' => $plot->plot ?? ''
                ];
            }
        }
        
        echo json_encode($available);
        exit;
    }

<?php
/**
 * Excel Schedule Generator
 * Uses project-specific templates to generate payment schedules
 * Templates: installment_schedule_hill_town.xlsx, installment_schedule_moon_hill.xlsx
 */

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Generate Excel payment schedule using project template
 * 
 * @param int $purchase_id Purchase ID
 * @return array Result with file path
 */
function generate_excel_payment_schedule($purchase_id) {
    global $db, $wo;
    
    try {
        // Get purchase details
        $purchase = $db->where('id', $purchase_id)->getOne('wo_booking_helper');
        if (!$purchase) {
            return ['success' => false, 'message' => 'Purchase not found'];
        }
        
        // Get client details
        $client = $db->where('id', $purchase->client_id)->getOne('crm_customers', ['name', 'email', 'phone']);
        
        // Get booking details
        $booking = $db->where('id', $purchase->booking_id)->getOne('wo_booking', ['plot', 'block', 'project', 'plot_price']);
        
        // Get payment schedule
        $db->where('purchase_id', $purchase_id);
        $db->where('status', 99, '!='); // Not deleted
        $db->orderBy('installment_number', 'ASC');
        $schedules = $db->get('crm_payment_schedule');
        
        if (empty($schedules)) {
            return ['success' => false, 'message' => 'No payment schedule found'];
        }
        
        // Determine template file based on project
        $theme = $wo['config']['theme'] ?? 'wowonder';
        $project_safe = isset($booking->project) ? preg_replace('/[^A-Za-z0-9_]/', '_', strtolower($booking->project)) : 'default';
        $template_path = ROOT_DIR . "/themes/{$theme}/file_template/installment_schedule_{$project_safe}.xlsx";
        
        // Fallback to default template if project-specific doesn't exist
        if (!file_exists($template_path)) {
            $template_path = ROOT_DIR . "/themes/{$theme}/file_template/installment_schedule_default.xlsx";
        }
        
        if (!file_exists($template_path)) {
            return ['success' => false, 'message' => 'Excel template not found'];
        }
        
        // Load the template
        $spreadsheet = IOFactory::load($template_path);
        $sheet = $spreadsheet->getActiveSheet();
        
        // Find the data start row (look for placeholder or first empty row after header)
        // Typically templates have headers and data starts at a specific row
        // Adjust based on your template structure
        $dataStartRow = 10; // Default, adjust based on your template
        
        // Try to find the start row automatically
        for ($row = 1; $row <= 20; $row++) {
            $cellValue = $sheet->getCell('A' . $row)->getValue();
            if (stripos($cellValue, 'installment') !== false || stripos($cellValue, '#') !== false) {
                $dataStartRow = $row + 1;
                break;
            }
        }
        
        // Populate client information in template
        // Look for placeholders like {{CLIENT_NAME}}, {{FILE_NUM}}, etc.
        $replacements = [
            '{{CLIENT_NAME}}' => $client->name ?? '',
            '{{FILE_NUM}}' => $purchase->file_num ?? '',
            '{{PLOT}}' => $booking->plot ?? '',
            '{{BLOCK}}' => $booking->block ?? '',
            '{{PROJECT}}' => $booking->project ?? '',
            '{{TOTAL_PRICE}}' => number_format($booking->plot_price ?? 0, 2),
            '{{DATE}}' => date('d M Y'),
        ];
        
        // Replace placeholders in all cells
        foreach ($sheet->getRowIterator() as $row) {
            foreach ($row->getCellIterator() as $cell) {
                $value = $cell->getValue();
                if (is_string($value)) {
                    $newValue = str_replace(array_keys($replacements), array_values($replacements), $value);
                    if ($newValue !== $value) {
                        $cell->setValue($newValue);
                    }
                }
            }
        }
        
        // Insert schedule data
        $currentRow = $dataStartRow;
        $totalAmount = 0;
        $totalPaid = 0;
        
        foreach ($schedules as $schedule) {
            $status_text = '';
            switch ($schedule->status) {
                case 1: $status_text = 'Paid'; break;
                case 2: $status_text = 'Partial'; break;
                case 3: $status_text = 'Overdue'; break;
                default: $status_text = 'Pending'; break;
            }
            
            $sheet->setCellValue('A' . $currentRow, $schedule->installment_number);
            $sheet->setCellValue('B' . $currentRow, $schedule->particular ?? '-');
            $sheet->setCellValue('C' . $currentRow, date('d M Y', strtotime($schedule->due_date)));
            $sheet->setCellValue('D' . $currentRow, $schedule->installment_amount);
            $sheet->setCellValue('E' . $currentRow, $schedule->paid_amount);
            $sheet->setCellValue('F' . $currentRow, $status_text);
            
            $totalAmount += $schedule->installment_amount;
            $totalPaid += $schedule->paid_amount;
            $currentRow++;
        }
        
        // Add total row
        $sheet->setCellValue('B' . $currentRow, 'TOTAL');
        $sheet->setCellValue('D' . $currentRow, $totalAmount);
        $sheet->setCellValue('E' . $currentRow, $totalPaid);
        $sheet->setCellValue('F' . $currentRow, $totalAmount - $totalPaid);
        
        // Generate filename
        $client_name_safe = isset($client->name) ? preg_replace('/[^A-Za-z0-9_\-]/', '_', $client->name) : 'client';
        $filename = "installment_{$client_name_safe}_" . intval($purchase_id) . "_{$project_safe}_" . date('Ymd') . ".xlsx";
        $temp_path = sys_get_temp_dir() . '/' . $filename;
        
        // Save to temp
        $writer = new Xlsx($spreadsheet);
        $writer->save($temp_path);
        
        // Store in purchase folder
        $store_result = crm_store_document(
            $temp_path,
            $purchase->client_id,
            $purchase_id,
            'schedule',
            [
                'file_num' => $purchase->file_num,
                'project' => $booking->project ?? '',
                'generated_date' => date('Y-m-d')
            ]
        );
        
        // Clean up temp file
        @unlink($temp_path);
        
        if (!$store_result['success']) {
            return $store_result;
        }
        
        return [
            'success' => true,
            'file_path' => $store_result['path'],
            'full_path' => $store_result['full_path'],
            'file_id' => $store_result['file_id'],
            'filename' => $filename
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * Queue email with Excel schedule attachment
 * 
 * @param int $purchase_id Purchase ID
 * @param string $excel_path Full path to Excel file
 * @return bool Success status
 */
function queue_excel_schedule_email($purchase_id, $excel_path) {
    global $db;
    
    try {
        $purchase = $db->where('id', $purchase_id)->getOne('wo_booking_helper', ['client_id', 'file_num']);
        if (!$purchase) return false;
        
        $client = $db->where('id', $purchase->client_id)->getOne('crm_customers', ['name', 'email']);
        if (!$client || empty($client->email)) return false;
        
        $db->insert('crm_email_queue', [
            'purchase_id' => $purchase_id,
            'client_id' => $purchase->client_id,
            'recipient_email' => $client->email,
            'recipient_name' => $client->name,
            'email_type' => 'schedule_generated',
            'metadata' => json_encode([
                'client_name' => $client->name,
                'file_num' => $purchase->file_num,
                'excel_path' => $excel_path
            ]),
            'attachment_path' => $excel_path,
            'status' => 'queued',
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        return true;
    } catch (Exception $e) {
        return false;
    }
}

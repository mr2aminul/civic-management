context currently removed for keep project smaller
............
...............
............

ini_set('display_errors', 1);
ini_set('display_startup_errors', 0);
error_reporting(1);
$sms_api_elitbuzz = 'C200794166e165ca0470b2.11927812';
$sms_api_iglWeb = '44517100458726701710045872';

function sms_delivery_iglweb($sms_id) {
    global $sms_api_iglWeb;

    // Ensure API key is set
    if (!isset($sms_api_iglWeb)) {
        return ["error" => "API key is not set."];
    }

    $url = "http://sms.felnadma.com/api/v1/getDeliveryReport?api_key=$sms_api_iglWeb&sms_id=$sms_id"; // IGL Web URL

    // Fetch the API response
    $response = @file_get_contents($url);
    if ($response === FALSE) {
        return ["error" => "Failed to fetch response from IGL Web API."];
    }

    // Decode JSON response for IGL Web
    $result = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return ["error" => "Failed to decode JSON response from IGL Web: " . json_last_error_msg()];
    }

    return $result; // Return the structured result for further processing
}
function sms_delivery($sms_id) {
    global $sms_api_elitbuzz;

    // Ensure API key is set
    if (!isset($sms_api_elitbuzz)) {
        return ["error" => "API key is not set."];
    }

    $url = "https://msg.elitbuzz-bd.com/miscapi/$sms_api_elitbuzz/getDLRRep/$sms_id";

    // Fetch the API response
    $response = @file_get_contents($url);
    if ($response === FALSE) {
        return ["error" => "Failed to fetch response from API."];
    }

    // Decode JSON response
    $result = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return ["error" => "Failed to decode JSON response: " . json_last_error_msg()];
    }

    // Return the result
    return $result;
}

function sms_update_report() {
    global $db;

    // Get the current time for comparison
    $currentTimestamp = time();
    
    // Calculate the timestamp for two minutes ago
    $twoMinutesAgo = $currentTimestamp + (2 * 60);

    // Get SMS records that are 'Sending', have been tried less than 3 times, and where last_tried is less than or equal to two minutes ago
    $smsRecords = $db->where('status', 'Sending') // Not equal to 'Delivered'
                     ->where('tried', 3, '<')
                     ->where('last_tried', $twoMinutesAgo, '<=')
                     ->get(T_SMS, 5);

    // Log the SQL query for debugging (optional)
    error_log($db->getLastQuery());

    foreach ($smsRecords as $smsRecord) {
        // Check the vendor based on sms_vendor field
        $isElitbuzz = strpos($smsRecord->sms_vendor, 'elitbuzz') !== false;

        // Get delivery details based on vendor
        $deliveries = $isElitbuzz ? sms_delivery($smsRecord->sms_id) : sms_delivery_iglweb($smsRecord->sms_id);

        // Process the delivery response
        if (isset($deliveries['error'])) {
            // Log the error if there's an issue with delivery status
            error_log("Delivery check error for sms_id: " . $smsRecord->sms_id . " - " . $deliveries['error']);
            continue; // Skip to the next record
        }
        
        // Prepare the data array for update
        $data_array = [
            'tried' => $smsRecord->tried + 1, // Increment the tried count
            'last_tried' => $currentTimestamp // Update the last tried timestamp to current time
        ];

        if ($isElitbuzz) {
			if ($data_array['status'] > 2 && $deliveries[0]['sms_status_str'] != 'Delivered') {
				$status = 'Failed';
			} else {
				$status = $deliveries[0]['sms_status_str'] ?? 'Failed'; // Assuming it's an array
			}
            $data_array['status'] = $status;
            $data_array['cost'] = $deliveries[0]['charges_per_sms'] ?? '0'; // Set default cost
        } else {
            $data_array['status'] = $deliveries['status'] ?? 'Failed';
            $data_array['cost'] = $deliveries['charges_per_sms'] ?? ''; // Add cost if available
        }
        
        // Update SMS record in the database
        if (!$db->where('id', $smsRecord->id)->update(T_SMS, $data_array)) {
            error_log("Failed to update SMS status for sms_id: " . $smsRecord->sms_id);
        }
    }
}

function sms_send($data) {
    global $sms_api_elitbuzz, $sms_api_iglWeb, $wo;

    // Determine the SMS vendor
    $sms_vendor = $data['sms_vendor'] ?? 'elitbuzz';

    // Balance checks for each vendor
    if ($sms_vendor === 'elitbuzz' && $wo['config']['elitbuzz_balance'] <= 1) {
        return 'Elitbuzz balance is low! Current Balance is: <strong class="text-black">৳' . $wo['config']['elitbuzz_balance'] . '</strong>';
    }

    if ($sms_vendor === 'iglWeb' && $wo['config']['iglweb_balance'] <= 1) {
        return 'IGL Web balance is low! Current Balance is: <strong class="text-black">৳' . $wo['config']['iglweb_balance'] . '</strong>';
    }

    // Prepare API URL and API key based on the vendor
    $url = '';
    $api_key = '';
    if ($sms_vendor === 'elitbuzz') {
        $url = "https://msg.elitbuzz-bd.com/smsapi";
        $api_key = $sms_api_elitbuzz;
    } elseif ($sms_vendor === 'iglWeb') {
        $url = "http://sms.felnadma.com/api/v1/send";
        $api_key = $sms_api_iglWeb;
    }

    // Ensure API key is set
    if (empty($api_key)) {
        return "Error: API key is not set for $sms_vendor.";
    }

    // Prepare the message and contacts
    if (empty($data['senderid'])) {
        $data['senderid'] = '38756'; // Default sender ID for elitbuzz
    }
    
    $data['msg'] = str_replace('<br>', "\n", $data['msg']); // Handle line breaks
    $contactsArray = array_map('trim', explode(',', $data['contacts'])); // Split contacts
    $contacts = ($sms_vendor === 'elitbuzz') ? implode('+', $contactsArray) : implode(',', $contactsArray); // Format contacts

    // Initialize cURL
    $ch = curl_init();

    // Prepare request based on vendor
    if ($sms_vendor === 'elitbuzz') {
        $params = [
            'api_key' => $api_key,
            'type' => 'text', // Adjust if sending Unicode
            'contacts' => $contacts,
            'senderid' => $data['senderid'],
            'msg' => $data['msg'],
        ];
        
        curl_setopt($ch, CURLOPT_URL, $url . '?' . http_build_query($params));
    } elseif ($sms_vendor === 'iglWeb') {
        $params = [
            'api_key' => $api_key,
            'contacts' => $contacts,
            'senderid' => $data['senderid'],
            'msg' => $data['msg'],
        ];

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    }

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/x-www-form-urlencoded'
    ]);

    // Execute cURL request
    $response = curl_exec($ch);

    // Check for cURL errors
    if (curl_errno($ch)) {
        $error_msg = curl_error($ch);
        curl_close($ch);
        return "Error: cURL error - $error_msg";
    }

    // Check HTTP response code
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($http_code != 200) {
        curl_close($ch);
        return "Error: HTTP response code $http_code";
    }

    curl_close($ch);

    // Handle responses for each vendor
    if ($sms_vendor === 'elitbuzz') {
        if (strpos($response, 'SMS SUBMITTED: ID - ') !== false) {
            preg_match('/SMS SUBMITTED: ID - (\S+)/', $response, $matches);
            $sms_id = $matches[1] ?? null;
            return handle_sms_record($data, $sms_id, $sms_vendor);
        } else {
            return "Unexpected response from Elitbuzz: $response";
        }
    } elseif ($sms_vendor === 'iglWeb') {
        $responseData = json_decode($response);
		print_r($responseData);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return "Error: Failed to decode JSON response.";
        }
        if (isset($responseData->status) && $responseData->status === 'success') {
            return handle_sms_record($data, $responseData->sms_id ?? null, $sms_vendor);
        } else {
            return "Error from IGL Web: " . ($responseData->message ?? "Unexpected response.");
        }
    }

    return "Unexpected response: $response";
}

// Function to handle SMS record insertion and balance update
function handle_sms_record($data, $sms_id, $sms_vendor) {
    global $db, $wo;

    // Map sender ID if necessary
    $senderid_map = [
        '38714' => '8809601011151',
        '38756' => 'CIVIC PLOTS',
        '40042' => 'CIVIC',
        '01847431162' => '01847431162',
        'CIVIC LAND' => 'CIVIC LAND',
    ];

    $senderid = $senderid_map[$data['senderid']] ?? $data['senderid'];
    $contacts = explode('+', $data['contacts']);
    $inserted_ids = [];

    foreach ($contacts as $contact) {
        $contact = trim($contact);
        $data_array = [
            'sms_id' => trim($sms_id),
            'sms_vendor' => $sms_vendor,
            'senderid' => $senderid,
            'type' => $data['type'],
            'contacts' => formatContactNumber($contact),
            'msg' => $data['msg'],
            'time' => time(),
            'last_tried' => time(),
            'user_id' => $data['user_id'] ?? $wo['user']['user_id'],
            'status' => 'Sending',
            'cost' => ''
        ];
        $db->insert(T_SMS, $data_array);
    }

    // // Update current balance
    // $balance = sms_get_balance($sms_vendor);
    // if ($balance) {
        // $balance = str_replace('Your Balance is:BDT ', '', $balance);
        // $key = $sms_vendor === 'elitbuzz' ? 'elitbuzz_balance' : 'iglweb_balance';
        // Wo_SaveConfig($key, $balance);
    // }

    return "SMS sent successfully with ID: $sms_id";
}

function sms_get_balance($sms_vendor = 'elitbuzz') {
	Global $sms_api_elitbuzz, $sms_api_iglWeb;
	
    $api_keys = [
        'elitbuzz' => $sms_api_elitbuzz ?? null,
        'iglWeb' => $sms_api_iglWeb ?? null,
    ];

    if (!isset($api_keys[$sms_vendor])) {
        return "Error: API key is not set.";
    }

    switch ($sms_vendor) {
        case 'elitbuzz':
            $url = "https://msg.elitbuzz-bd.com/miscapi/{$api_keys['elitbuzz']}/getBalance";
            $context = stream_context_create(['http' => ['timeout' => 4]]);
            $response = @file_get_contents($url, false, $context);
            break;

        case 'iglWeb':
            $url = "http://sms.felnadma.com/api/v1/balance?api_key={$api_keys['iglWeb']}";
            $context = stream_context_create(['http' => ['timeout' => 4]]);
            $response = @file_get_contents($url, false, $context);
            $responseData = json_decode($response);
            return $responseData->balance ?? "Error: Unable to retrieve balance.";
    }

    if ($response === false) {
        return "Error: " . (error_get_last()['message'] ?? 'Unknown error occurred.');
    }

    return $response;
}

function lead_report($user_id, $date) {
    global $db;
    // Fetch the report for the specified user and date
    $report = $db->where('user_id', $user_id)
                 ->where('date', $date)
                 ->getOne(T_LEADS_REPORT);

    // Check if a report was found; return structured data or null
    return $report ?: null; // Return null if no report found
}

function maskPhoneNumber($phone_number) {
    // Remove non-numeric characters (including '+' and spaces)
    $phone_number = preg_replace('/\D/', '', $phone_number);

    // Check if the phone number has at least 10 digits (after cleaning)
    if (strlen($phone_number) >= 10) {
        // Identify the length of the number (if it's a country code format, it will be longer)
        $length = strlen($phone_number);

        // For numbers with a country code, we assume the country code is the first 1-3 digits (e.g., +880)
        // Extract first part, middle part, and last part
        $start = substr($phone_number, 0, 3);  // Keep the first 3 digits (country code or area code)
        $end = substr($phone_number, -3);     // Keep the last 4 digits

        // Mask the middle part
        $middle = substr($phone_number, 3, -3); // Get the middle part excluding the first 3 and last 4 digits

        // Mask the middle 6 digits (or fewer if the number is shorter)
        $masked_middle = str_repeat('*', min(strlen($middle), 6));

        // Combine the parts: start + masked middle + end
        return $start . $masked_middle . $end;
    } else {
        // If the number is too short, return it as is (you can handle this differently if needed)
        return $phone_number;
    }
}

// Function to normalize the 'created' field to a Unix timestamp
function normalizeCreatedDate($createdDate) {
	// First, try to parse using strtotime (works for most common date formats)
	$timestamp = strtotime($createdDate);

	// If strtotime fails, try to handle specific date formats
	if ($timestamp === false) {
		// Try parsing ISO 8601 format using DateTime
		$dateTime = DateTime::createFromFormat(DateTime::ATOM, $createdDate);
		if ($dateTime) {
			$timestamp = $dateTime->getTimestamp();
		}
	}

	// If we still don't have a valid timestamp, use the current time
	if ($timestamp === false) {
		$timestamp = time(); // Default to current time
	}

	return $timestamp;
}

function GetDeviceName($userAgent) {
    $userAgent = strtolower($userAgent);
    if (strpos($userAgent, 'edge') !== false) return 'Edge';
    if (strpos($userAgent, 'chrome') !== false) return 'Chrome';
    if (strpos($userAgent, 'firefox') !== false) return 'Firefox';
    if (strpos($userAgent, 'safari') !== false && strpos($userAgent, 'chrome') === false) return 'Safari';
    if (strpos($userAgent, 'opera') !== false || strpos($userAgent, 'opr/') !== false) return 'Opera';
    if (strpos($userAgent, 'trident') !== false || strpos($userAgent, 'msie') !== false) return 'IE';
    return 'Unknown';
}
function GetDeviceIcon($name) {
    switch ($name) {
        case 'Chrome': return '<i class="fa fa-chrome"></i>';
        case 'Firefox': return '<i class="fa fa-firefox-browser"></i>';
        case 'Safari': return '<i class="fa fa-safari"></i>';
        case 'Edge': return '<i class="fa fa-edge"></i>';
        case 'Opera': return '<i class="fa fa-opera"></i>';
        case 'IE': return '<i class="fa fa-internet-explorer"></i>';
        default: return '<i class="fa fa-question-circle"></i>';
    }
}


// Helper function to generate ordinal suffix
function getOrdinalSuffix($number) {
    $ends = ['th', 'st', 'nd', 'rd', 'th', 'th', 'th', 'th', 'th', 'th'];
    if ((($number % 100) >= 11) && (($number % 100) <= 13)) {
        return $number . 'th';
    } else {
        return $number . $ends[$number % 10];
    }
}

/**
 * Prepare email template with dynamic content
 */
function prepareEmailTemplate(string $email_type, array $metadata = [], string $project_root = null): array
{
    global $db;
    $project_root = $project_root ?: dirname(__DIR__);

    $template_file = $project_root . "/templates/emails/{$email_type}.php";
    if (!file_exists($template_file)) {
        $template_file = $project_root . "/templates/emails/default.php";
    }

    if (!file_exists($template_file)) {
        $html_body = generateInlineTemplate($email_type, $metadata);
    } else {
        // expose metadata vars safely, avoid overwriting existing vars
        extract($metadata, EXTR_SKIP);
        ob_start();
        include $template_file;
        $html_body = ob_get_clean();
    }

    $subject = $metadata['subject'] ?? getDefaultSubject($email_type);

    // Create plain text by stripping styles and scripts first
    $text_body_source = preg_replace('/<style\b[^>]*>(.*?)<\/style>/is', '', $html_body);
    $text_body_source = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $text_body_source);
    $text_body = trim(strip_tags($text_body_source));

    $attachments = [];
    if (!empty($metadata['pdf_path'])) {
        $attachments[] = [
            'path' => $metadata['pdf_path'],
            'name' => basename($metadata['pdf_path'])
        ];
    }

    return [
        'subject' => $subject,
        'html_body' => $html_body,
        'text_body' => $text_body,
        'attachments' => $attachments,
        'cc' => $metadata['cc'] ?? []
    ];
}

function generateInlineTemplate(string $email_type, array $metadata = []): string
{
    $company_name = 'Civic Group BD';
    $subject = htmlspecialchars(getDefaultSubject($email_type), ENT_QUOTES, 'UTF-8');
    $client = htmlspecialchars($metadata['client_name'] ?? 'Valued Customer', ENT_QUOTES, 'UTF-8');

    $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
.container { max-width: 600px; margin: 0 auto; padding: 20px; }
.header { background: #0066cc; color: white; padding: 20px; text-align: center; }
.content { background: #f9f9f9; padding: 20px; }
.footer { text-align: center; padding: 20px; font-size: 12px; color: #888; }
.button { background: #0066cc; color: white; padding: 10px 20px; text-decoration: none; display: inline-block; border-radius: 5px; }
</style>
</head>
<body>
<div class="container">
    <div class="header"><h1>{$company_name}</h1></div>
    <div class="content">
        <h2>{$subject}</h2>
        <p>Dear {$client},</p>
        <!-- content -->
HTML;

    $html .= getEmailContent($email_type, $metadata);
    $html .= "\n    </div>\n    <div class=\"footer\"><p>&copy; " . date('Y') . " {$company_name}. All rights reserved.</p></div>\n</div>\n</body>\n</html>";

    return $html;
}

function getEmailContent(string $email_type, array $metadata = []): string
{
    switch ($email_type) {
        case 'payment_received':
            return '<p>We have received your payment of <strong>৳' . number_format((float)($metadata['amount'] ?? 0), 2) . '</strong>.</p>'
                 . '<p>Receipt Number: <strong>' . htmlspecialchars($metadata['receipt_number'] ?? '', ENT_QUOTES, 'UTF-8') . '</strong></p>'
                 . '<p>Thank you for your timely payment!</p>';
        case 'invoice_created':
            return '<p>A new invoice has been generated for your account.</p>'
                 . '<p>Invoice Number: <strong>' . htmlspecialchars($metadata['invoice_number'] ?? '', ENT_QUOTES, 'UTF-8') . '</strong></p>'
                 . '<p>Amount: <strong>৳' . number_format((float)($metadata['amount'] ?? 0), 2) . '</strong></p>'
                 . '<p>Due Date: <strong>' . htmlspecialchars($metadata['due_date'] ?? '', ENT_QUOTES, 'UTF-8') . '</strong></p>';
        case 'reminder_7_days':
        case 'reminder_3_days':
        case 'reminder_1_day':
            return '<p>This is a friendly reminder that you have a payment due.</p>'
                 . '<p>Amount: <strong>৳' . number_format((float)($metadata['amount'] ?? 0), 2) . '</strong></p>'
                 . '<p>Due Date: <strong>' . htmlspecialchars($metadata['due_date'] ?? '', ENT_QUOTES, 'UTF-8') . '</strong></p>'
                 . '<p>Please make payment by the due date to avoid any late fees.</p>';
        default:
            return '<p>This is a notification from Civic Group BD.</p>';
    }
}

function getDefaultSubject(string $email_type): string
{
    $subjects = [
        'invoice_created' => 'New Invoice Generated',
        'payment_received' => 'Payment Received - Thank You',
        'invoice_paid' => 'Invoice Paid in Full',
        'payment_completed' => 'Congratulations! All Payments Complete',
        'reminder_7_days' => 'Payment Reminder - Due in 7 Days',
        'reminder_3_days' => 'Urgent: Payment Due in 3 Days',
        'reminder_1_day' => 'Final Reminder: Payment Due Tomorrow',
        'payment_overdue' => 'Payment Overdue Notice',
        'reschedule_proposed' => 'Payment Reschedule Proposal',
        'cancellation_approved' => 'Purchase Cancellation Confirmed',
        'refund_scheduled' => 'Refund Schedule Confirmation',
        'transfer_approved' => 'Transfer Approved',
        'plot_held' => 'Plot Hold Notification',
        'hold_expired' => 'Plot Hold Expired'
    ];
    return $subjects[$email_type] ?? 'Notification from Civic Group';
}

require_once 'NumberToWords.php';
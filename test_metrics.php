<?php
// Test Metrics API
// Run this from command line: php test_metrics.php

require_once('../assets/init.php');

$client_id = 1; // Change to actual client ID

echo "Testing get_client_metrics for client_id=$client_id\n\n";

$_GET['client_id'] = $client_id;
$s = 'get_client_metrics';

include('xhr/manage_inventory/analytics.php');

<?php
// Run migration script
require_once 'config.php';

$mysqli = new mysqli($sql_db_host, $sql_db_user, $sql_db_pass, $sql_db_name);

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

$sql = file_get_contents(__DIR__ . '/migrations/004_advanced_file_manager_features.sql');

if ($mysqli->multi_query($sql)) {
    do {
        if ($result = $mysqli->store_result()) {
            while ($row = $result->fetch_row()) {
                print_r($row);
            }
            $result->free();
        }
        if ($mysqli->more_results()) {
            echo "---\n";
        }
    } while ($mysqli->next_result());
}

if ($mysqli->error) {
    echo "Error: " . $mysqli->error . "\n";
} else {
    echo "Migration completed successfully!\n";
}

$mysqli->close();

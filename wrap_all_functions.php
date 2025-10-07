<?php
/**
 * Comprehensive script to wrap all functions with function_exists checks
 */

$file = __DIR__ . '/assets/includes/file_manager_helper.php';
$content = file_get_contents($file);

// Backup
$backupFile = $file . '.backup_before_wrap_' . date('Ymd_His');
copy($file, $backupFile);
echo "Backup created: $backupFile\n\n";

// Split content into lines for processing
$lines = explode("\n", $content);
$output = [];
$inFunction = false;
$functionName = '';
$braceCount = 0;
$functionStartLine = 0;

for ($i = 0; $i < count($lines); $i++) {
    $line = $lines[$i];

    // Check if this line starts a function definition (but not already wrapped)
    if (preg_match('/^function\s+(fm_[a-z_]+)\s*\(/', $line, $matches)) {
        $funcName = $matches[1];

        // Check if already wrapped (look at previous line)
        $prevLine = $i > 0 ? $lines[$i - 1] : '';
        if (strpos($prevLine, "!function_exists('$funcName')") === false) {
            // Add function_exists wrapper
            $output[] = "if (!function_exists('$funcName')) {";
            $inFunction = true;
            $functionName = $funcName;
            $braceCount = 0;
            $functionStartLine = count($output);
            echo "Wrapping: $funcName\n";
        }
    }

    // Add the current line
    $output[] = $line;

    // Track braces if we're in a function
    if ($inFunction) {
        $braceCount += substr_count($line, '{');
        $braceCount -= substr_count($line, '}');

        // If braces are balanced, function is complete
        if ($braceCount == 0 && strpos($line, '{') !== false) {
            $output[] = '}';
            $inFunction = false;
            $functionName = '';
        }
    }
}

// Write the wrapped content
file_put_contents($file, implode("\n", $output));

echo "\nDone! All functions wrapped with function_exists checks.\n";
echo "Original file backed up to: $backupFile\n";

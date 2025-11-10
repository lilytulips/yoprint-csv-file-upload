#!/usr/bin/env php
<?php

/**
 * PHP Configuration Checker for Large File Uploads
 * 
 * Run this script to check your current PHP configuration:
 * php check-php-config.php
 */

echo "=== PHP Configuration Check ===\n\n";

$settings = [
    'upload_max_filesize' => ini_get('upload_max_filesize'),
    'post_max_size' => ini_get('post_max_size'),
    'memory_limit' => ini_get('memory_limit'),
    'max_execution_time' => ini_get('max_execution_time'),
    'max_input_time' => ini_get('max_input_time'),
];

/**
 * Parse size string (e.g., "128M", "2G") to bytes
 */
function parseSize($size) {
    $size = trim($size);
    if (empty($size)) return 0;
    $last = strtolower($size[strlen($size)-1]);
    $size = (int) $size;
    
    switch($last) {
        case 'g':
            $size *= 1024;
        case 'm':
            $size *= 1024;
        case 'k':
            $size *= 1024;
    }
    
    return $size;
}

foreach ($settings as $key => $value) {
    $status = '✓';
    $recommended = '';
    
    switch ($key) {
        case 'upload_max_filesize':
            $recommended = '100M (recommended for large CSV files)';
            $currentBytes = parseSize($value);
            if ($currentBytes < 50 * 1024 * 1024) {
                $status = '✗';
            }
            break;
        case 'post_max_size':
            $recommended = '100M (should be >= upload_max_filesize)';
            $currentBytes = parseSize($value);
            $uploadBytes = parseSize($settings['upload_max_filesize']);
            if ($currentBytes < $uploadBytes) {
                $status = '✗';
            }
            break;
        case 'memory_limit':
            $recommended = '512M (recommended for large file processing)';
            $currentBytes = parseSize($value);
            if ($currentBytes < 256 * 1024 * 1024 && $currentBytes != -1) {
                $status = '✗';
            }
            break;
        case 'max_execution_time':
            $recommended = '300 (5 minutes for large file processing)';
            if ($value < 300 && $value != 0) {
                $status = '✗';
            }
            break;
    }
    
    echo sprintf(
        "%s %-20s: %-10s %s\n",
        $status,
        $key,
        $value,
        $recommended ? "({$recommended})" : ''
    );
}

echo "\n=== PHP Configuration File Location ===\n";
echo "Loaded configuration file: " . php_ini_loaded_file() . "\n";
echo "Additional .ini files: " . php_ini_scanned_files() . "\n";

echo "\n=== Recommendations ===\n";
echo "If any settings show ✗, you should update your php.ini file:\n\n";
echo "upload_max_filesize = 100M\n";
echo "post_max_size = 100M\n";
echo "memory_limit = 512M\n";
echo "max_execution_time = 300\n\n";
echo "After updating php.ini, restart your PHP server.\n";
echo "See PHP_CONFIG.md for detailed instructions.\n";


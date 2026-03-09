<?php
// This script updates all PHP files in the /api/ad directory to include SQL mode setting
$directory = __DIR__;
$files = glob($directory . '/*.php');
$pattern = '/\$conn->query\("SET SESSION sql_mode = \'NO_ENGINE_SUBSTITUTION\'".\);/';
$replacement = '$conn->query("SET SESSION sql_mode = \'NO_ENGINE_SUBSTITUTION\'");';
$addedCount = 0;

foreach ($files as $file) {
    // Skip this file and backups
    if (basename($file) === 'update-sql-mode.php' || basename($file) === 'db.php' || strpos($file, '_bak') !== false) {
        continue;
    }
    
    $content = file_get_contents($file);
    
    // Check if file already has the SQL mode setting
    if (strpos($content, "SET SESSION sql_mode = 'NO_ENGINE_SUBSTITUTION'") !== false) {
        echo basename($file) . " - Already has SQL mode setting\n";
        continue;
    }
    
    // Check if file has mysqli connection
    if (strpos($content, 'new mysqli($host') === false && strpos($content, '$conn = new mysqli') === false) {
        echo basename($file) . " - No mysqli connection found\n";
        continue;
    }
    
    // Find and update the connection
    $updated = preg_replace(
        '/(\$conn = new mysqli\(\$host, \$user, \$password, \$dbname\);.*?if \(\$conn->connect_error\) \{[^}]+\})/',
        '$0' . "\n\n// Set SQL mode to avoid strict mode issues\n" . '$conn->query("SET SESSION sql_mode = \'NO_ENGINE_SUBSTITUTION\'");',
        $content,
        1,
        $count
    );
    
    if ($count > 0) {
        file_put_contents($file, $updated);
        echo basename($file) . " - Updated\n";
        $addedCount++;
    } else {
        echo basename($file) . " - Could not find connection pattern\n";
    }
}

echo "\nTotal files updated: " . $addedCount . "\n";
?>

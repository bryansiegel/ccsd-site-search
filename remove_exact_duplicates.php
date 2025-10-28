<?php
require_once 'vendor/autoload.php';

use CCSD\Search\Database;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$db = Database::getInstance();

echo "Finding and removing exact duplicate URLs...\n";

// Find duplicate URL combinations
$duplicates = $db->fetchAll("
    SELECT website_id, url, COUNT(*) as count, GROUP_CONCAT(id ORDER BY id) as ids
    FROM scraped_pages 
    GROUP BY website_id, url 
    HAVING count > 1
    ORDER BY count DESC
");

$totalDeleted = 0;

foreach ($duplicates as $duplicate) {
    $ids = explode(',', $duplicate['ids']);
    $keepId = array_shift($ids); // Keep the first (oldest) record
    
    if (!empty($ids)) {
        $placeholders = str_repeat('?,', count($ids) - 1) . '?';
        $db->query("DELETE FROM scraped_pages WHERE id IN ($placeholders)", $ids);
        $totalDeleted += count($ids);
        
        echo "Kept ID {$keepId}, deleted " . count($ids) . " duplicates for: {$duplicate['url']}\n";
    }
}

echo "\nTotal deleted: {$totalDeleted} duplicate records\n";

// Now try to add the unique constraint
echo "\nAdding unique constraint...\n";
try {
    $db->query("ALTER TABLE scraped_pages ADD CONSTRAINT unique_page UNIQUE (website_id, url(191))");
    echo "Unique constraint added successfully.\n";
} catch (Exception $e) {
    echo "Error adding constraint: " . $e->getMessage() . "\n";
}

echo "Cleanup completed!\n";
?>
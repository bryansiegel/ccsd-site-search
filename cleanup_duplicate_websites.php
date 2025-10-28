<?php
require_once 'vendor/autoload.php';

use CCSD\Search\Database;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$db = Database::getInstance();

echo "Finding and cleaning up duplicate websites...\n";

// Find duplicate websites by base_domain
$duplicateWebsites = $db->fetchAll("
    SELECT base_domain, COUNT(*) as count, GROUP_CONCAT(id ORDER BY id) as ids
    FROM websites 
    GROUP BY base_domain 
    HAVING count > 1
    ORDER BY count DESC
");

$totalWebsitesDeleted = 0;
$totalPagesDeleted = 0;

foreach ($duplicateWebsites as $duplicate) {
    $ids = explode(',', $duplicate['ids']);
    $keepId = array_shift($ids); // Keep the first (oldest) website record
    
    echo "\nProcessing duplicate websites for domain: {$duplicate['base_domain']}\n";
    echo "Keeping website ID: {$keepId}\n";
    
    foreach ($ids as $deleteId) {
        // Count pages that will be deleted
        $pageCount = $db->fetchOne("SELECT COUNT(*) as count FROM scraped_pages WHERE website_id = ?", [$deleteId])['count'];
        
        // Delete scraped pages for this website
        $db->query("DELETE FROM scraped_pages WHERE website_id = ?", [$deleteId]);
        
        // Delete the website
        $db->query("DELETE FROM websites WHERE id = ?", [$deleteId]);
        
        echo "Deleted website ID {$deleteId} and {$pageCount} associated pages\n";
        $totalWebsitesDeleted++;
        $totalPagesDeleted += $pageCount;
    }
}

// Now remove any remaining duplicate URLs that might exist within the same website
echo "\nRemoving any remaining duplicate URLs within websites...\n";

$duplicateUrls = $db->fetchAll("
    SELECT website_id, url, COUNT(*) as count, GROUP_CONCAT(id ORDER BY id) as ids
    FROM scraped_pages 
    GROUP BY website_id, url 
    HAVING count > 1
    ORDER BY count DESC
");

$urlDuplicatesDeleted = 0;

foreach ($duplicateUrls as $duplicate) {
    $ids = explode(',', $duplicate['ids']);
    $keepId = array_shift($ids); // Keep the first (oldest) record
    
    if (!empty($ids)) {
        $placeholders = str_repeat('?,', count($ids) - 1) . '?';
        $db->query("DELETE FROM scraped_pages WHERE id IN ($placeholders)", $ids);
        $urlDuplicatesDeleted += count($ids);
        
        echo "Kept page ID {$keepId}, deleted " . count($ids) . " URL duplicates for: {$duplicate['url']}\n";
    }
}

echo "\nCleanup Summary:\n";
echo "- Deleted websites: {$totalWebsitesDeleted}\n";
echo "- Deleted pages from duplicate websites: {$totalPagesDeleted}\n";
echo "- Deleted URL duplicates: {$urlDuplicatesDeleted}\n";
echo "Total pages deleted: " . ($totalPagesDeleted + $urlDuplicatesDeleted) . "\n";

echo "\nCleanup completed!\n";
?>
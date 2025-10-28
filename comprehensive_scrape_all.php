<?php
require_once 'vendor/autoload.php';

use CCSD\Search\Scraper;
use CCSD\Search\Database;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Set higher limits for comprehensive scraping
ini_set('memory_limit', '2G');
ini_set('max_execution_time', 7200); // 2 hours

$scraper = new Scraper();
$db = Database::getInstance();

echo "=== COMPREHENSIVE SCRAPING OF ALL CCSD WEBSITES ===\n";
echo "Started at: " . date('Y-m-d H:i:s') . "\n\n";

// Get all websites except ccsd.net (which was already scraped comprehensively)
$websites = $db->fetchAll("SELECT * FROM websites WHERE base_domain != 'ccsd.net' ORDER BY name");

$totalStartTime = time();
$results = [];

foreach ($websites as $website) {
    echo "--- Scraping {$website['name']} ({$website['base_domain']}) ---\n";
    
    // Get initial count
    $initialCount = $db->fetchOne(
        "SELECT COUNT(*) as count FROM scraped_pages WHERE website_id = ?", 
        [$website['id']]
    )['count'];
    
    echo "Initial pages: {$initialCount}\n";
    echo "Max depth: {$website['max_depth']}\n";
    
    $startTime = time();
    
    try {
        $scraper->scrapeWebsite($website['id']);
        
        $endTime = time();
        $duration = $endTime - $startTime;
        
        $finalCount = $db->fetchOne(
            "SELECT COUNT(*) as count FROM scraped_pages WHERE website_id = ?", 
            [$website['id']]
        )['count'];
        
        $newPages = $finalCount - $initialCount;
        
        $results[] = [
            'name' => $website['name'],
            'domain' => $website['base_domain'],
            'success' => true,
            'initial_pages' => $initialCount,
            'final_pages' => $finalCount,
            'new_pages' => $newPages,
            'duration' => $duration
        ];
        
        echo "✓ Completed: {$finalCount} total pages (+{$newPages} new) in {$duration}s\n\n";
        
    } catch (Exception $e) {
        $finalCount = $db->fetchOne(
            "SELECT COUNT(*) as count FROM scraped_pages WHERE website_id = ?", 
            [$website['id']]
        )['count'];
        
        $results[] = [
            'name' => $website['name'],
            'domain' => $website['base_domain'],
            'success' => false,
            'initial_pages' => $initialCount,
            'final_pages' => $finalCount,
            'error' => $e->getMessage(),
            'duration' => time() - $startTime
        ];
        
        echo "✗ Error: " . $e->getMessage() . "\n\n";
    }
}

$totalEndTime = time();
$totalDuration = $totalEndTime - $totalStartTime;

echo "=== SCRAPING SUMMARY ===\n";
echo "Total duration: {$totalDuration} seconds (" . round($totalDuration/60, 1) . " minutes)\n";
echo "Completed at: " . date('Y-m-d H:i:s') . "\n\n";

$totalPages = 0;
$totalNewPages = 0;
$successCount = 0;

foreach ($results as $result) {
    $status = $result['success'] ? '✓' : '✗';
    $newPages = isset($result['new_pages']) ? $result['new_pages'] : 0;
    
    echo "{$status} {$result['name']}: {$result['final_pages']} pages (+{$newPages}) in {$result['duration']}s\n";
    
    if ($result['success']) {
        $successCount++;
        $totalNewPages += $newPages;
    }
    $totalPages += $result['final_pages'];
}

echo "\nTotals:\n";
echo "- Successful websites: {$successCount}/" . count($results) . "\n";
echo "- Total pages across all sites: {$totalPages}\n";
echo "- New pages added: {$totalNewPages}\n";

// Get final ccsd.net count for grand total
$ccsdCount = $db->fetchOne("SELECT COUNT(*) as count FROM scraped_pages WHERE website_id = 1")['count'];
echo "- CCSD.net pages: {$ccsdCount}\n";
echo "- Grand total: " . ($totalPages + $ccsdCount) . " pages\n";
?>
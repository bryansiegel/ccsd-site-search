<?php
require_once 'vendor/autoload.php';

use CCSD\Search\Scraper;
use CCSD\Search\Database;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Set higher memory and time limits for comprehensive scraping
ini_set('memory_limit', '1G');
ini_set('max_execution_time', 3600); // 1 hour

$scraper = new Scraper();
$db = Database::getInstance();

echo "Starting comprehensive scrape of ccsd.net...\n";
echo "Current time: " . date('Y-m-d H:i:s') . "\n";

// Clear existing data for fresh scrape
echo "Clearing existing ccsd.net data...\n";
$db->query("DELETE FROM scraped_pages WHERE website_id = 1");

$startTime = time();

try {
    $scraper->scrapeWebsite(1); // ccsd.net website ID is 1
    
    $endTime = time();
    $duration = $endTime - $startTime;
    
    $finalCount = $db->fetchOne("SELECT COUNT(*) as count FROM scraped_pages WHERE website_id = 1")['count'];
    
    echo "\n=== SCRAPING COMPLETED ===\n";
    echo "Total pages scraped: {$finalCount}\n";
    echo "Duration: {$duration} seconds (" . round($duration/60, 2) . " minutes)\n";
    echo "Average: " . round($finalCount / max($duration, 1), 2) . " pages per second\n";
    echo "Completed at: " . date('Y-m-d H:i:s') . "\n";
    
} catch (Exception $e) {
    echo "Scraping stopped with error: " . $e->getMessage() . "\n";
    $finalCount = $db->fetchOne("SELECT COUNT(*) as count FROM scraped_pages WHERE website_id = 1")['count'];
    echo "Pages scraped before error: {$finalCount}\n";
}
?>
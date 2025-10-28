<?php
require_once 'vendor/autoload.php';

use CCSD\Search\Scraper;
use CCSD\Search\Database;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$scraper = new Scraper();
$db = Database::getInstance();

echo "Re-scraping engage.ccsd.net with increased depth...\n";
echo "Current time: " . date('Y-m-d H:i:s') . "\n";

// Clear existing engage.ccsd.net data for fresh scrape
echo "Clearing existing engage.ccsd.net data...\n";
$db->query("DELETE FROM scraped_pages WHERE website_id = 4");

$startTime = time();

try {
    $scraper->scrapeWebsite(4); // engage.ccsd.net website ID is 4
    
    $endTime = time();
    $duration = $endTime - $startTime;
    
    $finalCount = $db->fetchOne("SELECT COUNT(*) as count FROM scraped_pages WHERE website_id = 4")['count'];
    
    echo "\n=== ENGAGE.CCSD.NET SCRAPING COMPLETED ===\n";
    echo "Total pages scraped: {$finalCount}\n";
    echo "Duration: {$duration} seconds\n";
    echo "Completed at: " . date('Y-m-d H:i:s') . "\n";
    
    // Check if ravereview page was found
    $raveReview = $db->fetchOne("SELECT url FROM scraped_pages WHERE website_id = 4 AND url LIKE '%ravereview%'");
    if ($raveReview) {
        echo "✓ Found ravereview page: " . $raveReview['url'] . "\n";
    } else {
        echo "✗ Ravereview page not found in scraped content\n";
        echo "Checking if page is reachable...\n";
        
        // Test direct access
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://engage.ccsd.net/ravereview/');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        echo "Direct access to ravereview page: HTTP {$httpCode}\n";
    }
    
} catch (Exception $e) {
    echo "Scraping error: " . $e->getMessage() . "\n";
    $finalCount = $db->fetchOne("SELECT COUNT(*) as count FROM scraped_pages WHERE website_id = 4")['count'];
    echo "Pages scraped before error: {$finalCount}\n";
}
?>
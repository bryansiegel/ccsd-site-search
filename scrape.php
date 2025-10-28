<?php
require_once 'vendor/autoload.php';

use CCSD\Search\Scraper;
use CCSD\Search\Database;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$scraper = new Scraper();
$db = Database::getInstance();

echo "CCSD Search Scraper\n";
echo "==================\n\n";

if ($argc > 1) {
    $websiteId = (int)$argv[1];
    
    $website = $db->fetchOne("SELECT * FROM websites WHERE id = ?", [$websiteId]);
    
    if (!$website) {
        echo "Error: Website with ID {$websiteId} not found.\n";
        exit(1);
    }
    
    echo "Scraping website: {$website['name']} ({$website['url']})\n";
    echo "Max depth: {$website['max_depth']}\n";
    echo "Started at: " . date('Y-m-d H:i:s') . "\n\n";
    
    try {
        $scraper->scrapeWebsite($websiteId);
        echo "Scraping completed successfully!\n";
        
        $pageCount = $db->fetchOne(
            "SELECT COUNT(*) as count FROM scraped_pages WHERE website_id = ?", 
            [$websiteId]
        )['count'];
        
        echo "Total pages scraped: {$pageCount}\n";
        
    } catch (Exception $e) {
        echo "Error during scraping: " . $e->getMessage() . "\n";
        exit(1);
    }
    
} else {
    echo "Processing scrape queue...\n\n";
    
    try {
        $scraper->processQueue();
        echo "Queue processing completed!\n";
        
    } catch (Exception $e) {
        echo "Error processing queue: " . $e->getMessage() . "\n";
        exit(1);
    }
}

echo "\nFinished at: " . date('Y-m-d H:i:s') . "\n";
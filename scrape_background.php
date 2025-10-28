<?php
require_once 'vendor/autoload.php';

use CCSD\Search\Database;
use CCSD\Search\Scraper;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Get session ID from command line argument
$sessionId = $argv[1] ?? '';
if (empty($sessionId)) {
    echo "Error: No session ID provided\n";
    exit(1);
}

$db = Database::getInstance();
$scraper = new Scraper();

try {
    // Get the progress record
    $progress = $db->fetchOne("SELECT * FROM scrape_progress WHERE session_id = ?", [$sessionId]);
    if (!$progress) {
        echo "Error: Progress session not found\n";
        exit(1);
    }
    
    // Get active websites
    $activeWebsites = $db->fetchAll("SELECT * FROM websites WHERE status = 'active'");
    if (empty($activeWebsites)) {
        $db->update('scrape_progress', [
            'status' => 'failed',
            'message' => 'No active websites found'
        ], ['session_id' => $sessionId]);
        exit(1);
    }
    
    // Update status to in_progress
    $db->update('scrape_progress', [
        'status' => 'in_progress',
        'total_websites' => count($activeWebsites),
        'message' => 'Starting to scrape websites...'
    ], ['session_id' => $sessionId]);
    
    $completedCount = 0;
    $failedCount = 0;
    $totalNewPages = 0;
    
    foreach ($activeWebsites as $index => $website) {
        // Check if cancelled
        $currentProgress = $db->fetchOne("SELECT status FROM scrape_progress WHERE session_id = ?", [$sessionId]);
        if ($currentProgress['status'] === 'cancelled') {
            echo "Scraping cancelled by user\n";
            exit(0);
        }
        
        // Update current progress
        $db->update('scrape_progress', [
            'current_website' => $website['name'],
            'current_url' => $website['url'],
            'current_index' => $index + 1,
            'message' => "Scraping {$website['name']}..."
        ], ['session_id' => $sessionId]);
        
        try {
            // Get initial page count
            $initialCount = $db->fetchOne(
                "SELECT COUNT(*) as count FROM scraped_pages WHERE website_id = ?", 
                [$website['id']]
            )['count'];
            
            // Perform the scrape
            $scraper->scrapeWebsite($website['id']);
            
            // Get final page count
            $finalCount = $db->fetchOne(
                "SELECT COUNT(*) as count FROM scraped_pages WHERE website_id = ?", 
                [$website['id']]
            )['count'];
            
            $newPages = $finalCount - $initialCount;
            $totalNewPages += $newPages;
            $completedCount++;
            
            echo "Completed {$website['name']}: {$newPages} new pages\n";
            
        } catch (Exception $e) {
            $failedCount++;
            echo "Failed {$website['name']}: " . $e->getMessage() . "\n";
        }
        
        // Update progress counts
        $db->update('scrape_progress', [
            'completed_count' => $completedCount,
            'failed_count' => $failedCount,
            'total_new_pages' => $totalNewPages
        ], ['session_id' => $sessionId]);
    }
    
    // Mark as completed
    $db->update('scrape_progress', [
        'status' => 'completed',
        'current_website' => 'All websites completed',
        'current_url' => '',
        'current_index' => count($activeWebsites),
        'message' => "Scraping completed! {$completedCount} successful, {$failedCount} failed."
    ], ['session_id' => $sessionId]);
    
    echo "Scraping completed successfully\n";
    
} catch (Exception $e) {
    // Mark as failed
    $db->update('scrape_progress', [
        'status' => 'failed',
        'message' => 'Error: ' . $e->getMessage()
    ], ['session_id' => $sessionId]);
    
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
<?php
require_once 'vendor/autoload.php';

use CCSD\Search\Database;
use CCSD\Search\Scraper;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Get session ID and website ID from command line arguments
$sessionId = $argv[1] ?? '';
$websiteId = (int)($argv[2] ?? 0);

if (empty($sessionId) || $websiteId <= 0) {
    echo "Error: No session ID or website ID provided\n";
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
    
    // Get the specific website
    $website = $db->fetchOne("SELECT * FROM websites WHERE id = ? AND status = 'active'", [$websiteId]);
    if (!$website) {
        $db->update('scrape_progress', [
            'status' => 'failed',
            'message' => 'Website not found or inactive'
        ], ['session_id' => $sessionId]);
        exit(1);
    }
    
    // Update status to in_progress
    $db->update('scrape_progress', [
        'status' => 'in_progress',
        'current_index' => 1,
        'current_website' => $website['name'],
        'current_url' => $website['url'],
        'percentage' => 0,
        'message' => 'Starting to scrape ' . $website['name'] . '...'
    ], ['session_id' => $sessionId]);
    
    // Check if cancelled before starting
    $currentProgress = $db->fetchOne("SELECT status FROM scrape_progress WHERE session_id = ?", [$sessionId]);
    if ($currentProgress['status'] === 'cancelled') {
        echo "Scraping cancelled by user\n";
        exit(0);
    }
    
    echo "Starting scrape of {$website['name']} (ID: {$websiteId})\n";
    
    // Get initial page count
    $initialCount = $db->fetchOne(
        "SELECT COUNT(*) as count FROM scraped_pages WHERE website_id = ?", 
        [$websiteId]
    )['count'];
    
    // Update progress - scraping started
    $db->update('scrape_progress', [
        'percentage' => 10,
        'message' => 'Scraping ' . $website['name'] . '...'
    ], ['session_id' => $sessionId]);
    
    $startTime = microtime(true);
    
    try {
        // Set up progress callback to update database in real-time
        $scraper->setProgressCallback(function($progress) use ($db, $sessionId, $website, $websiteId, $initialCount) {
            // Get current page count in database
            $currentPages = $db->fetchOne(
                "SELECT COUNT(*) as count FROM scraped_pages WHERE website_id = ?", 
                [$websiteId]
            )['count'];
            
            $newPages = $currentPages - $initialCount;
            
            // Estimate total pages based on pages found so far and depth progress
            $estimatedTotal = max(100, $progress['visited_count'] * 1.5);
            
            $db->update('scrape_progress', [
                'percentage' => round($progress['percentage'], 1),
                'current_url' => $progress['current_url'],
                'current_index' => $progress['visited_count'], // Pages visited so far
                'total_websites' => round($estimatedTotal), // Dynamic estimate of total pages
                'total_new_pages' => $newPages,
                'completed_count' => 0, // Still in progress
                'failed_count' => 0, // No failures yet
                'message' => "Scraping {$website['name']}... ({$progress['visited_count']} pages visited, depth {$progress['current_depth']}/{$progress['max_depth']})"
            ], ['session_id' => $sessionId]);
        });
        
        // Perform the scrape
        $scraper->scrapeWebsite($websiteId);
        
        $endTime = microtime(true);
        $duration = round($endTime - $startTime, 2);
        
        // Get final page count
        $finalCount = $db->fetchOne(
            "SELECT COUNT(*) as count FROM scraped_pages WHERE website_id = ?", 
            [$websiteId]
        )['count'];
        
        $newPages = $finalCount - $initialCount;
        
        echo "✓ Completed {$website['name']}: {$finalCount} total pages (+{$newPages} new) in {$duration}s\n";
        
        // Update final progress with correct statistics
        $db->update('scrape_progress', [
            'status' => 'completed',
            'current_index' => $finalCount, // Total pages in this website
            'total_websites' => $finalCount, // Total pages discovered
            'percentage' => 100,
            'completed_count' => 1, // 1 website completed
            'failed_count' => 0, // No failures
            'total_new_pages' => $newPages,
            'message' => "Successfully scraped {$website['name']}: {$finalCount} total pages (+{$newPages} new)"
        ], ['session_id' => $sessionId]);
        
    } catch (Exception $e) {
        $errorMessage = $e->getMessage();
        echo "✗ Error scraping {$website['name']}: {$errorMessage}\n";
        
        // Get current page count for failed case
        $currentPages = $db->fetchOne(
            "SELECT COUNT(*) as count FROM scraped_pages WHERE website_id = ?", 
            [$websiteId]
        )['count'];
        $newPages = $currentPages - $initialCount;
        
        // Update progress with error
        $db->update('scrape_progress', [
            'status' => 'failed',
            'current_index' => $currentPages, // Pages that were scraped before failure
            'total_websites' => $currentPages,
            'percentage' => 0,
            'completed_count' => 0, // No websites completed due to failure
            'failed_count' => 1, // 1 website failed
            'total_new_pages' => $newPages,
            'message' => "Failed to scrape {$website['name']}: {$errorMessage}"
        ], ['session_id' => $sessionId]);
    }
    
} catch (Exception $e) {
    echo "Fatal error: " . $e->getMessage() . "\n";
    
    // Update progress with fatal error
    try {
        $db->update('scrape_progress', [
            'status' => 'failed',
            'message' => 'Fatal error: ' . $e->getMessage()
        ], ['session_id' => $sessionId]);
    } catch (Exception $dbError) {
        echo "Database error: " . $dbError->getMessage() . "\n";
    }
    
    exit(1);
}

echo "Single website scraping process completed\n";
?>
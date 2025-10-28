<?php
require_once 'vendor/autoload.php';

use CCSD\Search\Database;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$db = Database::getInstance();

echo "Cleaning up query parameter duplicates...\n";

// Enhanced normalize function (same as updated Scraper.php)
function normalizeUrl(string $url): string
{
    $parsed = parse_url($url);
    
    if (!$parsed || !isset($parsed['host'])) {
        return $url;
    }
    
    // Ensure https protocol
    $scheme = 'https';
    
    // Normalize host to lowercase
    $host = strtolower($parsed['host']);
    
    // Normalize path - remove trailing slash unless it's root
    $path = $parsed['path'] ?? '/';
    if ($path !== '/' && substr($path, -1) === '/') {
        $path = rtrim($path, '/');
    }
    
    // Build normalized URL
    $normalized = $scheme . '://' . $host . $path;
    
    // Add query string if present, but filter out common navigation parameters
    if (isset($parsed['query'])) {
        parse_str($parsed['query'], $queryParams);
        
        // Remove common UI/navigation parameters that don't affect content
        $filteredParams = array_filter($queryParams, function($key) {
            $ignoredParams = ['students', 'parents', 'employees', 'trustees', 'community', 'utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term', 'ref', 'source'];
            return !in_array(strtolower($key), $ignoredParams);
        }, ARRAY_FILTER_USE_KEY);
        
        if (!empty($filteredParams)) {
            $normalized .= '?' . http_build_query($filteredParams);
        }
    }
    
    // Add fragment if present
    if (isset($parsed['fragment'])) {
        $normalized .= '#' . $parsed['fragment'];
    }
    
    return $normalized;
}

// Temporarily drop unique constraint
echo "Dropping unique constraint temporarily...\n";
try {
    $db->query("ALTER TABLE scraped_pages DROP INDEX unique_page");
    echo "Unique constraint dropped.\n";
} catch (Exception $e) {
    echo "Warning: Could not drop constraint: " . $e->getMessage() . "\n";
}

// Get all pages and group by normalized URL
$allPages = $db->fetchAll("SELECT id, url, website_id FROM scraped_pages ORDER BY id ASC");

$urlGroups = [];
echo "Processing " . count($allPages) . " pages...\n";

foreach ($allPages as $page) {
    $normalizedUrl = normalizeUrl($page['url']);
    $key = $page['website_id'] . '|' . $normalizedUrl;
    
    if (!isset($urlGroups[$key])) {
        $urlGroups[$key] = [];
    }
    
    $urlGroups[$key][] = [
        'id' => $page['id'],
        'original_url' => $page['url'],
        'normalized_url' => $normalizedUrl
    ];
}

$updatedCount = 0;
$deletedCount = 0;

foreach ($urlGroups as $key => $pages) {
    if (count($pages) > 1) {
        // Multiple pages normalize to the same URL - keep the first one, delete the rest
        $keepPage = array_shift($pages);
        
        // Update the kept page to use normalized URL
        if ($keepPage['original_url'] !== $keepPage['normalized_url']) {
            $db->update('scraped_pages', 
                ['url' => $keepPage['normalized_url']], 
                ['id' => $keepPage['id']]
            );
            echo "Updated: {$keepPage['original_url']} -> {$keepPage['normalized_url']}\n";
            $updatedCount++;
        }
        
        // Delete duplicate pages
        foreach ($pages as $page) {
            $db->query("DELETE FROM scraped_pages WHERE id = ?", [$page['id']]);
            echo "Deleted duplicate: {$page['original_url']} (ID: {$page['id']})\n";
            $deletedCount++;
        }
    } else {
        // Single page - just update URL if needed
        $page = $pages[0];
        if ($page['original_url'] !== $page['normalized_url']) {
            $db->update('scraped_pages', 
                ['url' => $page['normalized_url']], 
                ['id' => $page['id']]
            );
            $updatedCount++;
        }
    }
}

// Re-add unique constraint
echo "\nRe-adding unique constraint...\n";
try {
    $db->query("ALTER TABLE scraped_pages ADD CONSTRAINT unique_page UNIQUE (website_id, url(191))");
    echo "Unique constraint re-added.\n";
} catch (Exception $e) {
    echo "Error re-adding constraint: " . $e->getMessage() . "\n";
}

echo "\nCleanup completed!\n";
echo "Summary:\n";
echo "- Updated URLs: {$updatedCount}\n";
echo "- Deleted duplicates: {$deletedCount}\n";
?>
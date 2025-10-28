<?php
require_once 'vendor/autoload.php';

use CCSD\Search\Database;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$db = Database::getInstance();

echo "Starting duplicate URL cleanup...\n";

// Temporarily drop the unique constraint to allow updates
echo "Dropping unique constraint temporarily...\n";
try {
    $db->query("ALTER TABLE scraped_pages DROP INDEX unique_page");
    echo "Unique constraint dropped.\n";
} catch (Exception $e) {
    echo "Warning: Could not drop constraint (may not exist): " . $e->getMessage() . "\n";
}

// Function to normalize URLs (same as in Scraper.php)
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
    
    // Add query string if present
    if (isset($parsed['query'])) {
        $normalized .= '?' . $parsed['query'];
    }
    
    // Add fragment if present
    if (isset($parsed['fragment'])) {
        $normalized .= '#' . $parsed['fragment'];
    }
    
    return $normalized;
}

// Get all scraped pages
$allPages = $db->fetchAll("SELECT id, url, website_id FROM scraped_pages ORDER BY id ASC");

$normalizedUrls = [];
$duplicatesToDelete = [];
$updatesToMake = [];

echo "Processing " . count($allPages) . " pages...\n";

foreach ($allPages as $page) {
    $originalUrl = $page['url'];
    $normalizedUrl = normalizeUrl($originalUrl);
    $key = $page['website_id'] . '|' . $normalizedUrl;
    
    if (isset($normalizedUrls[$key])) {
        // Duplicate found - mark for deletion
        $duplicatesToDelete[] = $page['id'];
        echo "Duplicate found: {$originalUrl} (ID: {$page['id']})\n";
    } else {
        // First occurrence - keep this one and update URL if needed
        $normalizedUrls[$key] = $page['id'];
        
        if ($originalUrl !== $normalizedUrl) {
            $updatesToMake[] = [
                'id' => $page['id'],
                'old_url' => $originalUrl,
                'new_url' => $normalizedUrl
            ];
        }
    }
}

echo "\nFound " . count($duplicatesToDelete) . " duplicates to delete\n";
echo "Found " . count($updatesToMake) . " URLs to normalize\n";

// Delete duplicates
if (!empty($duplicatesToDelete)) {
    $placeholders = str_repeat('?,', count($duplicatesToDelete) - 1) . '?';
    $deleteQuery = "DELETE FROM scraped_pages WHERE id IN ($placeholders)";
    $db->query($deleteQuery, $duplicatesToDelete);
    echo "Deleted " . count($duplicatesToDelete) . " duplicate entries\n";
}

// Update URLs to normalized versions and handle new duplicates
foreach ($updatesToMake as $update) {
    // Check if normalized URL already exists for this website
    $existing = $db->fetchOne(
        "SELECT id FROM scraped_pages WHERE website_id = ? AND url = ? AND id != ?",
        [$db->fetchOne("SELECT website_id FROM scraped_pages WHERE id = ?", [$update['id']])['website_id'], $update['new_url'], $update['id']]
    );
    
    if ($existing) {
        // Delete this record since normalized URL already exists
        $db->query("DELETE FROM scraped_pages WHERE id = ?", [$update['id']]);
        echo "Deleted duplicate after normalization: {$update['old_url']} (ID: {$update['id']})\n";
    } else {
        // Safe to update
        $db->update('scraped_pages', 
            ['url' => $update['new_url']], 
            ['id' => $update['id']]
        );
        echo "Updated: {$update['old_url']} -> {$update['new_url']}\n";
    }
}

// Re-add the unique constraint
echo "\nRe-adding unique constraint...\n";
try {
    $db->query("ALTER TABLE scraped_pages ADD CONSTRAINT unique_page UNIQUE (website_id, url(191))");
    echo "Unique constraint re-added.\n";
} catch (Exception $e) {
    echo "Error re-adding constraint: " . $e->getMessage() . "\n";
}

echo "\nCleanup completed!\n";
echo "Summary:\n";
echo "- Deleted: " . count($duplicatesToDelete) . " duplicates\n";
echo "- Normalized: " . count($updatesToMake) . " URLs\n";
?>
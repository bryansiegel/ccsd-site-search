<?php
require_once 'vendor/autoload.php';

use CCSD\Search\SearchEngine;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$searchEngine = new SearchEngine();

// Test search with URL matching
echo "=== Testing Search with URL Matching ===\n\n";

$testQueries = [
    'rave review',
    'transportation',
    'engage',
    'facilities'
];

foreach ($testQueries as $query) {
    echo "Search: '{$query}'\n";
    echo str_repeat('-', 50) . "\n";
    
    $results = $searchEngine->search($query, 1, 5);
    
    if (empty($results['results'])) {
        echo "No results found.\n\n";
        continue;
    }
    
    foreach ($results['results'] as $i => $result) {
        $rank = $i + 1;
        $url = $result['url'];
        $title = $result['title'];
        
        // Check if URL contains search terms
        $urlMatch = stripos($url, str_replace(' ', '', $query)) !== false ? " [URL MATCH]" : "";
        
        echo "{$rank}. {$title}{$urlMatch}\n";
        echo "   {$url}\n";
        echo "   Domain: {$result['base_domain']}\n\n";
    }
    
    echo "\n";
}
?>
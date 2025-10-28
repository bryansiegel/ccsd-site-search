<?php

namespace CCSD\Search;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Symfony\Component\DomCrawler\Crawler;

class Scraper
{
    private Database $db;
    private Client $client;
    private array $visited = [];
    
    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->client = new Client([
            'timeout' => 60,
            'connect_timeout' => 10,
            'headers' => [
                'User-Agent' => 'CCSD Search Bot 1.0'
            ]
        ]);
    }
    
    private function normalizeUrl(string $url): string
    {
        // Parse the URL
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
    
    public function scrapeWebsite(int $websiteId): void
    {
        $website = $this->db->fetchOne("SELECT * FROM websites WHERE id = ?", [$websiteId]);
        
        if (!$website) {
            throw new \Exception("Website not found");
        }
        
        echo "Starting scrape of {$website['base_domain']} with max depth {$website['max_depth']}\n";
        $this->visited = [];
        $this->crawlUrl($website['url'], $websiteId, $website['max_depth'], 0, $website['base_domain']);
        
        echo "Completed scrape. Total pages visited: " . count($this->visited) . "\n";
        
        $this->db->update('websites', 
            ['last_scraped' => date('Y-m-d H:i:s')], 
            ['id' => $websiteId]
        );
    }
    
    private function crawlUrl(string $url, int $websiteId, int $maxDepth, int $currentDepth, string $baseDomain): void
    {
        $normalizedUrl = $this->normalizeUrl($url);
        
        if ($currentDepth > $maxDepth || in_array($normalizedUrl, $this->visited)) {
            return;
        }
        
        $parsedUrl = parse_url($normalizedUrl);
        if (!isset($parsedUrl['host']) || $parsedUrl['host'] !== $baseDomain) {
            return;
        }
        
        $this->visited[] = $normalizedUrl;
        
        if (count($this->visited) % 10 == 0) {
            echo "Visited " . count($this->visited) . " pages, current depth: $currentDepth, URL: $normalizedUrl\n";
        }
        
        try {
            $response = $this->client->get($url);
            $html = $response->getBody()->getContents();
            $statusCode = $response->getStatusCode();
            
            if ($statusCode === 200) {
                $this->extractAndSaveContent($normalizedUrl, $html, $websiteId);
                
                if ($currentDepth < $maxDepth) {
                    $this->extractLinks($html, $url, $websiteId, $maxDepth, $currentDepth + 1, $baseDomain);
                }
            }
            
        } catch (RequestException $e) {
            error_log("Scraping error for $normalizedUrl: " . $e->getMessage());
        }
    }
    
    private function extractAndSaveContent(string $url, string $html, int $websiteId): void
    {
        $crawler = new Crawler($html);
        
        $title = '';
        $metaDescription = '';
        $content = '';
        $h1Tags = [];
        $h2Tags = [];
        $keywords = '';
        
        try {
            $titleNode = $crawler->filter('title');
            if ($titleNode->count() > 0) {
                $title = trim($titleNode->text());
            }
            
            $metaNode = $crawler->filter('meta[name="description"]');
            if ($metaNode->count() > 0) {
                $metaDescription = trim($metaNode->attr('content') ?? '');
            }
            
            $keywordsNode = $crawler->filter('meta[name="keywords"]');
            if ($keywordsNode->count() > 0) {
                $keywords = trim($keywordsNode->attr('content') ?? '');
            }
            
            $crawler->filter('h1')->each(function (Crawler $node) use (&$h1Tags) {
                $h1Tags[] = trim($node->text());
            });
            
            $crawler->filter('h2')->each(function (Crawler $node) use (&$h2Tags) {
                $h2Tags[] = trim($node->text());
            });
            
            $crawler->filter('script, style, nav, footer, .hidden')->each(function (Crawler $node) {
                $node->getNode(0)->parentNode->removeChild($node->getNode(0));
            });
            
            $bodyNode = $crawler->filter('body');
            if ($bodyNode->count() > 0) {
                $content = trim(preg_replace('/\s+/', ' ', strip_tags($bodyNode->html())));
            }
            
        } catch (\Exception $e) {
            error_log("Content extraction error for $url: " . $e->getMessage());
        }
        
        $contentHash = hash('sha256', $content);
        
        // Check for existing page using normalized URL
        $existingPage = $this->db->fetchOne(
            "SELECT id, content_hash FROM scraped_pages WHERE website_id = ? AND url = ?",
            [$websiteId, $url]
        );
        
        $data = [
            'title' => substr($title, 0, 500),
            'content' => $content,
            'meta_description' => substr($metaDescription, 0, 1000),
            'keywords' => substr($keywords, 0, 1000),
            'h1_tags' => implode(', ', array_slice($h1Tags, 0, 10)),
            'h2_tags' => implode(', ', array_slice($h2Tags, 0, 20)),
            'content_hash' => $contentHash,
            'status_code' => 200
        ];
        
        if ($existingPage) {
            if ($existingPage['content_hash'] !== $contentHash) {
                $this->db->update('scraped_pages', $data, ['id' => $existingPage['id']]);
            }
        } else {
            $data['website_id'] = $websiteId;
            $data['url'] = $url;
            try {
                $this->db->insert('scraped_pages', $data);
            } catch (\PDOException $e) {
                // Skip if duplicate entry (race condition or normalization issue)
                if (strpos($e->getMessage(), 'Duplicate entry') === false) {
                    throw $e;
                }
            }
        }
    }
    
    private function extractLinks(string $html, string $baseUrl, int $websiteId, int $maxDepth, int $currentDepth, string $baseDomain): void
    {
        $crawler = new Crawler($html);
        
        $crawler->filter('a[href]')->each(function (Crawler $node) use ($baseUrl, $websiteId, $maxDepth, $currentDepth, $baseDomain) {
            $href = $node->attr('href');
            $absoluteUrl = $this->makeAbsoluteUrl($href, $baseUrl);
            
            if ($absoluteUrl) {
                $normalizedUrl = $this->normalizeUrl($absoluteUrl);
                if (!in_array($normalizedUrl, $this->visited)) {
                    $this->crawlUrl($absoluteUrl, $websiteId, $maxDepth, $currentDepth, $baseDomain);
                }
            }
        });
    }
    
    private function makeAbsoluteUrl(string $url, string $baseUrl): ?string
    {
        // Clean and validate the URL
        $url = trim($url);
        if (empty($url)) {
            return null;
        }
        
        // Skip invalid URLs, fragments, javascript, mailto, tel, etc.
        if (strpos($url, '#') === 0 || 
            strpos($url, 'javascript:') === 0 || 
            strpos($url, 'mailto:') === 0 || 
            strpos($url, 'tel:') === 0 ||
            strpos($url, 'data:') === 0) {
            return null;
        }
        
        // Skip URLs with whitespace or invalid characters
        if (preg_match('/\s/', $url) || strpos($url, '"') !== false) {
            return null;
        }
        
        // Already absolute URL
        if (strpos($url, 'http') === 0) {
            return $url;
        }
        
        // Protocol-relative URLs
        if (strpos($url, '//') === 0) {
            return 'https:' . $url;
        }
        
        // Root-relative URLs
        if (strpos($url, '/') === 0) {
            $parsed = parse_url($baseUrl);
            if (!$parsed || !isset($parsed['scheme'], $parsed['host'])) {
                return null;
            }
            return $parsed['scheme'] . '://' . $parsed['host'] . $url;
        }
        
        // Relative URLs
        $baseDir = dirname($baseUrl);
        return rtrim($baseDir, '/') . '/' . ltrim($url, '/');
    }
    
    public function getQueuedScrapes(): array
    {
        return $this->db->fetchAll("
            SELECT sq.*, w.name as website_name, w.url as website_url 
            FROM scrape_queue sq 
            JOIN websites w ON sq.website_id = w.id 
            WHERE sq.status = 'pending' AND sq.attempts < sq.max_attempts
            ORDER BY sq.priority DESC, sq.scheduled_at ASC
            LIMIT 10
        ");
    }
    
    public function processQueue(): void
    {
        $queueItems = $this->getQueuedScrapes();
        
        foreach ($queueItems as $item) {
            $this->db->update('scrape_queue', [
                'status' => 'processing',
                'started_at' => date('Y-m-d H:i:s'),
                'attempts' => $item['attempts'] + 1
            ], ['id' => $item['id']]);
            
            try {
                $this->scrapeWebsite($item['website_id']);
                
                $this->db->update('scrape_queue', [
                    'status' => 'completed',
                    'completed_at' => date('Y-m-d H:i:s')
                ], ['id' => $item['id']]);
                
            } catch (\Exception $e) {
                $status = $item['attempts'] >= $item['max_attempts'] ? 'failed' : 'pending';
                
                $this->db->update('scrape_queue', [
                    'status' => $status,
                    'error_message' => $e->getMessage()
                ], ['id' => $item['id']]);
            }
        }
    }
}
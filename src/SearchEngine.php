<?php

namespace CCSD\Search;

class SearchEngine
{
    private Database $db;
    
    public function __construct()
    {
        $this->db = Database::getInstance();
    }
    
    public function search(string $query, int $page = 1, int $perPage = 10): array
    {
        if (empty(trim($query))) {
            return ['results' => [], 'total' => 0, 'page' => $page, 'per_page' => $perPage];
        }
        
        $this->logSearch($query);
        
        $offset = ($page - 1) * $perPage;
        $searchTerms = $this->prepareSearchTerms($query);
        
        $sql = "
            SELECT sp.*, w.name as website_name, w.base_domain,
                   MATCH(sp.title, sp.content, sp.meta_description, sp.keywords) AGAINST (? IN BOOLEAN MODE) as relevance_score,
                   CASE WHEN w.base_domain = 'ccsd.net' THEN 1 ELSE 0 END as is_ccsd_main
            FROM scraped_pages sp
            JOIN websites w ON sp.website_id = w.id
            WHERE w.status = 'active' 
            AND MATCH(sp.title, sp.content, sp.meta_description, sp.keywords) AGAINST (? IN BOOLEAN MODE)
            ORDER BY is_ccsd_main DESC, relevance_score DESC, sp.updated_at DESC
            LIMIT ? OFFSET ?
        ";
        
        $results = $this->db->fetchAll($sql, [$searchTerms, $searchTerms, $perPage, $offset]);
        
        $countSql = "
            SELECT COUNT(*) as total
            FROM scraped_pages sp
            JOIN websites w ON sp.website_id = w.id
            WHERE w.status = 'active' 
            AND MATCH(sp.title, sp.content, sp.meta_description, sp.keywords) AGAINST (? IN BOOLEAN MODE)
        ";
        
        $totalResult = $this->db->fetchOne($countSql, [$searchTerms]);
        $total = $totalResult['total'] ?? 0;
        
        $processedResults = array_map(function($result) use ($query) {
            return $this->processSearchResult($result, $query);
        }, $results);
        
        return [
            'results' => $processedResults,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'query' => $query,
            'search_terms' => $searchTerms
        ];
    }
    
    private function prepareSearchTerms(string $query): string
    {
        $query = trim($query);
        $terms = preg_split('/\s+/', $query);
        
        $booleanTerms = [];
        foreach ($terms as $term) {
            $term = preg_replace('/[^\w\s-]/', '', $term);
            if (strlen($term) >= 2) {
                $booleanTerms[] = '+' . $term . '*';
            }
        }
        
        return implode(' ', $booleanTerms);
    }
    
    private function processSearchResult(array $result, string $query = ''): array
    {
        $result['title'] = $result['title'] ?: 'Untitled Page';
        $result['snippet'] = $this->generateSnippet($result['content'], $result['meta_description']);
        $result['highlighted_snippet'] = $this->generateHighlightedSnippet($result['content'], $query);
        $result['display_url'] = $this->formatDisplayUrl($result['url']);
        
        return $result;
    }
    
    private function generateSnippet(string $content, string $metaDescription = ''): string
    {
        if (!empty($metaDescription) && strlen($metaDescription) > 50) {
            return substr($metaDescription, 0, 160) . (strlen($metaDescription) > 160 ? '...' : '');
        }
        
        $content = strip_tags($content);
        $content = preg_replace('/\s+/', ' ', $content);
        $content = trim($content);
        
        if (strlen($content) <= 160) {
            return $content;
        }
        
        return substr($content, 0, 157) . '...';
    }
    
    private function generateHighlightedSnippet(string $content, string $query): string
    {
        if (empty($query)) {
            return $this->generateSnippet($content);
        }
        
        $content = strip_tags($content);
        $content = preg_replace('/\s+/', ' ', $content);
        $content = trim($content);
        
        // Extract search terms
        $searchTerms = preg_split('/\s+/', trim($query));
        $searchTerms = array_filter($searchTerms, function($term) {
            return strlen($term) >= 2;
        });
        
        if (empty($searchTerms)) {
            return $this->generateSnippet($content);
        }
        
        // Find the best snippet location (where most search terms appear)
        $bestPosition = 0;
        $maxScore = 0;
        $snippetLength = 200;
        
        // Scan through content to find best snippet position
        $contentLength = strlen($content);
        for ($i = 0; $i <= $contentLength - $snippetLength; $i += 50) {
            $snippet = substr($content, $i, $snippetLength);
            $score = 0;
            
            foreach ($searchTerms as $term) {
                $score += substr_count(strtolower($snippet), strtolower($term));
            }
            
            if ($score > $maxScore) {
                $maxScore = $score;
                $bestPosition = $i;
            }
        }
        
        // Extract snippet around best position
        $start = max(0, $bestPosition - 50);
        $length = min($snippetLength + 100, $contentLength - $start);
        $snippet = substr($content, $start, $length);
        
        // Add ellipsis if needed
        if ($start > 0) {
            $snippet = '...' . $snippet;
        }
        if ($start + $length < $contentLength) {
            $snippet .= '...';
        }
        
        // Highlight search terms
        foreach ($searchTerms as $term) {
            $pattern = '/\b' . preg_quote($term, '/') . '\b/i';
            $snippet = preg_replace($pattern, '<strong>$0</strong>', $snippet);
        }
        
        return $snippet;
    }
    
    private function formatDisplayUrl(string $url): string
    {
        $parsed = parse_url($url);
        $display = $parsed['host'] ?? '';
        
        if (isset($parsed['path']) && $parsed['path'] !== '/') {
            $display .= $parsed['path'];
        }
        
        return $display;
    }
    
    private function logSearch(string $query): void
    {
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        $this->db->insert('search_logs', [
            'query' => substr($query, 0, 500),
            'ip_address' => $ipAddress,
            'user_agent' => substr($userAgent, 0, 1000)
        ]);
    }
    
    public function getPopularSearches(int $limit = 10): array
    {
        return $this->db->fetchAll("
            SELECT query, COUNT(*) as search_count
            FROM search_logs 
            WHERE searched_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY query
            ORDER BY search_count DESC
            LIMIT ?
        ", [$limit]);
    }
    
    public function getSearchStats(): array
    {
        $today = $this->db->fetchOne("
            SELECT COUNT(*) as count 
            FROM search_logs 
            WHERE DATE(searched_at) = CURDATE()
        ")['count'] ?? 0;
        
        $thisWeek = $this->db->fetchOne("
            SELECT COUNT(*) as count 
            FROM search_logs 
            WHERE searched_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ")['count'] ?? 0;
        
        $thisMonth = $this->db->fetchOne("
            SELECT COUNT(*) as count 
            FROM search_logs 
            WHERE searched_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ")['count'] ?? 0;
        
        return [
            'today' => $today,
            'this_week' => $thisWeek,
            'this_month' => $thisMonth
        ];
    }
}
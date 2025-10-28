<?php
require_once 'vendor/autoload.php';

use CCSD\Search\SearchEngine;
use CCSD\Search\Database;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$searchEngine = new SearchEngine();

$query = $_GET['addsearch'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;

$searchResults = $searchEngine->search($query, $page, $perPage);

$totalPages = ceil($searchResults['total'] / $perPage);
$hasResults = $searchResults['total'] > 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $query ? htmlspecialchars($query) . ' - ' : '' ?>CCSD Search Results</title>
    <style>
        body {
            font-family: "proxima-nova", Helvetica, Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f5f5f5;
        }
        .container {
            width: 960px;
            margin: 0 auto;
            background-color: white;
            min-height: 100vh;
        }
        .header {
            background-color: #1771b7;
            color: white;
            padding: 15px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
            font-weight: normal;
        }
        .header h1 a {
            color: white;
            text-decoration: none;
        }
        .search-wrap {
            padding: 20px;
            border-bottom: 1px solid #ddd;
            background-color: #f9f9f9;
        }
        .search-wrap form {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .search-wrap .addsearch {
            flex: 1;
            max-width: 500px;
            padding: 12px 20px;
            font-size: 16px;
            border: 2px solid #ddd;
            border-radius: 25px;
            outline: none;
            background: url('data:image/svg+xml;charset=UTF-8,%3Csvg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 16 16"%3E%3Cpath fill="%23999" d="M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001c.03.04.062.078.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1.007 1.007 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0z"/%3E%3C/svg%3E') no-repeat 15px center;
            padding-left: 45px;
        }
        .search-wrap .addsearch:focus {
            border-color: #1771b7;
            box-shadow: 0 0 0 3px rgba(23, 113, 183, 0.1);
        }
        .search-wrap .search-btn {
            background-color: #1771b7;
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 25px;
            cursor: pointer;
            font-size: 16px;
            white-space: nowrap;
        }
        .search-wrap .search-btn:hover {
            background-color: #145a91;
        }
        .hidden {
            position: absolute;
            left: -9999px;
            visibility: hidden;
        }
        .results-info {
            padding: 15px 20px;
            color: #666;
            border-bottom: 1px solid #eee;
        }
        .results-container {
            padding: 20px;
        }
        .search-result {
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }
        .search-result:last-child {
            border-bottom: none;
        }
        .result-title {
            font-size: 18px;
            margin-bottom: 5px;
        }
        .result-title a {
            color: #1771b7;
            text-decoration: none;
        }
        .result-title a:hover {
            text-decoration: underline;
        }
        .result-title a:visited {
            color: #5a1a9b;
        }
        .result-url {
            color: #006621;
            font-size: 14px;
            margin-bottom: 5px;
        }
        .result-snippet {
            color: #333;
            line-height: 1.4;
            margin-bottom: 5px;
        }
        .result-content {
            color: #333;
            line-height: 1.4;
            margin-bottom: 5px;
        }
        .result-content strong {
            background-color: #fff3cd;
            color: #856404;
            padding: 1px 3px;
            border-radius: 2px;
            font-weight: bold;
        }
        .result-meta {
            color: #666;
            font-size: 13px;
        }
        .no-results {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }
        .no-results h2 {
            color: #333;
            margin-bottom: 15px;
        }
        .pagination {
            text-align: center;
            padding: 30px 20px;
            border-top: 1px solid #eee;
        }
        .pagination a, .pagination span {
            display: inline-block;
            padding: 8px 12px;
            margin: 0 2px;
            text-decoration: none;
            border: 1px solid #ddd;
            color: #1771b7;
        }
        .pagination a:hover {
            background-color: #f5f5f5;
        }
        .pagination .current {
            background-color: #1771b7;
            color: white;
            border-color: #1771b7;
        }
        .admin-link {
            color: white;
            text-decoration: none;
            padding: 8px 16px;
            background-color: rgba(255,255,255,0.2);
            border-radius: 4px;
            border: 1px solid rgba(255,255,255,0.3);
            font-size: 14px;
        }
        .admin-link:hover {
            background-color: rgba(255,255,255,0.3);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><a href="index.php">CCSD Search</a></h1>
            <a href="admin.php" class="admin-link">Admin</a>
        </div>
        
        <div class="search-wrap">
            <form method="get" action="search.php">
                <label for="addsearch" class="hidden">Search</label>
                <input type="text" name="addsearch" id="addsearch" class="addsearch" 
                       value="<?= htmlspecialchars($query) ?>" 
                       placeholder="Search CCSD websites..."
                       autofocus>
                <input type="submit" class="search-btn" value="Search">
            </form>
        </div>
        
        <?php if ($query): ?>
            <div class="results-info">
                <?php if ($hasResults): ?>
                    About <?= number_format($searchResults['total']) ?> results 
                    <?php if ($totalPages > 1): ?>
                        (page <?= $page ?> of <?= $totalPages ?>)
                    <?php endif; ?>
                    for <strong><?= htmlspecialchars($query) ?></strong>
                <?php else: ?>
                    No results found for <strong><?= htmlspecialchars($query) ?></strong>
                <?php endif; ?>
            </div>
            
            <div class="results-container">
                <?php if ($hasResults): ?>
                    <?php foreach ($searchResults['results'] as $result): ?>
                        <div class="search-result">
                            <div class="result-title">
                                <a href="<?= htmlspecialchars($result['url']) ?>" target="_blank">
                                    <?= htmlspecialchars($result['title']) ?>
                                </a>
                            </div>
                            <div class="result-url">
                                <?= htmlspecialchars($result['display_url']) ?>
                            </div>
                            <div class="result-content">
                                <?= $result['highlighted_snippet'] ?>
                            </div>
                            <div class="result-meta">
                                <?= htmlspecialchars($result['website_name']) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <?php if ($totalPages > 1): ?>
                        <div class="pagination">
                            <?php if ($page > 1): ?>
                                <a href="?addsearch=<?= urlencode($query) ?>&page=<?= $page - 1 ?>">« Previous</a>
                            <?php endif; ?>
                            
                            <?php
                            $startPage = max(1, $page - 5);
                            $endPage = min($totalPages, $page + 5);
                            
                            for ($i = $startPage; $i <= $endPage; $i++):
                            ?>
                                <?php if ($i == $page): ?>
                                    <span class="current"><?= $i ?></span>
                                <?php else: ?>
                                    <a href="?addsearch=<?= urlencode($query) ?>&page=<?= $i ?>"><?= $i ?></a>
                                <?php endif; ?>
                            <?php endfor; ?>
                            
                            <?php if ($page < $totalPages): ?>
                                <a href="?addsearch=<?= urlencode($query) ?>&page=<?= $page + 1 ?>">Next »</a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    
                <?php else: ?>
                    <div class="no-results">
                        <h2>No results found</h2>
                        <p>Your search for <strong><?= htmlspecialchars($query) ?></strong> did not match any pages.</p>
                        <p>Suggestions:</p>
                        <ul style="text-align: left; display: inline-block;">
                            <li>Make sure all words are spelled correctly</li>
                            <li>Try different keywords</li>
                            <li>Try more general keywords</li>
                            <li>Try fewer keywords</li>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
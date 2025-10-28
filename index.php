<?php
require_once 'vendor/autoload.php';

use CCSD\Search\Database;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$searchQuery = $_GET['addsearch'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CCSD Search</title>
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
            padding: 20px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 48px;
            font-weight: normal;
        }
        .search-wrap {
            padding: 40px 20px;
            text-align: center;
        }
        .search-wrap form {
            display: inline-block;
            position: relative;
        }
        .search-wrap .addsearch {
            width: 400px;
            padding: 15px 20px;
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
            padding: 15px 25px;
            border-radius: 25px;
            margin-left: 10px;
            cursor: pointer;
            font-size: 16px;
            transition: background-color 0.3s;
        }
        .search-wrap .search-btn:hover {
            background-color: #145a91;
        }
        .hidden {
            position: absolute;
            left: -9999px;
            visibility: hidden;
        }
        .content {
            padding: 20px;
            text-align: center;
            color: #666;
        }
        .admin-link {
            position: absolute;
            top: 20px;
            right: 20px;
            color: white;
            text-decoration: none;
            padding: 8px 16px;
            background-color: rgba(255,255,255,0.2);
            border-radius: 4px;
            border: 1px solid rgba(255,255,255,0.3);
        }
        .admin-link:hover {
            background-color: rgba(255,255,255,0.3);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <a href="admin.php" class="admin-link">Admin</a>
            <h1>CCSD Search</h1>
        </div>
        
        <div class="search-wrap">
            <form method="get" action="search.php">
                <label for="addsearch" class="hidden">Search</label>
                <input type="text" name="addsearch" id="addsearch" class="addsearch" 
                       value="<?= htmlspecialchars($searchQuery) ?>" 
                       placeholder="Search CCSD websites..."
                       autofocus>
                <input type="submit" class="search-btn" value="Search">
            </form>
        </div>
        
        <div class="content">
            <p>Search across all configured CCSD websites for information, documents, and resources.</p>
            <p>Enter your search terms above to get started.</p>
        </div>
    </div>
</body>
</html>
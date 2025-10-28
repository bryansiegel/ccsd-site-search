<?php
require_once 'vendor/autoload.php';

use CCSD\Search\Auth;
use CCSD\Search\Database;
use CCSD\Search\Scraper;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$auth = new Auth();
$auth->requireLogin();

$db = Database::getInstance();
$message = '';
$scrapeResults = [];
$scrapeError = '';

// Function to format scrape frequency
function formatScrapeFrequency($frequency) {
    if ($frequency == -1) {
        return 'Unlimited';
    } elseif ($frequency < 60) {
        return $frequency . ' seconds';
    } elseif ($frequency < 3600) {
        return round($frequency / 60) . ' minutes';
    } elseif ($frequency < 86400) {
        return round($frequency / 3600) . ' hours';
    } else {
        return round($frequency / 86400) . ' days';
    }
}

if ($_POST) {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_website':
                $name = $_POST['name'] ?? '';
                $url = $_POST['url'] ?? '';
                $maxDepth = (int)($_POST['max_depth'] ?? 3);
                $frequency = $_POST['scrape_frequency'] ?? '3600';
                
                // Handle unlimited frequency
                $frequencyValue = ($frequency === 'unlimited') ? -1 : (int)$frequency;
                
                if ($name && $url) {
                    $parsedUrl = parse_url($url);
                    $baseDomain = $parsedUrl['host'] ?? '';
                    
                    try {
                        $db->insert('websites', [
                            'name' => $name,
                            'url' => $url,
                            'base_domain' => $baseDomain,
                            'max_depth' => $maxDepth,
                            'scrape_frequency' => $frequencyValue,
                            'created_by' => $_SESSION['user_id']
                        ]);
                        $message = 'Website added successfully!';
                    } catch (Exception $e) {
                        $message = 'Error adding website: ' . $e->getMessage();
                    }
                }
                break;
                
            case 'edit_website':
                $websiteId = (int)($_POST['website_id'] ?? 0);
                $name = $_POST['name'] ?? '';
                $url = $_POST['url'] ?? '';
                $maxDepth = (int)($_POST['max_depth'] ?? 3);
                $frequency = $_POST['scrape_frequency'] ?? '3600';
                
                if ($websiteId && $name && $url) {
                    $parsedUrl = parse_url($url);
                    $baseDomain = $parsedUrl['host'] ?? '';
                    
                    // Handle unlimited frequency
                    $frequencyValue = ($frequency === 'unlimited') ? -1 : (int)$frequency;
                    
                    try {
                        $db->update('websites', [
                            'name' => $name,
                            'url' => $url,
                            'base_domain' => $baseDomain,
                            'max_depth' => $maxDepth,
                            'scrape_frequency' => $frequencyValue
                        ], ['id' => $websiteId]);
                        $message = 'Website updated successfully!';
                    } catch (Exception $e) {
                        $message = 'Error updating website: ' . $e->getMessage();
                    }
                } else {
                    $message = 'Missing required fields for website update!';
                }
                break;
                
            case 'delete_website':
                $websiteId = (int)($_POST['website_id'] ?? 0);
                if ($websiteId) {
                    $db->query("DELETE FROM websites WHERE id = ?", [$websiteId]);
                    $message = 'Website deleted successfully!';
                }
                break;
                
            case 'scrape_website':
                $websiteId = (int)($_POST['website_id'] ?? 0);
                if ($websiteId) {
                    $website = $db->fetchOne("SELECT * FROM websites WHERE id = ?", [$websiteId]);
                    if ($website) {
                        try {
                            $scraper = new Scraper();
                            
                            // Get initial page count
                            $initialCount = $db->fetchOne(
                                "SELECT COUNT(*) as count FROM scraped_pages WHERE website_id = ?", 
                                [$websiteId]
                            )['count'];
                            
                            $startTime = microtime(true);
                            
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
                            
                            $scrapeResults = [
                                'success' => true,
                                'website_name' => $website['name'],
                                'website_url' => $website['url'],
                                'duration' => $duration,
                                'initial_pages' => $initialCount,
                                'final_pages' => $finalCount,
                                'new_pages' => $newPages,
                                'max_depth' => $website['max_depth']
                            ];
                            
                            $message = "Scraping completed successfully for {$website['name']}!";
                            
                        } catch (Exception $e) {
                            $scrapeError = $e->getMessage();
                            $scrapeResults = [
                                'success' => false,
                                'website_name' => $website['name'],
                                'website_url' => $website['url'],
                                'error' => $e->getMessage()
                            ];
                            $message = "Scraping failed for {$website['name']}: " . $e->getMessage();
                        }
                    } else {
                        $message = 'Website not found!';
                    }
                } else {
                    $message = 'Invalid website ID!';
                }
                break;
                
            case 'scrape_all':
                $activeWebsites = $db->fetchAll("SELECT * FROM websites WHERE status = 'active'");
                if (!empty($activeWebsites)) {
                    $scrapeResults = [
                        'success' => true,
                        'total_websites' => count($activeWebsites),
                        'successful_scrapes' => 0,
                        'failed_scrapes' => 0,
                        'total_duration' => 0,
                        'total_new_pages' => 0,
                        'details' => []
                    ];
                    
                    $scraper = new Scraper();
                    $overallStartTime = microtime(true);
                    
                    foreach ($activeWebsites as $website) {
                        try {
                            // Get initial page count
                            $initialCount = $db->fetchOne(
                                "SELECT COUNT(*) as count FROM scraped_pages WHERE website_id = ?", 
                                [$website['id']]
                            )['count'];
                            
                            $startTime = microtime(true);
                            
                            // Perform the scrape
                            $scraper->scrapeWebsite($website['id']);
                            
                            $endTime = microtime(true);
                            $duration = round($endTime - $startTime, 2);
                            
                            // Get final page count
                            $finalCount = $db->fetchOne(
                                "SELECT COUNT(*) as count FROM scraped_pages WHERE website_id = ?", 
                                [$website['id']]
                            )['count'];
                            
                            $newPages = $finalCount - $initialCount;
                            
                            $scrapeResults['successful_scrapes']++;
                            $scrapeResults['total_new_pages'] += $newPages;
                            $scrapeResults['details'][] = [
                                'success' => true,
                                'name' => $website['name'],
                                'url' => $website['url'],
                                'duration' => $duration,
                                'new_pages' => $newPages,
                                'total_pages' => $finalCount
                            ];
                            
                        } catch (Exception $e) {
                            $scrapeResults['failed_scrapes']++;
                            $scrapeResults['details'][] = [
                                'success' => false,
                                'name' => $website['name'],
                                'url' => $website['url'],
                                'error' => $e->getMessage()
                            ];
                        }
                    }
                    
                    $overallEndTime = microtime(true);
                    $scrapeResults['total_duration'] = round($overallEndTime - $overallStartTime, 2);
                    
                    if ($scrapeResults['failed_scrapes'] > 0) {
                        $scrapeResults['success'] = false;
                    }
                    
                    $message = "Scrape All completed! {$scrapeResults['successful_scrapes']} successful, {$scrapeResults['failed_scrapes']} failed.";
                } else {
                    $message = 'No active websites found to scrape!';
                }
                break;
                
            case 'scrape_all_ajax':
                // Handle AJAX scrape all request
                $sessionId = $_POST['session_id'] ?? '';
                if (empty($sessionId)) {
                    echo json_encode(['success' => false, 'error' => 'No session ID provided']);
                    exit;
                }
                
                // Start scraping in background
                $activeWebsites = $db->fetchAll("SELECT * FROM websites WHERE status = 'active'");
                if (!empty($activeWebsites)) {
                    // Initialize progress
                    $db->insert('scrape_progress', [
                        'session_id' => $sessionId,
                        'status' => 'starting',
                        'total_websites' => count($activeWebsites),
                        'current_index' => 0,
                        'message' => 'Initializing scrape process...'
                    ]);
                    
                    // Start background scraping process
                    $command = "php scrape_background.php " . escapeshellarg($sessionId) . " > /dev/null 2>&1 &";
                    exec($command);
                    
                    // Return success to start polling
                    echo json_encode(['success' => true, 'session_id' => $sessionId]);
                } else {
                    echo json_encode(['success' => false, 'error' => 'No active websites found']);
                }
                exit;
                
            case 'cancel_scrape':
                $sessionId = $_POST['session_id'] ?? '';
                if ($sessionId) {
                    $db->update('scrape_progress', 
                        ['status' => 'cancelled', 'message' => 'Cancelled by user'], 
                        ['session_id' => $sessionId]
                    );
                }
                echo json_encode(['success' => true]);
                exit;
        }
    }
}

$websites = $db->fetchAll("
    SELECT w.*, u.username as created_by_name,
           (SELECT COUNT(*) FROM scraped_pages WHERE website_id = w.id) as pages_count
    FROM websites w 
    LEFT JOIN users u ON w.created_by = u.id 
    ORDER BY w.created_at DESC
");

$stats = $db->fetchOne("
    SELECT 
        (SELECT COUNT(*) FROM websites) as total_websites,
        (SELECT COUNT(*) FROM scraped_pages) as total_pages,
        (SELECT COUNT(*) FROM search_logs WHERE DATE(searched_at) = CURDATE()) as searches_today,
        (SELECT COUNT(*) FROM scrape_queue WHERE status = 'pending') as pending_scrapes
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CCSD Search Admin</title>
    <style>
        body {
            font-family: "proxima-nova", Helvetica, Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f5f5f5;
        }
        .header {
            background-color: #1771b7;
            color: white;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
        }
        .logout-btn {
            background-color: rgba(255,255,255,0.2);
            color: white;
            padding: 8px 16px;
            text-decoration: none;
            border-radius: 4px;
            border: 1px solid rgba(255,255,255,0.3);
        }
        .logout-btn:hover {
            background-color: rgba(255,255,255,0.3);
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
        }
        .stat-number {
            font-size: 32px;
            font-weight: bold;
            color: #1771b7;
        }
        .stat-label {
            color: #666;
            margin-top: 5px;
        }
        .section {
            background: white;
            margin-bottom: 30px;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .section-header {
            background-color: #1771b7;
            color: white;
            padding: 15px 20px;
            font-size: 18px;
            font-weight: bold;
        }
        .section-content {
            padding: 20px;
        }
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        input, select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        button {
            background-color: #1771b7;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        button:hover {
            background-color: #145a91;
        }
        .btn-danger {
            background-color: #dc3545;
        }
        .btn-danger:hover {
            background-color: #c82333;
        }
        .btn-scrape {
            background-color: #28a745;
        }
        .btn-scrape:hover {
            background-color: #218838;
        }
        .btn-scrape-all {
            background-color: #ffc107;
            color: #212529;
        }
        .btn-scrape-all:hover {
            background-color: #e0a800;
            color: #212529;
        }
        .btn-edit {
            background-color: #007bff;
        }
        .btn-edit:hover {
            background-color: #0056b3;
        }
        .section-header-with-button {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .section-header-with-button button {
            margin: 0;
            font-size: 14px;
            padding: 8px 16px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #f8f9fa;
            font-weight: bold;
        }
        .message {
            padding: 10px;
            margin-bottom: 20px;
            border-radius: 4px;
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .status-active { color: #28a745; }
        .status-inactive { color: #6c757d; }
        .status-error { color: #dc3545; }
        .scrape-results {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            padding: 15px;
            margin-top: 20px;
        }
        .scrape-success {
            background-color: #d4edda;
            border-color: #c3e6cb;
            color: #155724;
        }
        .scrape-error {
            background-color: #f8d7da;
            border-color: #f5c6cb;
            color: #721c24;
        }
        .scrape-details {
            margin-top: 10px;
        }
        .scrape-details strong {
            display: inline-block;
            width: 120px;
        }
        .progress-container {
            display: none;
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            padding: 20px;
            margin: 20px 0;
        }
        .progress-bar-container {
            width: 100%;
            height: 30px;
            background-color: #e9ecef;
            border-radius: 15px;
            overflow: hidden;
            margin: 10px 0;
            box-shadow: inset 0 1px 3px rgba(0,0,0,0.2);
        }
        .progress-bar {
            height: 100%;
            background: linear-gradient(90deg, #28a745, #20c997);
            width: 0%;
            transition: width 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 14px;
        }
        .progress-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        .progress-stat {
            background: white;
            padding: 10px;
            border-radius: 4px;
            border: 1px solid #dee2e6;
            text-align: center;
        }
        .progress-stat-number {
            font-size: 24px;
            font-weight: bold;
            color: #1771b7;
        }
        .progress-stat-label {
            font-size: 12px;
            color: #6c757d;
            margin-top: 2px;
        }
        .current-website {
            background-color: #e3f2fd;
            border: 1px solid #bbdefb;
            border-radius: 4px;
            padding: 10px;
            margin: 10px 0;
        }
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 0;
            border-radius: 8px;
            width: 90%;
            max-width: 600px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
        }
        .modal-header {
            background-color: #1771b7;
            color: white;
            padding: 15px 20px;
            border-radius: 8px 8px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .modal-header h3 {
            margin: 0;
        }
        .modal-close {
            background: none;
            border: none;
            color: white;
            font-size: 24px;
            cursor: pointer;
            padding: 0;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .modal-close:hover {
            background-color: rgba(255,255,255,0.2);
            border-radius: 50%;
        }
        .modal-body {
            padding: 20px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>CCSD Search Admin</h1>
        <div>
            Welcome, <?= htmlspecialchars($_SESSION['username']) ?> | 
            <a href="logout.php" class="logout-btn">Logout</a>
        </div>
    </div>

    <div class="container">
        <?php if ($message): ?>
            <div class="message"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <div class="stats">
            <div class="stat-card">
                <div class="stat-number"><?= $stats['total_websites'] ?></div>
                <div class="stat-label">Websites</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $stats['total_pages'] ?></div>
                <div class="stat-label">Pages Scraped</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $stats['searches_today'] ?></div>
                <div class="stat-label">Searches Today</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $stats['pending_scrapes'] ?></div>
                <div class="stat-label">Pending Scrapes</div>
            </div>
        </div>

        <div class="section">
            <div class="section-header">Add New Website</div>
            <div class="section-content">
                <form method="POST">
                    <input type="hidden" name="action" value="add_website">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="name">Website Name:</label>
                            <input type="text" id="name" name="name" required>
                        </div>
                        <div class="form-group">
                            <label for="url">Start URL:</label>
                            <input type="url" id="url" name="url" required>
                        </div>
                        <div class="form-group">
                            <label for="max_depth">Max Depth:</label>
                            <input type="number" id="max_depth" name="max_depth" value="3" min="1" max="10">
                        </div>
                        <div class="form-group">
                            <label for="scrape_frequency">Scrape Frequency:</label>
                            <select id="scrape_frequency" name="scrape_frequency">
                                <option value="60">1 minute</option>
                                <option value="300">5 minutes</option>
                                <option value="900">15 minutes</option>
                                <option value="1800">30 minutes</option>
                                <option value="3600" selected>1 hour</option>
                                <option value="7200">2 hours</option>
                                <option value="21600">6 hours</option>
                                <option value="43200">12 hours</option>
                                <option value="86400">24 hours</option>
                                <option value="unlimited">Unlimited (No auto-scrape)</option>
                            </select>
                        </div>
                    </div>
                    <button type="submit">Add Website</button>
                </form>
            </div>
        </div>

        <div class="section">
            <div class="section-header section-header-with-button">
                <span>Managed Websites</span>
                <form method="POST" style="display: inline-block;">
                    <input type="hidden" name="action" value="scrape_all">
                    <button type="submit" class="btn-scrape-all" onclick="return confirm('Scrape all active websites? This may take several minutes and could be resource intensive.')">
                        🔄 Scrape All
                    </button>
                </form>
            </div>
            <div class="section-content">
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>URL</th>
                            <th>Status</th>
                            <th>Pages</th>
                            <th>Frequency</th>
                            <th>Last Scraped</th>
                            <th>Created By</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($websites as $website): ?>
                        <tr>
                            <td><?= htmlspecialchars($website['name']) ?></td>
                            <td><a href="<?= htmlspecialchars($website['url']) ?>" target="_blank"><?= htmlspecialchars($website['url']) ?></a></td>
                            <td class="status-<?= $website['status'] ?>"><?= ucfirst($website['status']) ?></td>
                            <td><?= $website['pages_count'] ?></td>
                            <td><?= formatScrapeFrequency($website['scrape_frequency']) ?></td>
                            <td><?= $website['last_scraped'] ? date('M j, Y H:i', strtotime($website['last_scraped'])) : 'Never' ?></td>
                            <td><?= htmlspecialchars($website['created_by_name'] ?? 'Unknown') ?></td>
                            <td>
                                <!-- <form method="POST" style="display: inline-block; margin-right: 5px;">
                                    <input type="hidden" name="action" value="scrape_website">
                                    <input type="hidden" name="website_id" value="<?= $website['id'] ?>">
                                    <button type="submit" class="btn-scrape" onclick="return confirm('Start scraping <?= htmlspecialchars($website['name']) ?>? This may take a few minutes.')">Scrape</button>
                                </form> -->
                                <button type="button" class="btn-edit" style="margin-right: 5px;" onclick="editWebsite(<?= $website['id'] ?>, '<?= htmlspecialchars($website['name'], ENT_QUOTES) ?>', '<?= htmlspecialchars($website['url'], ENT_QUOTES) ?>', <?= $website['max_depth'] ?>, <?= $website['scrape_frequency'] ?>)">Edit</button>
                                <form method="POST" style="display: inline-block;">
                                    <input type="hidden" name="action" value="delete_website">
                                    <input type="hidden" name="website_id" value="<?= $website['id'] ?>">
                                    <button type="submit" class="btn-danger" onclick="return confirm('Are you sure you want to delete this website?')">Delete</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Progress Bar Container -->
        <div id="progress-container" class="progress-container">
            <h4>🔄 Scraping in Progress...</h4>
            <div class="current-website">
                <strong>Current Website:</strong> <span id="current-website">Initializing...</span><br>
                <small id="current-url">Preparing to start...</small>
            </div>
            
            <div class="progress-bar-container">
                <div id="progress-bar" class="progress-bar">0%</div>
            </div>
            
            <div class="progress-details">
                <div class="progress-stat">
                    <div id="progress-current" class="progress-stat-number">0</div>
                    <div class="progress-stat-label">Current</div>
                </div>
                <div class="progress-stat">
                    <div id="progress-total" class="progress-stat-number">0</div>
                    <div class="progress-stat-label">Total</div>
                </div>
                <div class="progress-stat">
                    <div id="progress-completed" class="progress-stat-number">0</div>
                    <div class="progress-stat-label">Completed</div>
                </div>
                <div class="progress-stat">
                    <div id="progress-failed" class="progress-stat-number">0</div>
                    <div class="progress-stat-label">Failed</div>
                </div>
                <div class="progress-stat">
                    <div id="progress-pages" class="progress-stat-number">0</div>
                    <div class="progress-stat-label">New Pages</div>
                </div>
            </div>
            
            <div style="text-align: center; margin-top: 15px;">
                <button type="button" id="cancel-scrape" class="btn-danger" onclick="cancelScrape()">Cancel Scraping</button>
            </div>
        </div>

        <?php if (!empty($scrapeResults)): ?>
        <div class="section">
            <div class="section-header">
                <?= $scrapeResults['success'] ? '✓ Scrape Results' : '✗ Scrape Error' ?>
            </div>
            <div class="section-content">
                <div class="scrape-results <?= $scrapeResults['success'] ? 'scrape-success' : 'scrape-error' ?>">
                    <?php if (isset($scrapeResults['total_websites'])): ?>
                        <!-- Scrape All Results -->
                        <h4><?= $scrapeResults['success'] ? '✓' : '⚠' ?> Scrape All Results</h4>
                        <div class="scrape-details">
                            <div><strong>Total Websites:</strong> <?= $scrapeResults['total_websites'] ?></div>
                            <div><strong>Successful:</strong> <span style="color: #28a745; font-weight: bold;"><?= $scrapeResults['successful_scrapes'] ?></span></div>
                            <div><strong>Failed:</strong> <span style="color: #dc3545; font-weight: bold;"><?= $scrapeResults['failed_scrapes'] ?></span></div>
                            <div><strong>Total Duration:</strong> <?= $scrapeResults['total_duration'] ?> seconds</div>
                            <div><strong>Total New Pages:</strong> <span style="color: #28a745; font-weight: bold;"><?= number_format($scrapeResults['total_new_pages']) ?></span></div>
                        </div>
                        
                        <?php if (!empty($scrapeResults['details'])): ?>
                            <div style="margin-top: 20px;">
                                <h5>Detailed Results:</h5>
                                <table style="width: 100%; margin-top: 10px; font-size: 14px;">
                                    <thead>
                                        <tr style="background-color: rgba(0,0,0,0.1);">
                                            <th style="padding: 8px; text-align: left;">Website</th>
                                            <th style="padding: 8px; text-align: left;">Status</th>
                                            <th style="padding: 8px; text-align: left;">Duration</th>
                                            <th style="padding: 8px; text-align: left;">New Pages</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($scrapeResults['details'] as $detail): ?>
                                        <tr>
                                            <td style="padding: 8px;">
                                                <strong><?= htmlspecialchars($detail['name']) ?></strong><br>
                                                <small><?= htmlspecialchars($detail['url']) ?></small>
                                            </td>
                                            <td style="padding: 8px;">
                                                <?php if ($detail['success']): ?>
                                                    <span style="color: #28a745;">✓ Success</span>
                                                <?php else: ?>
                                                    <span style="color: #dc3545;">✗ Failed</span><br>
                                                    <small><?= htmlspecialchars($detail['error']) ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td style="padding: 8px;">
                                                <?= isset($detail['duration']) ? $detail['duration'] . 's' : 'N/A' ?>
                                            </td>
                                            <td style="padding: 8px;">
                                                <?= isset($detail['new_pages']) ? number_format($detail['new_pages']) : 'N/A' ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($scrapeResults['total_new_pages'] > 0): ?>
                            <div style="margin-top: 15px;">
                                <a href="search.php" class="btn-scrape" style="display: inline-block; text-decoration: none; padding: 8px 16px; border-radius: 4px;">
                                    Test All Search Results
                                </a>
                            </div>
                        <?php endif; ?>
                        
                    <?php else: ?>
                        <!-- Single Website Results -->
                        <?php if ($scrapeResults['success']): ?>
                            <h4>✓ Scraping Completed Successfully!</h4>
                            <div class="scrape-details">
                                <div><strong>Website:</strong> <?= htmlspecialchars($scrapeResults['website_name']) ?></div>
                                <div><strong>URL:</strong> <a href="<?= htmlspecialchars($scrapeResults['website_url']) ?>" target="_blank"><?= htmlspecialchars($scrapeResults['website_url']) ?></a></div>
                                <div><strong>Duration:</strong> <?= $scrapeResults['duration'] ?> seconds</div>
                                <div><strong>Max Depth:</strong> <?= $scrapeResults['max_depth'] ?> levels</div>
                                <div><strong>Pages Before:</strong> <?= number_format($scrapeResults['initial_pages']) ?></div>
                                <div><strong>Pages After:</strong> <?= number_format($scrapeResults['final_pages']) ?></div>
                                <div><strong>New Pages:</strong> <span style="color: #28a745; font-weight: bold;"><?= number_format($scrapeResults['new_pages']) ?></span></div>
                            </div>
                            <?php if ($scrapeResults['new_pages'] > 0): ?>
                                <div style="margin-top: 15px;">
                                    <a href="search.php?addsearch=<?= urlencode($scrapeResults['website_name']) ?>" class="btn-scrape" style="display: inline-block; text-decoration: none; padding: 8px 16px; border-radius: 4px;">
                                        Test Search Results
                                    </a>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <h4>✗ Scraping Failed</h4>
                            <div class="scrape-details">
                                <div><strong>Website:</strong> <?= htmlspecialchars($scrapeResults['website_name']) ?></div>
                                <div><strong>URL:</strong> <a href="<?= htmlspecialchars($scrapeResults['website_url']) ?>" target="_blank"><?= htmlspecialchars($scrapeResults['website_url']) ?></a></div>
                                <div><strong>Error:</strong> <?= htmlspecialchars($scrapeResults['error']) ?></div>
                            </div>
                            <div style="margin-top: 15px;">
                                <h5>Common Issues:</h5>
                                <ul>
                                    <li>Website might be blocking automated requests</li>
                                    <li>Network connectivity issues</li>
                                    <li>Website structure might be incompatible</li>
                                    <li>Timeout due to slow response times</li>
                                </ul>
                                <p><strong>Suggestion:</strong> Try again later or check if the website is accessible manually.</p>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php
        // Show recent scrape activity
        /*
        $recentScrapes = $db->fetchAll("
            SELECT w.name, w.url, w.last_scraped, 
                   (SELECT COUNT(*) FROM scraped_pages WHERE website_id = w.id) as total_pages
            FROM websites w 
            WHERE w.last_scraped IS NOT NULL 
            ORDER BY w.last_scraped DESC 
            LIMIT 5
        ");
        
        if (!empty($recentScrapes)):
        ?>
        <div class="section">
            <div class="section-header">Recent Scraping Activity</div>
            <div class="section-content">
                <table>
                    <thead>
                        <tr>
                            <th>Website</th>
                            <th>Last Scraped</th>
                            <th>Total Pages</th>
                            <th>Quick Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentScrapes as $scrape): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($scrape['name']) ?></strong><br>
                                <small><a href="<?= htmlspecialchars($scrape['url']) ?>" target="_blank"><?= htmlspecialchars($scrape['url']) ?></a></small>
                            </td>
                            <td><?= date('M j, Y H:i', strtotime($scrape['last_scraped'])) ?></td>
                            <td><?= number_format($scrape['total_pages']) ?></td>
                            <td>
                                <a href="search.php?addsearch=<?= urlencode($scrape['name']) ?>" target="_blank" style="color: #1771b7; text-decoration: none;">
                                    🔍 Search Content
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>
        <?php */ ?>

    <!-- Edit Website Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Edit Website</h3>
                <button type="button" class="modal-close" onclick="closeEditModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST" id="editForm">
                    <input type="hidden" name="action" value="edit_website">
                    <input type="hidden" name="website_id" id="edit_website_id">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit_name">Website Name:</label>
                            <input type="text" id="edit_name" name="name" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_url">Start URL:</label>
                            <input type="url" id="edit_url" name="url" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit_max_depth">Max Depth:</label>
                            <input type="number" id="edit_max_depth" name="max_depth" value="3" min="1" max="10">
                        </div>
                        <div class="form-group">
                            <label for="edit_scrape_frequency">Scrape Frequency:</label>
                            <select id="edit_scrape_frequency" name="scrape_frequency">
                                <option value="60">1 minute</option>
                                <option value="300">5 minutes</option>
                                <option value="900">15 minutes</option>
                                <option value="1800">30 minutes</option>
                                <option value="3600">1 hour</option>
                                <option value="7200">2 hours</option>
                                <option value="21600">6 hours</option>
                                <option value="43200">12 hours</option>
                                <option value="86400">24 hours</option>
                                <option value="unlimited">Unlimited (No auto-scrape)</option>
                            </select>
                        </div>
                    </div>
                    
                    <div style="margin-top: 20px; text-align: right;">
                        <button type="button" onclick="closeEditModal()" style="background-color: #6c757d; margin-right: 10px;">Cancel</button>
                        <button type="submit" class="btn-edit">Update Website</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        let progressInterval;
        let currentSessionId = '';

        function startScrapeAll() {
            // Show progress bar
            document.getElementById('progress-container').style.display = 'block';
            
            // Generate session ID
            currentSessionId = 'scrape_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
            
            // Reset progress
            updateProgress({
                current_index: 0,
                total_websites: 0,
                percentage: 0,
                current_website: 'Initializing...',
                current_url: 'Preparing to start...',
                completed_count: 0,
                failed_count: 0,
                total_new_pages: 0
            });
            
            // Start scraping with AJAX
            const formData = new FormData();
            formData.append('action', 'scrape_all_ajax');
            formData.append('session_id', currentSessionId);
            
            fetch('admin.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Start polling for progress
                    startProgressPolling();
                } else {
                    alert('Failed to start scraping: ' + data.error);
                    hideProgress();
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Failed to start scraping');
                hideProgress();
            });
        }

        function startProgressPolling() {
            progressInterval = setInterval(() => {
                fetch(`progress.php?session=${currentSessionId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'completed' || data.status === 'failed') {
                        clearInterval(progressInterval);
                        setTimeout(() => {
                            hideProgress();
                            // Reload page to show results
                            window.location.reload();
                        }, 2000);
                    } else if (data.status === 'in_progress') {
                        updateProgress(data);
                    }
                })
                .catch(error => {
                    console.error('Progress fetch error:', error);
                });
            }, 1000); // Poll every second
        }

        function updateProgress(data) {
            const progressBar = document.getElementById('progress-bar');
            const percentage = data.percentage || 0;
            
            progressBar.style.width = percentage + '%';
            progressBar.textContent = percentage.toFixed(1) + '%';
            
            document.getElementById('current-website').textContent = data.current_website || 'Unknown';
            document.getElementById('current-url').textContent = data.current_url || '';
            document.getElementById('progress-current').textContent = data.current_index || 0;
            document.getElementById('progress-total').textContent = data.total_websites || 0;
            document.getElementById('progress-completed').textContent = data.completed_count || 0;
            document.getElementById('progress-failed').textContent = data.failed_count || 0;
            document.getElementById('progress-pages').textContent = data.total_new_pages || 0;
        }

        function hideProgress() {
            document.getElementById('progress-container').style.display = 'none';
            if (progressInterval) {
                clearInterval(progressInterval);
            }
        }

        function cancelScrape() {
            if (confirm('Are you sure you want to cancel the scraping process?')) {
                // Send cancel request
                const formData = new FormData();
                formData.append('action', 'cancel_scrape');
                formData.append('session_id', currentSessionId);
                
                fetch('admin.php', {
                    method: 'POST',
                    body: formData
                });
                
                hideProgress();
            }
        }

        // Edit website functions
        function editWebsite(id, name, url, maxDepth, frequency) {
            document.getElementById('edit_website_id').value = id;
            document.getElementById('edit_name').value = name;
            document.getElementById('edit_url').value = url;
            document.getElementById('edit_max_depth').value = maxDepth;
            
            // Handle frequency selection
            const frequencySelect = document.getElementById('edit_scrape_frequency');
            if (frequency === -1) {
                frequencySelect.value = 'unlimited';
            } else {
                frequencySelect.value = frequency;
            }
            
            document.getElementById('editModal').style.display = 'block';
        }

        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('editModal');
            if (event.target === modal) {
                closeEditModal();
            }
        }

        // Modify the Scrape All button to use JavaScript instead of form submission
        document.addEventListener('DOMContentLoaded', function() {
            const scrapeAllBtn = document.querySelector('button.btn-scrape-all');
            if (scrapeAllBtn) {
                scrapeAllBtn.onclick = function(e) {
                    e.preventDefault();
                    if (confirm('Scrape all active websites? This may take several minutes and could be resource intensive.')) {
                        startScrapeAll();
                    }
                    return false;
                };
            }
        });
    </script>
</body>
</html>
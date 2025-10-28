<?php
require_once 'vendor/autoload.php';

use CCSD\Search\Auth;
use CCSD\Search\Database;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Set headers for AJAX response
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

$db = Database::getInstance();

// Get the progress session ID from request
$sessionId = $_GET['session'] ?? '';
if (empty($sessionId)) {
    echo json_encode(['error' => 'No session ID provided']);
    exit;
}

try {
    // Check if progress record exists
    $progress = $db->fetchOne(
        "SELECT * FROM scrape_progress WHERE session_id = ? ORDER BY updated_at DESC LIMIT 1",
        [$sessionId]
    );
    
    if ($progress) {
        echo json_encode([
            'status' => $progress['status'],
            'current_website' => $progress['current_website'],
            'current_index' => (int)$progress['current_index'],
            'total_websites' => (int)$progress['total_websites'],
            'percentage' => round(($progress['current_index'] / max(1, $progress['total_websites'])) * 100, 1),
            'current_url' => $progress['current_url'],
            'completed_count' => (int)$progress['completed_count'],
            'failed_count' => (int)$progress['failed_count'],
            'total_new_pages' => (int)$progress['total_new_pages'],
            'message' => $progress['message']
        ]);
    } else {
        echo json_encode([
            'status' => 'not_found',
            'message' => 'Progress session not found'
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
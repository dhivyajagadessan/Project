<?php
/**
 * Hero Slides API
 */

require_once 'config.php';

$pdo = getDBConnection();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $stmt = $pdo->query("SELECT * FROM hero_slides WHERE is_active = 1 ORDER BY display_order");
        $slides = $stmt->fetchAll();
        
        sendSuccess('Hero slides fetched successfully', $slides);
        
    } catch (PDOException $e) {
        sendError('Failed to fetch slides: ' . $e->getMessage(), 500);
    }
}
?>

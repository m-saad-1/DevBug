<?php
// clear_notifications.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit();
}

require_once 'config/database.php';

$user_id = $_SESSION['user_id'];

try {
    $sql = "DELETE FROM notifications WHERE user_id = ?";
    $pdo->prepare($sql)->execute([$user_id]);
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
?>
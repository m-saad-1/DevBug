<?php
session_start();
require_once 'config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['user_id'])) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    try {
        if (isset($input['markAll']) && $input['markAll']) {
            // Mark all notifications as read
            $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
            $stmt->execute([$_SESSION['user_id']]);
        } elseif (isset($input['notificationId'])) {
            // Mark single notification as read
            $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
            $stmt->execute([$input['notificationId'], $_SESSION['user_id']]);
        }
        
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
}
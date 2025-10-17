<?php
// mark_notifications_read.php
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
$data = json_decode(file_get_contents('php://input'), true);

try {
    if (isset($data['markAll']) && $data['markAll'] === true) {
        // Mark all as read
        $sql = "UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$user_id]);
    } elseif (isset($data['notificationId'])) {
        // Mark a single notification as read
        $notification_id = (int)$data['notificationId'];
        $sql = "UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$notification_id, $user_id]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid request']);
        exit();
    }

    echo json_encode(['success' => true]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
?>
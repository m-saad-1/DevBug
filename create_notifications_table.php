<?php
require_once 'config/database.php';

try {
    $sql = "
    CREATE TABLE `notifications` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `user_id` int(11) NOT NULL,
      `sender_id` int(11) DEFAULT NULL,
      `type` varchar(50) NOT NULL,
      `message` varchar(255) NOT NULL,
      `link` varchar(500) DEFAULT NULL,
      `is_read` tinyint(1) NOT NULL DEFAULT 0,
      `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
      PRIMARY KEY (`id`),
      KEY `user_id` (`user_id`),
      CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
    ";

    $pdo->exec($sql);

    echo "Table 'notifications' created successfully.";

} catch (PDOException $e) {
    die("Could not create table: " . $e->getMessage());
}
?>